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

        // 1ª passada: tenta validar por assinatura (x-signature). Vale pro fluxo
        // dinâmico de Payments API. A documentação oficial do Mercado Pago
        // confirma que notificações do produto "Código QR"/POS (o que usamos
        // hoje, via PosService) NÃO têm assinatura verificável — então é
        // esperado que essa passada não encontre nada para esse caso, e caia
        // no fallback abaixo.
        foreach ($credenciais as $index => $credencial) {
            $dado = DescriptografaCredService::descriptografarCred($credencial);

            try {
                WebhookSignatureValidator::validate($xSignature, $xRequestId, $dataId, $dado['client_id']);
            } catch (InvalidWebhookSignatureException $e) {
                continue;
            }

            \Log::info("Assinatura do Mercado Pago validada com a credencial $index.");

            $consulta = self::consultarPagamento($dataId, $dado['client_secret']);

            if ($consulta['erro'] !== null) {
                return ['http_code' => $consulta['erro'], 'resposta' => null, 'aprovado' => false];
            }

            if (!$consulta['encontrado']) {
                \Log::warning("Pagamento $dataId não encontrado no Mercado Pago mesmo com assinatura válida (credencial $index, notificação de teste?).");
                return ['http_code' => 200, 'resposta' => null, 'aprovado' => false];
            }

            return self::montarRespostaProcessada($consulta['payment']);
        }

        // 2ª passada (fallback sem assinatura): como o Mercado Pago não assina
        // notificações do produto QR/POS, a defesa aqui deixa de ser "a
        // assinatura bateu" e passa a ser "conseguimos buscar esse pagamento de
        // verdade na API do MP com o access token de um cliente nosso". Um
        // payment_id de outra conta (ou forjado) retorna 404 nessa consulta —
        // então isso ainda impede aceitar um pagamento aprovado alheio, só que
        // por um mecanismo diferente (posse do access token em vez de HMAC).
        \Log::warning("Nenhuma credencial validou a assinatura da notificação Mercado Pago (data.id: $dataId) — tentando localizar o pagamento por access token (esperado para o produto QR/POS, que não é assinado).");

        foreach ($credenciais as $index => $credencial) {
            $dado = DescriptografaCredService::descriptografarCred($credencial);
            $consulta = self::consultarPagamento($dataId, $dado['client_secret']);

            if ($consulta['erro'] !== null || !$consulta['encontrado']) {
                // Não achou com essa credencial (ou falhou por motivo transitório
                // só dessa chamada) — tenta a próxima, o dono real ainda pode
                // estar nas credenciais seguintes.
                continue;
            }

            \Log::info("Pagamento $dataId localizado via fallback sem assinatura com a credencial $index.");

            return self::montarRespostaProcessada($consulta['payment']);
        }

        \Log::error("Pagamento $dataId não encontrado em nenhuma credencial Mercado Pago cadastrada (sem assinatura válida e busca direta falhou em todas).");
        return ['http_code' => 401, 'resposta' => null];
    }

    /**
     * Busca o pagamento na API do Mercado Pago com o access token informado.
     * 404 é tratado como "não encontrado" (não é erro) porque, nesse produto,
     * é o próprio mecanismo usado pra descobrir a qual cliente o pagamento
     * pertence — cada access token só enxerga os pagamentos da própria conta.
     */
    private static function consultarPagamento(string $dataId, string $accessToken): array
    {
        MercadoPagoConfig::setAccessToken($accessToken);

        try {
            $payment = (new PaymentClient())->get((int) $dataId);

            return ['encontrado' => true, 'payment' => $payment, 'erro' => null];
        } catch (MPApiException $e) {
            if ($e->getStatusCode() === 404) {
                return ['encontrado' => false, 'payment' => null, 'erro' => null];
            }

            \Log::error("Falha ao consultar pagamento $dataId no Mercado Pago (HTTP {$e->getStatusCode()}): " . $e->getMessage());

            return ['encontrado' => false, 'payment' => null, 'erro' => 502];
        } catch (\Throwable $e) {
            \Log::error("Erro inesperado ao consultar pagamento $dataId no Mercado Pago: " . $e->getMessage());

            return ['encontrado' => false, 'payment' => null, 'erro' => 502];
        }
    }

    /**
     * Monta a resposta padrão (aprovado/ignorado + linhas de extrato) a partir
     * de um pagamento já confirmado via API do Mercado Pago.
     */
    private static function montarRespostaProcessada($payment): array
    {
        \Log::info('----------resultado da coleta de pagamento Mercado Pago--------');
        \Log::info(json_encode($payment));

        if ($payment->status !== self::STATUS_APROVADO) {
            \Log::info("Pagamento {$payment->id} com status '{$payment->status}', ignorado (aguardando aprovação ou não aprovado).");
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
