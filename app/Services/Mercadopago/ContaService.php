<?php

namespace App\Services\Mercadopago;

use MercadoPago\Client\User\UserClient;
use MercadoPago\MercadoPagoConfig;

class ContaService
{
    /**
     * Resolve o user_id numérico do vendedor no Mercado Pago a partir do access
     * token, necessário para criar a Loja (POST /users/{user_id}/stores).
     */
    public static function obterUserId(string $accessToken): string
    {
        MercadoPagoConfig::setAccessToken($accessToken);

        $usuario = (new UserClient())->get();

        return (string) $usuario->id;
    }
}
