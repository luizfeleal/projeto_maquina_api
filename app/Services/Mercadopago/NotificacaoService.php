<?php

namespace App\Services\Mercadopago;

use App\Models\CredApiPix;
use App\Models\ExtratoMaquina;
use App\Models\Logs;
use App\Models\MaquinaCartao;
use App\Models\Maquinas;
use App\Services\Efi\DescriptografaCredService;
use App\Services\Hardware\AuthService;
use App\Services\Hardware\JogadasService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use MercadoPago\Exceptions\InvalidWebhookSignatureException;
use MercadoPago\Webhook\WebhookSignatureValidator;

class NotificacaoService
{
    /**
     * Status de pagamento do Mercado Pago que representam dinheiro efetivamente
     * recebido. Qualquer outro status (pending, in_process, rejected, cancelled,
     * refunded, charged_back etc.) não deve liberar jogada nem gerar extrato.
     */
    private const STATUS_APROVADO = 'approved';

    /**
     * Timeout curto de propósito: o Mercado Pago espera uma resposta rápida do
     * webhook (considera falha e reenvia depois de ~20s). Nunca deixamos uma
     * única chamada de rede segurar o processamento por mais que isso.
     */
    private const TIMEOUT_SEGUNDOS = 5;

    public static function coletarDadosNotificacao(?string $xSignature, ?string $xRequestId, ?string $dataId)
    {
        if (empty($dataId)) {
            \Log::error('Notificação do Mercado Pago recebida sem data.id (query ou corpo).');
            return ['http_code' => 400, 'resposta' => null];
        }

        $credenciais = CredApiPix::where('tipo_cred', 'mercadopago')->get()->toArray();
        $descriptografadas = array_values(array_map(
            fn ($credencial) => DescriptografaCredService::descriptografarCred($credencial),
            $credenciais
        ));

        // 1ª passada: tenta validar por assinatura (x-signature). É barato (só
        // HMAC em memória, sem rede) e só dispara UMA chamada de rede, pra
        // credencial que efetivamente validar. Vale pro fluxo dinâmico de
        // Payments API. A documentação oficial confirma que notificações do
        // produto "Código QR"/POS (o que usamos hoje) NÃO têm assinatura
        // verificável — então é esperado essa passada não achar nada nesse
        // caso, e cair no fallback em paralelo abaixo.
        foreach ($descriptografadas as $index => $dado) {
            try {
                WebhookSignatureValidator::validate($xSignature, $xRequestId, $dataId, $dado['client_id']);
            } catch (InvalidWebhookSignatureException $e) {
                continue;
            }

            \Log::info("Assinatura do Mercado Pago validada com a credencial $index.");

            $payment = self::consultarPagamento($dataId, $dado['client_secret']);

            if ($payment === null) {
                return ['http_code' => 502, 'resposta' => null, 'aprovado' => false];
            }

            if ($payment === false) {
                \Log::warning("Pagamento $dataId não encontrado no Mercado Pago mesmo com assinatura válida (credencial $index, notificação de teste?).");
                return ['http_code' => 200, 'resposta' => null, 'aprovado' => false];
            }

            return self::montarRespostaProcessada($payment);
        }

        if (empty($descriptografadas)) {
            \Log::error("Nenhuma credencial mercadopago cadastrada para localizar o pagamento $dataId.");
            return ['http_code' => 401, 'resposta' => null];
        }

        // 2ª passada (fallback sem assinatura, esperado pro produto QR/POS):
        // como o Mercado Pago não assina essas notificações, a defesa deixa de
        // ser "a assinatura bateu" e passa a ser "conseguimos confirmar esse
        // pagamento de verdade com o access token de um cliente nosso" (um
        // payment_id de outra conta/forjado retorna 404). Consultamos TODAS as
        // credenciais EM PARALELO (Http::pool) em vez de uma por vez -- com N
        // credenciais cadastradas, uma busca sequencial soma a latência de
        // cada chamada e passa fácil do timeout que o Mercado Pago tolera pro
        // webhook responder.
        \Log::warning("Nenhuma credencial validou a assinatura da notificação Mercado Pago (data.id: $dataId) — buscando o pagamento em paralelo em " . count($descriptografadas) . " credencial(is) (esperado para o produto QR/POS, que não é assinado).");

        $respostas = Http::pool(fn (Pool $pool) => array_map(
            fn ($dado) => $pool->withToken($dado['client_secret'])
                ->timeout(self::TIMEOUT_SEGUNDOS)
                ->get("https://api.mercadopago.com/v1/payments/{$dataId}"),
            $descriptografadas
        ));

        foreach ($respostas as $index => $resposta) {
            if ($resposta instanceof \Throwable) {
                \Log::warning("Falha ao consultar pagamento $dataId com a credencial $index: " . $resposta->getMessage());
                continue;
            }

            if ($resposta->status() === 404) {
                continue;
            }

            if (!$resposta->successful()) {
                \Log::error("Falha ao consultar pagamento $dataId com a credencial $index (HTTP {$resposta->status()}): " . $resposta->body());
                continue;
            }

            \Log::info("Pagamento $dataId localizado via fallback sem assinatura com a credencial $index.");

            return self::montarRespostaProcessada($resposta->json());
        }

        \Log::error("Pagamento $dataId não encontrado em nenhuma credencial Mercado Pago cadastrada (sem assinatura válida e busca em paralelo falhou em todas).");
        return ['http_code' => 401, 'resposta' => null];
    }

    /**
     * Busca o pagamento na API do Mercado Pago com o access token informado.
     * Retorna o payment (array) em sucesso, `false` se 404 (não encontrado com
     * essa credencial -- não é erro, é como descobrimos o dono) e `null` em
     * falha transitória (timeout, 5xx etc.).
     */
    private static function consultarPagamento(string $dataId, string $accessToken)
    {
        try {
            $resposta = Http::withToken($accessToken)
                ->timeout(self::TIMEOUT_SEGUNDOS)
                ->get("https://api.mercadopago.com/v1/payments/{$dataId}");
        } catch (\Throwable $e) {
            \Log::error("Erro de conexão ao consultar pagamento $dataId no Mercado Pago: " . $e->getMessage());
            return null;
        }

        if ($resposta->status() === 404) {
            return false;
        }

        if (!$resposta->successful()) {
            \Log::error("Falha ao consultar pagamento $dataId no Mercado Pago (HTTP {$resposta->status()}): " . $resposta->body());
            return null;
        }

        return $resposta->json();
    }

    /**
     * Monta a resposta padrão (aprovado/ignorado + linhas de extrato) a partir
     * de um pagamento já confirmado via API do Mercado Pago.
     */
    private static function montarRespostaProcessada(array $payment): array
    {
        \Log::info('----------resultado da coleta de pagamento Mercado Pago--------');
        \Log::info(json_encode($payment));

        $status = $payment['status'] ?? null;

        if ($status !== self::STATUS_APROVADO) {
            \Log::info("Pagamento {$payment['id']} com status '$status', ignorado (aguardando aprovação ou não aprovado).");
            return ['http_code' => 200, 'resposta' => null, 'aprovado' => false];
        }

        return ['http_code' => 200, 'resposta' => self::montarDadosTransacao($payment), 'aprovado' => true];
    }

    /**
     * Monta a estrutura de crédito/débito/device a partir do array de um
     * payment do Mercado Pago (mesmo formato retornado por
     * Http::get(...)->json() e pelo polling de reconciliação em
     * App\Console\Commands\PollMercadopagoTransactions, que converte o
     * resultado do SDK pra array antes de chamar este método). Público para
     * ser reaproveitado pelos dois caminhos.
     */
    public static function montarDadosTransacao(array $payment): array
    {
        $codigo_transacao = (string) $payment['id'];
        $valor_transacao = $payment['transaction_amount'];
        $valor_taxa = self::calcularTaxa($payment, $valor_transacao);
        $device_info = $payment['pos_id'] ?? null;

        $data_credito = [
            'id_end_to_end' => $codigo_transacao,
            'id_maquina' => 0,
            'extrato_operacao_valor' => $valor_transacao,
            'extrato_operacao_tipo' => 'Cartão MP',
            'extrato_operacao_status' => 1,
            'extrato_operacao' => 'C'
        ];
        $data_debito = [
            'id_end_to_end' => $codigo_transacao,
            'id_maquina' => 0,
            'extrato_operacao_valor' => $valor_taxa,
            'extrato_operacao_tipo' => 'Taxa MP',
            'extrato_operacao_status' => 1,
            'extrato_operacao' => 'D'
        ];

        return [
            'credito' => $data_credito,
            'debito' => $data_debito,
            'device' => $device_info
        ];
    }

    /**
     * Processa um pagamento já confirmado como aprovado: idempotência (com
     * lock por transação, pra proteger contra o Mercado Pago reenviando a
     * notificação enquanto a primeira ainda está em andamento, e contra o
     * polling de reconciliação pegando o mesmo pagamento no meio do caminho),
     * resolução da máquina pelo device (pos_id), lançamento no extrato e
     * liberação da jogada no hardware. Reaproveitado tanto pelo
     * WebhookController quanto pelo polling
     * (App\Console\Commands\PollMercadopagoTransactions).
     *
     * @param array{credito: array, debito: array, device: mixed} $resposta
     * @return array{status: string, liberado?: bool, hardware?: mixed}
     */
    public static function processarPagamentoAprovado(array $resposta): array
    {
        $codigoTransacao = $resposta['credito']['id_end_to_end'];
        $valorRecebido = $resposta['credito']['extrato_operacao_valor'] ?? null;
        $deviceInfo = $resposta['device'] ?? null;

        \Log::info("Pagamento recebido para processamento: transacao=$codigoTransacao, valor=$valorRecebido, device=$deviceInfo");

        $lock = Cache::lock("webhook:mercadopago:transacao:$codigoTransacao", 30);

        try {
            $lock->block(10);
        } catch (LockTimeoutException $e) {
            \Log::info("Timeout aguardando lock do pagamento $codigoTransacao; outra requisição já está processando essa notificação.");
            return ['status' => 'ja_processado'];
        }

        try {
            if (ExtratoMaquina::where('id_end_to_end', $codigoTransacao)->exists()) {
                \Log::info("Notificação duplicada do Mercado Pago para o pagamento $codigoTransacao, já processada anteriormente.");
                return ['status' => 'ja_processado'];
            }

            $liberarJogada = true;
            $device_numero = $resposta['device'];

            \Log::info('---------Numero device --------');
            \Log::info($device_numero);

            $device = MaquinaCartao::where('device', $device_numero)->where('status', 1)->get()->toArray();
            \Log::info('---------Device Encontrado--------');
            \Log::info($device);
            if (empty($device)) {
                Logs::create([
                    "descricao" => "Erro ao tentar liberar uma jogada, device de número: $device_numero não foi encontrado no sistema.",
                    "status" => "erro",
                    "acao" => "liberar jogada",
                    "id_maquina" => 0
                ]);
                return ['status' => 'device_nao_encontrado'];
            }
            $id_maquina = $device[0]['id_maquina'];

            \Log::info('---------ID Maquina pelo device--------');
            \Log::info($id_maquina);

            $resposta['credito']['id_maquina'] = $id_maquina;
            $resposta['debito']['id_maquina'] = $id_maquina;

            $maquina = Maquinas::where('id_maquina', $id_maquina)->get();
            \Log::info('--------Máquina encontrada cartão------');
            \Log::info($maquina);
            if (isset($maquina[0])) {
                if (!empty($maquina) && $maquina[0]['bloqueio_jogada_mercadopago'] == 1) {
                    $liberarJogada = false;
                    Logs::create([
                        "descricao" => "Erro ao tentar liberar jogadas! A máquina de cartão se encontra como bloqueada para liberar jogadas por maquininha de cartão (Mercado Pago).",
                        "status" => "erro",
                        "acao" => "liberar jogada",
                        "id_maquina" => $id_maquina
                    ]);
                }
            } else {
                $liberarJogada = false;
                Logs::create([
                    "descricao" => "Erro ao tentar liberar jogadas! Não foi possível encontrar a máquina. Verifique o registro!",
                    "status" => "erro",
                    "acao" => "liberar jogada",
                    "id_maquina" => $id_maquina
                ]);
            }

            // Registramos o extrato assim que decidimos processar o pagamento, antes de
            // acionar o hardware: se o Mercado Pago reenviar a mesma notificação (retry)
            // ou o polling encontrar o mesmo pagamento de novo, a checagem de
            // idempotência acima passa a barrar o reprocessamento.
            $dadosExtrato = [
                $resposta['credito'],
                $resposta['debito']
            ];
            ExtratoMaquina::insert($dadosExtrato);

            $tentativas = 0;
            $maxTentativas = env('TENTATIVAS_PERSISTENCIA_JOGADA');
            $respostaHardware = null;
            do {
                if ($liberarJogada == true) {
                    $valor = $resposta['credito']['extrato_operacao_valor'];
                    $idE2E = $resposta['credito']['id_end_to_end'];

                    $maquina = Maquinas::find($id_maquina);

                    $id_placa = $maquina['id_placa'];

                    $token = AuthService::coletarToken();
                    $respostaHardware = JogadasService::liberarJogada($id_placa, $valor, $idE2E, $token);
                    $tentativas++;

                    if ($respostaHardware['http_code'] == 200) {
                        \Log::info("Jogada liberada com sucesso: transacao=$idE2E, id_maquina=$id_maquina, id_placa=$id_placa, valor=$valor, tentativa=$tentativas");
                        break;
                    }

                    \Log::warning("Falha ao liberar jogada (tentativa $tentativas): transacao=$idE2E, id_maquina=$id_maquina, id_placa=$id_placa, resposta=" . json_encode($respostaHardware));

                    if ($tentativas >= $maxTentativas) {
                        \Log::error("Jogada NÃO liberada após $tentativas tentativa(s): transacao=$idE2E, id_maquina=$id_maquina, id_placa=$id_placa");
                        Logs::create([
                            "descricao" => "Erro ao tentar liberar jogadas, número de tentativas de comunicação com a máquina foi excedido.",
                            "status" => "erro",
                            "acao" => "liberar jogada",
                            "id_maquina" => $id_maquina
                        ]);

                        //Fazer o estorno aqui
                        break;
                    }
                } else {
                    break;
                }
            } while ($respostaHardware['http_code'] != 200);

            return ['status' => 'processado', 'liberado' => $liberarJogada, 'hardware' => $respostaHardware];
        } finally {
            $lock->release();
        }
    }

    /**
     * Taxa cobrada pelo Mercado Pago. transaction_details.net_received_amount só é
     * confiável após a liquidação do pagamento, então preferimos a soma de
     * fee_details (mais precisa e disponível já na aprovação).
     */
    private static function calcularTaxa(array $payment, $valor_transacao): float
    {
        $feeDetails = $payment['fee_details'] ?? null;

        if (!empty($feeDetails)) {
            $totalTaxas = 0;
            foreach ($feeDetails as $fee) {
                $totalTaxas += $fee['amount'] ?? 0;
            }

            if ($totalTaxas > 0) {
                return (float) $totalTaxas;
            }
        }

        $valorLiquido = $payment['transaction_details']['net_received_amount'] ?? null;

        if (!empty($valorLiquido)) {
            return (float) ($valor_transacao - $valorLiquido);
        }

        return 0.0;
    }
}
