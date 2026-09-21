<?php

namespace App\Services\Mercadopago;

use App\Models\CredApiPix;
use App\Services\Efi\DescriptografaCredService;
use Illuminate\Http\Client\Pool;
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

        $dado_transacao = [
            'credito' => $data_credito,
            'debito' => $data_debito,
            'device' => $device_info
        ];

        return ['http_code' => 200, 'resposta' => $dado_transacao, 'aprovado' => true];
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
