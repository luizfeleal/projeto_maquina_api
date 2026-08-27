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
                    'operating_mode' => 'pdv',
                ],
            ],
        ];

        $resposta = Http::withToken($accessToken)
            ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
            ->post(self::BASE_URL . '/v2/pos', $payload);

        \Log::info('Criação de POS Mercado Pago -----------------');
        \Log::info($resposta->body());

        if ($resposta->failed()) {
            throw new \Exception('Falha ao criar o POS no Mercado Pago: ' . $resposta->body());
        }

        $dados = $resposta->json();
        $mpPosId = (string) $dados['id'];

        // O formato exato de qr_response deve ser conferido em sandbox antes de ir
        // para produção; cobrimos as chaves mais prováveis da resposta da API.
        $qrData = $dados['qr_response']['qr_code']
            ?? $dados['qr_response']['image']
            ?? null;

        if (empty($qrData)) {
            throw new \Exception('POS criado no Mercado Pago, mas a resposta não trouxe o QR Code (qr_response). Resposta: ' . $resposta->body());
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
