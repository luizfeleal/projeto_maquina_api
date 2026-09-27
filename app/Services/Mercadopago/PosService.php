<?php

namespace App\Services\Mercadopago;

use App\Models\MaquinaCartao;
use App\Models\MercadopagoLoja;
use App\Models\MercadopagoPos;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mpdf\QrCode\Output;
use Mpdf\QrCode\QrCode as MpdfQrCode;

class PosService
{
    /**
     * O SDK oficial (mercadopago/dx-php) não expõe cliente para a API de POS,
     * por isso a chamada é feita direto via HTTP, seguindo a documentação
     * (POST /v2/pos).
     */
    private const BASE_URL = 'https://api.mercadopago.com';

    /**
     * Retorna o POS (caixa) já cadastrado da máquina ou cria um novo no Mercado
     * Pago na primeira vez, já com o QR Code estático renderizado. Mesmo
     * espírito do QrController da Efí: criação automática e reaproveitada.
     */
    public static function criarOuObterPos(int $idMaquina, int $idLocal, int $idCliente, MercadopagoLoja $loja, string $accessToken): MercadopagoPos
    {
        $posExistente = MercadopagoPos::where('id_maquina', $idMaquina)->first();

        if ($posExistente) {
            return $posExistente;
        }

        $externalPosId = 'MAQ' . $idMaquina;

        $payload = [
            'name' => $externalPosId,
            'store_id' => $loja->mp_store_id,
            'external_id' => $externalPosId,
            'config' => [
                'qr' => [
                    // 'standalone': QR estático de valor livre — o cliente digita o
                    // valor no próprio app do Mercado Pago ao escanear, igual ao
                    // fluxo da chave aleatória Efí. Diferente do modo 'pdv' (atendido,
                    // requer operador), este não tem order/PDV integrado via API, então
                    // não há garantia de notificação via webhook — o polling de
                    // reconciliação (App\Console\Commands\PollMercadopagoTransactions)
                    // passa a ser o caminho que garante a confirmação do pagamento,
                    // não só um fallback do webhook.
                    'operating_mode' => 'standalone',
                ],
            ],
        ];

        $resposta = Http::withToken($accessToken)
            ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
            ->post(self::BASE_URL . '/v2/pos', $payload);

        \Log::info('Criação de POS Mercado Pago -----------------');
        \Log::info($resposta->body());

        if (self::erroPosJaExiste($resposta)) {
            // A conta já tem um caixa com este external_id — mesma situação da Loja
            // (ver LojaService::criarOuObterLoja): provável sobra de uma tentativa
            // anterior que criou o POS no Mercado Pago com sucesso, mas não chegou a
            // salvar em mercadopago_pos (ex.: falhou depois, ao montar a imagem do QR,
            // ou a transação do banco foi revertida). Em vez de falhar, buscamos o POS
            // já existente na conta e reaproveitamos.
            \Log::warning("External_id $externalPosId já existe na conta (store {$loja->mp_store_id}); buscando o POS existente em vez de criar outro.");
            $dados = self::buscarPosPorExternalId($loja->mp_store_id, $externalPosId, $accessToken);

            if ($dados === null) {
                throw new \Exception("Já existe um POS com external_id $externalPosId na conta do Mercado Pago, mas não foi possível localizá-lo via busca.");
            }
        } elseif ($resposta->failed()) {
            throw new \Exception('Falha ao criar o POS no Mercado Pago: ' . $resposta->body());
        } else {
            $dados = $resposta->json();
        }

        $mpPosId = (string) $dados['id'];

        // O formato exato de qr_response deve ser conferido em sandbox antes de ir
        // para produção; cobrimos as chaves mais prováveis da resposta da API.
        $qrData = $dados['qr_response']['qr_code']
            ?? $dados['qr_response']['image']
            ?? null;

        if (empty($qrData)) {
            // json_encode($dados) em vez de $resposta->body(): no caminho de
            // reaproveitamento (pos_already_exists), $resposta ainda é a tentativa de
            // criação que falhou, não o POS que estamos de fato usando.
            throw new \Exception('POS do Mercado Pago sem qr_response utilizável (qr_code/image). Dados: ' . json_encode($dados));
        }

        $obQrCode = new MpdfQrCode($qrData);
        $image = (new Output\Png())->output($obQrCode, 400);
        $qrImage = 'data:image/png;base64, ' . base64_encode($image);

        $pos = new MercadopagoPos();
        $pos->fill([
            'id_cliente' => $idCliente,
            'id_local' => $idLocal,
            'id_maquina' => $idMaquina,
            'id_mercadopago_loja' => $loja->id,
            'mp_pos_id' => $mpPosId,
            'external_pos_id' => $externalPosId,
            'qr_image' => $qrImage,
            'qr_data' => $qrData,
            'ativo' => 1,
        ]);
        $pos->save();

        self::vincularMaquinaCartao($idMaquina, $mpPosId);
        self::vincularMaquinaCartao($idMaquina, $externalPosId);

        return $pos;
    }

    /**
     * Detecta especificamente o erro "pos_already_exists" (a mensagem vem em
     * `errors[].code`, formato diferente do erro de external_id da Loja, que vem
     * em `message`). Não confiamos no HTTP status (não confirmado em sandbox se é
     * sempre 400) — checamos o código do erro no corpo independente do status.
     */
    private static function erroPosJaExiste($resposta): bool
    {
        if ($resposta->successful()) {
            return false;
        }

        $erros = $resposta->json()['errors'] ?? [];

        foreach ($erros as $erro) {
            if (($erro['code'] ?? null) === 'pos_already_exists') {
                return true;
            }
        }

        return false;
    }

    /**
     * Busca o POS de uma loja pelo external_id (usado quando a criação falha
     * porque já existe um caixa com esse external_id — ver criarOuObterPos).
     * A listagem pode não trazer o qr_response completo, então buscamos o
     * detalhe pelo id em seguida pra garantir o qr_code/image necessário.
     * Log completo das duas respostas de propósito, mesmo motivo do
     * LojaService::buscarLojaPorExternalId: não confirmado em sandbox o
     * formato exato de nenhuma das duas.
     */
    private static function buscarPosPorExternalId(string $storeId, string $externalId, string $accessToken): ?array
    {
        $resposta = Http::withToken($accessToken)
            ->get(self::BASE_URL . '/v2/pos', [
                'external_id' => $externalId,
                'store_id' => $storeId,
            ]);

        \Log::info('Busca de POS Mercado Pago por external_id -----------------');
        \Log::info($resposta->body());

        if (!$resposta->successful()) {
            return null;
        }

        $corpo = $resposta->json();
        $resultados = $corpo['results'] ?? (array_is_list($corpo ?? []) ? $corpo : []);
        $encontrado = $resultados[0] ?? null;

        if (empty($encontrado['id'])) {
            return null;
        }

        $respostaDetalhe = Http::withToken($accessToken)
            ->get(self::BASE_URL . '/v2/pos/' . $encontrado['id']);

        \Log::info('Detalhe do POS Mercado Pago encontrado -----------------');
        \Log::info($respostaDetalhe->body());

        return $respostaDetalhe->successful() ? $respostaDetalhe->json() : $encontrado;
    }

    /**
     * O webhook de pagamento (App\Services\Mercadopago\NotificacaoService) já
     * resolve a máquina lendo maquina_cartao.device = payment->pos_id. Não está
     * confirmado em sandbox se o Mercado Pago devolve nesse campo o id numérico
     * do POS ou o external_id que escolhemos, então registramos os dois como
     * device válido para a mesma máquina — o webhook resolve com o que vier.
     */
    private static function vincularMaquinaCartao(int $idMaquina, string $device): void
    {
        $jaExiste = MaquinaCartao::where('device', $device)->exists();

        if ($jaExiste) {
            return;
        }

        $maquinaCartao = new MaquinaCartao();
        $maquinaCartao->fill([
            'id_maquina' => $idMaquina,
            'device' => $device,
            'status' => 1,
        ]);
        $maquinaCartao->save();
    }
}
