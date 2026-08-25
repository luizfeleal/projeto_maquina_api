<?php

namespace App\Services\Mercadopago;

use App\Models\CredApiPix;
use App\Services\Efi\DescriptografaCredService;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\InvalidWebhookSignatureException;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Webhook\WebhookSignatureValidator;

class NotificacaoService
{
    /**
     * Status de pagamento do Mercado Pago que representam dinheiro efetivamente
     * recebido. Qualquer outro status (pending, in_process, rejected, cancelled,
     * refunded, charged_back etc.) não deve liberar jogada nem gerar extrato.
     */
    private const STATUS_APROVADO = 'approved';

    public static function coletarDadosNotificacao(?string $xSignature, ?string $xRequestId, ?string $dataId)
    {
        if (empty($dataId)) {
            \Log::error('Notificação do Mercado Pago recebida sem data.id (query ou corpo).');
            return ['http_code' => 400, 'resposta' => null];
        }

        $credenciais = CredApiPix::where('tipo_cred', 'mercadopago')->get()->toArray();

        foreach ($credenciais as $index => $credencial) {
            $dadoCredDescriptografado = DescriptografaCredService::descriptografarCred($credencial);
            $webhookSecret = $dadoCredDescriptografado['client_id'];
            $accessToken = $dadoCredDescriptografado['client_secret'];

            try {
                WebhookSignatureValidator::validate($xSignature, $xRequestId, $dataId, $webhookSecret);
            } catch (InvalidWebhookSignatureException $e) {
                \Log::warning("Assinatura do Mercado Pago não validou com a credencial $index.");
                continue;
            }

            \Log::info("Assinatura do Mercado Pago validada com a credencial $index.");

            MercadoPagoConfig::setAccessToken($accessToken);

            try {
                $payment = (new PaymentClient())->get((int) $dataId);
            } catch (MPApiException $e) {
                $statusCode = $e->getStatusCode();

                // 404: pagamento não existe (ex.: notificação de teste do painel MP).
                // Não adianta o MP reenviar, então confirmamos o recebimento.
                if ($statusCode === 404) {
                    \Log::warning("Pagamento $dataId não encontrado no Mercado Pago (notificação de teste?).");
                    return ['http_code' => 200, 'resposta' => null, 'aprovado' => false];
                }

                // Falha transitória (rate limit, 5xx, etc.): pedimos para o MP reenviar.
                \Log::error("Falha ao consultar pagamento $dataId no Mercado Pago (HTTP $statusCode): " . $e->getMessage());
                return ['http_code' => 502, 'resposta' => null, 'aprovado' => false];
            } catch (\Throwable $e) {
                \Log::error("Erro inesperado ao consultar pagamento $dataId no Mercado Pago: " . $e->getMessage());
                return ['http_code' => 502, 'resposta' => null, 'aprovado' => false];
            }

            \Log::info('----------resultado da coleta de pagamento Mercado Pago--------');
            \Log::info(json_encode($payment));

            if ($payment->status !== self::STATUS_APROVADO) {
                \Log::info("Pagamento $dataId com status '{$payment->status}', ignorado (aguardando aprovação ou não aprovado).");
                return ['http_code' => 200, 'resposta' => null, 'aprovado' => false];
            }

            $codigo_transacao = (string) $payment->id;
            $valor_transacao = $payment->transaction_amount;
            $valor_taxa = self::calcularTaxa($payment, $valor_transacao);
            $device_info = $payment->pos_id;

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

        \Log::error("Nenhuma credencial do Mercado Pago validou a assinatura da notificação (data.id: $dataId).");
        return ['http_code' => 401, 'resposta' => null];
    }

    /**
     * Taxa cobrada pelo Mercado Pago. transaction_details.net_received_amount só é
     * confiável após a liquidação do pagamento, então preferimos a soma de
     * fee_details (mais precisa e disponível já na aprovação).
     */
    private static function calcularTaxa($payment, $valor_transacao): float
    {
        $feeDetails = $payment->fee_details ?? null;

        if (!empty($feeDetails)) {
            $totalTaxas = 0;
            foreach ($feeDetails as $fee) {
                $totalTaxas += $fee->amount ?? 0;
            }

            if ($totalTaxas > 0) {
                return (float) $totalTaxas;
            }
        }

        $valorLiquido = $payment->transaction_details->net_received_amount ?? null;

        if (!empty($valorLiquido)) {
            return (float) ($valor_transacao - $valorLiquido);
        }

        return 0.0;
    }
}
