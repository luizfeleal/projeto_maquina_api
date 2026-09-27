<?php

namespace App\Console\Commands;

use App\Models\CredApiPix;
use App\Models\ExtratoMaquina;
use App\Services\Efi\DescriptografaCredService;
use App\Services\Mercadopago\NotificacaoService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Net\MPSearchRequest;

/**
 * Fallback/reconciliação para o webhook do Mercado Pago: varre os pagamentos
 * aprovados de cada cliente via /v1/payments/search e processa qualquer um que
 * o webhook não tenha entregue. É um mecanismo temporário — o canal principal
 * continua sendo o webhook (App\Http\Controllers\Mercadopago\Webhooks\WebhookController);
 * este comando existe só pra cobrir falhas de entrega.
 *
 * Reaproveita a mesma lógica de negócio do webhook (NotificacaoService::
 * processarPagamentoAprovado), então idempotência, liberação de jogada e
 * lançamento de extrato são idênticos nos dois caminhos.
 */
class PollMercadopagoTransactions extends Command
{
    protected $signature = 'mercadopago:poll-transactions';

    protected $description = 'Busca pagamentos aprovados do Mercado Pago via polling (fallback de reconciliação para quando o webhook falha)';

    /**
     * Margem de sobreposição na janela de busca: reconsulta um pouco antes do
     * checkpoint salvo pra cobrir pagamentos que ainda não tinham sido
     * aprovados (status pending/in_process) na última consulta.
     */
    private const MARGEM_SOBREPOSICAO_MINUTOS = 2;

    private const JANELA_INICIAL_MINUTOS = 5;

    public function handle(): int
    {
        $credenciais = CredApiPix::where('tipo_cred', 'mercadopago')->get();

        if ($credenciais->isEmpty()) {
            $this->info('Nenhuma credencial do Mercado Pago cadastrada.');
            return Command::SUCCESS;
        }

        foreach ($credenciais as $credencial) {
            try {
                $this->consultarCredencial($credencial);
            } catch (\Throwable $e) {
                \Log::error("Erro ao consultar transações Mercado Pago (polling) para a credencial {$credencial->id_cred_api_pix}: " . $e->getMessage());
                continue;
            }

            // Pausa curta entre clientes para não disparar todas as requisições
            // ao Mercado Pago no mesmo instante (evita rajada e reduz risco de
            // 429 usage_quota_exceeded quando a base de clientes crescer).
            usleep(200000);
        }

        $this->info('Polling de transações Mercado Pago finalizado.');
        return Command::SUCCESS;
    }

    private function consultarCredencial(CredApiPix $credencial): void
    {
        $dadosCredDescriptografado = DescriptografaCredService::descriptografarCred($credencial->toArray());
        $accessToken = $dadosCredDescriptografado['client_secret'];

        MercadoPagoConfig::setAccessToken($accessToken);

        $fim = Carbon::now();
        $inicio = $credencial->mp_ultima_consulta
            ? Carbon::parse($credencial->mp_ultima_consulta)->subMinutes(self::MARGEM_SOBREPOSICAO_MINUTOS)
            : Carbon::now()->subMinutes(self::JANELA_INICIAL_MINUTOS);

        $searchRequest = new MPSearchRequest(50, 0, [
            'range' => 'date_created',
            // A API do Mercado Pago é estrita com o formato de data desses
            // filtros: exige milissegundos (.v). Sem isso, /v1/payments/search
            // responde 400 e o SDK só expõe a mensagem genérica "Api error.
            // Check response for details" (ver MPApiException abaixo).
            'begin_date' => $inicio->format('Y-m-d\TH:i:s.vP'),
            'end_date' => $fim->format('Y-m-d\TH:i:s.vP'),
            'sort' => 'date_approved',
            'criteria' => 'desc',
        ]);

        try {
            $resultado = (new PaymentClient())->search($searchRequest);
        } catch (MPApiException $e) {
            // A mensagem padrão do SDK ("Api error. Check response for details")
            // não diz nada; o corpo de verdade (motivo real, ex.: token inválido/
            // expirado, filtro malformado) só vem em getApiResponse()->getContent()
            // (mesmo problema já resolvido em ContaService::obterUserId).
            $conteudo = json_encode($e->getApiResponse()->getContent());
            throw new \Exception("Falha ao buscar pagamentos no Mercado Pago (HTTP {$e->getStatusCode()}): {$conteudo}");
        }

        foreach ($resultado->results ?? [] as $payment) {
            if ($payment->status !== 'approved') {
                continue;
            }

            // Checagem rápida antes de montar os dados: evita trabalho
            // desnecessário para pagamentos já lançados (pelo webhook ou por
            // uma execução anterior deste polling).
            if (ExtratoMaquina::where('id_end_to_end', (string) $payment->id)->exists()) {
                continue;
            }

            \Log::info("[Polling Mercado Pago] Pagamento aprovado recebido: id={$payment->id}, credencial={$credencial->id_cred_api_pix}, valor={$payment->transaction_amount}, pos_id={$payment->pos_id}");

            // NotificacaoService::montarDadosTransacao espera um array (mesmo
            // formato de Http::get(...)->json(), usado pelo webhook), então
            // convertemos o objeto tipado retornado pelo SDK antes de chamar.
            $paymentArray = json_decode(json_encode($payment), true);

            $dadoTransacao = NotificacaoService::montarDadosTransacao($paymentArray);
            $resultadoProcessamento = NotificacaoService::processarPagamentoAprovado($dadoTransacao);

            \Log::info("[Polling Mercado Pago] Resultado do processamento do pagamento {$payment->id} (credencial {$credencial->id_cred_api_pix}): " . json_encode($resultadoProcessamento));
        }

        $credencial->update(['mp_ultima_consulta' => $fim]);
    }
}
