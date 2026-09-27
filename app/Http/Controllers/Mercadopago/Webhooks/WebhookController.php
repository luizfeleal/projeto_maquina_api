<?php

namespace App\Http\Controllers\Mercadopago\Webhooks;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Mercadopago\NotificacaoService;

class WebhookController extends Controller
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response|boolean
     */
    public function processamentoWebhook(Request $request)
    {
        \Log::info('req inicial webhook mercadopago ------------------');
        \Log::info($request);

        $tipoNotificacao = $request->input('type') ?? $request->query('topic');

        if ($tipoNotificacao != 'payment') {
            return response()->json(['message' => 'Evento ignorado'], 200);
        }

        $xSignature = $request->header('x-signature');
        $xRequestId = $request->header('x-request-id');
        // O Mercado Pago envia data.id tanto na query string quanto no corpo JSON
        // (formato aninhado data: { id: ... }); cobrimos as duas formas.
        $dataId = $request->query('data.id')
            ?? $request->query('data_id')
            ?? $request->input('data.id');

        $notificacao = NotificacaoService::coletarDadosNotificacao($xSignature, $xRequestId, $dataId);

        \Log::info('Notificacao webhook mercadopago ------------------');
        \Log::info($notificacao);

        if ($notificacao['http_code'] == 400) {
            return response()->json(['message' => 'Notificação sem data.id'], 400);
        }

        if ($notificacao['http_code'] == 401) {
            return response()->json(['message' => 'Assinatura inválida'], 401);
        }

        if ($notificacao['http_code'] == 502) {
            // Falha transitória ao consultar o pagamento no Mercado Pago: pedimos reenvio.
            return response()->json(['message' => 'Falha ao consultar pagamento'], 502);
        }

        if (empty($notificacao['aprovado'])) {
            // Pagamento não encontrado, pendente, rejeitado, cancelado etc.
            // Confirmamos o recebimento para o MP não reenviar; nada a liberar.
            return response()->json(['message' => 'Pagamento não aprovado, notificação ignorada'], 200);
        }

        $resultado = NotificacaoService::processarPagamentoAprovado($notificacao['resposta']);

        \Log::info('Resultado do processamento do pagamento Mercado Pago----------------------');
        \Log::info($resultado);

        return response()->json([], 200);
    }
}
