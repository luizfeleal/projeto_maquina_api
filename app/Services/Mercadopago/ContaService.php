<?php

namespace App\Services\Mercadopago;

use MercadoPago\Client\User\UserClient;
use MercadoPago\Exceptions\MPApiException;
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

        try {
            $usuario = (new UserClient())->get();
        } catch (MPApiException $e) {
            // A mensagem padrão do SDK ("Api error. Check response for details")
            // não diz nada; o corpo de verdade (motivo real, ex.: token inválido/
            // expirado) só vem em getApiResponse()->getContent().
            $conteudo = json_encode($e->getApiResponse()->getContent());
            throw new \Exception("Falha ao consultar usuário no Mercado Pago (HTTP {$e->getStatusCode()}): {$conteudo}");
        }

        return (string) $usuario->id;
    }
}
