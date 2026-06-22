<?php

namespace App\Services\Efi;

use Efi\EfiPay;
use Efi\Exception\EfiException;
use App\Models\Mensalidade;
use App\Models\Clientes;

class BoletoService
{
    private static function options(): array
    {
        return [
            'clientId'     => env('EFI_BOLETO_CLIENT_ID'),
            'clientSecret' => env('EFI_BOLETO_CLIENT_SECRET'),
            'sandbox'      => (bool) env('EFI_BOLETO_SANDBOX', true),
            'debug'        => false,
            'cache'        => true,
            'timeout'      => 30,
        ];
    }

    public static function criarBoleto(Mensalidade $mensalidade, Clientes $cliente): array
    {
        $valorCentavos = (int) round($mensalidade->valor * 100);

        $cpfCnpj = preg_replace('/\D/', '', $cliente->cliente_cpf_cnpj);
        $ehCnpj   = strlen($cpfCnpj) > 11;

        $customerData = [
            'name' => $cliente->cliente_nome,
        ];

        if ($ehCnpj) {
            $customerData['juridical_person'] = [
                'corporate_name' => $cliente->cliente_nome,
                'cnpj'           => $cpfCnpj,
            ];
        } else {
            $customerData['cpf'] = $cpfCnpj;
        }

        if (!empty($cliente->cliente_email)) {
            $customerData['email'] = $cliente->cliente_email;
        }
        if (!empty($cliente->cliente_celular)) {
            $customerData['phone_number'] = preg_replace('/\D/', '', $cliente->cliente_celular);
        }

        $body = [
            'items' => [
                [
                    'name'   => 'Mensalidade',
                    'amount' => 1,
                    'value'  => $valorCentavos,
                ],
            ],
            'metadata' => [
                'custom_id'        => 'mensalidade_' . $mensalidade->id,
                'notification_url' => env('APP_URL') . '/api/webhook/efi/boleto',
            ],
            'payment' => [
                'banking_billet' => [
                    'expire_at'      => $mensalidade->vencimento->format('Y-m-d'),
                    'customer'       => $customerData,
                    'configurations' => [
                        'fine'     => 200,  // 2% de multa
                        'interest' => 33,   // 0.033% ao dia de juros
                    ],
                ],
            ],
        ];

        $api      = new EfiPay(self::options());
        $response = $api->createOneStepCharge([], $body);

        $chargeId = $response['data']['charge_id']     ?? null;
        $barcode  = $response['data']['barcode']        ?? null;
        $link     = $response['data']['link']           ?? null;
        $pdf      = $response['data']['pdf']['charge']  ?? null;
        $status   = $response['data']['status']         ?? null;

        return compact('chargeId', 'barcode', 'link', 'pdf', 'status', 'response');
    }

    public static function detalharCobranca(string $chargeId): array
    {
        $api    = new EfiPay(self::options());
        $params = ['id' => (int) $chargeId];
        return $api->detailCharge($params);
    }

    public static function cancelarCobranca(string $chargeId): array
    {
        $api    = new EfiPay(self::options());
        $params = ['id' => (int) $chargeId];
        return $api->cancelCharge($params);
    }

    public static function reenviarBoleto(string $chargeId, string $email): array
    {
        $api    = new EfiPay(self::options());
        $params = ['id' => (int) $chargeId];
        $body   = ['email' => $email];
        return $api->sendBilletEmail($params, $body);
    }

    public static function consultarNotificacao(string $token): array
    {
        $api    = new EfiPay(self::options());
        $params = ['token' => $token];
        return $api->getNotification($params);
    }
}
