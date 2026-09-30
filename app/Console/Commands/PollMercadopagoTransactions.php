<?php

namespace App\Console\Commands;

use App\Models\CredApiPix;
use App\Models\ExtratoMaquina;
use App\Services\Efi\DescriptografaCredService;
use App\Services\Mercadopago\NotificacaoService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Reconciliação dos pagamentos do Mercado Pago: varre os pagamentos aprovados
 * de cada cliente via /v1/payments/search e processa qualquer um que o
 * webhook não tenha entregue.
 *
 * Não é só um fallback: o QR estático das máquinas é criado em modo
 * `standalone` (valor livre — ver App\Services\Mercadopago\PosService), que
 * não tem order/PDV integrado via API e por isso não garante notificação via
 * webhook (App\Http\Controllers\Mercadopago\Webhooks\WebhookController). Para
 * esses pagamentos, este polling é o caminho que garante a confirmação, não
 * um mecanismo temporário — se algum dia todas as máquinas passarem a operar
 * em modo integrado (`pdv`/orders), aí sim ele volta a ser só reconciliação.
 *
 * Reaproveita a mesma lógica de negócio do webhook (NotificacaoService::
 * processarPagamentoAprovado), então idempotência, liberação de jogada e
 * lançamento de extrato são idênticos nos dois caminhos.
 */
class PollMercadopagoTransactions extends Command
{
    protected $signature = 'mercadopago:poll-transactions';

    protected $description = 'Busca e processa pagamentos aprovados do Mercado Pago via polling (garante a confirmação de pagamentos em QRs standalone, que o webhook pode não entregar)';

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

        $fim = Carbon::now();
        $inicio = $credencial->mp_ultima_consulta
            ? Carbon::parse($credencial->mp_ultima_consulta)->subMinutes(self::MARGEM_SOBREPOSICAO_MINUTOS)
            : Carbon::now()->subMinutes(self::JANELA_INICIAL_MINUTOS);

        // Busca via HTTP cru (não via PaymentClient::search do SDK): o SDK
        // desserializa a resposta em objetos tipados (Resources\Payment\
        // PointOfInteraction) que não mapeiam o campo `device` — o
        // serial_number da Point some silenciosamente ao converter o objeto
        // de volta pra array. Chamando a API direto, preservamos a resposta
        // crua (mesmo caminho que NotificacaoService::consultarPagamento já
        // usa pro webhook) e point_of_interaction.device.serial_number chega
        // intacto até montarDadosTransacao.
        try {
            $resposta = Http::withToken($accessToken)
                ->get('https://api.mercadopago.com/v1/payments/search', [
                    'range' => 'date_created',
                    // A API do Mercado Pago é estrita com o formato de data desses
                    // filtros: exige milissegundos (.v). Sem isso, /v1/payments/search
                    // responde 400 com uma mensagem genérica de erro.
                    'begin_date' => $inicio->format('Y-m-d\TH:i:s.vP'),
                    'end_date' => $fim->format('Y-m-d\TH:i:s.vP'),
                    'sort' => 'date_approved',
                    'criteria' => 'desc',
                    'limit' => 50,
                    'offset' => 0,
                ]);
        } catch (\Throwable $e) {
            throw new \Exception("Falha de conexão ao buscar pagamentos no Mercado Pago: {$e->getMessage()}");
        }

        if (!$resposta->successful()) {
            throw new \Exception("Falha ao buscar pagamentos no Mercado Pago (HTTP {$resposta->status()}): {$resposta->body()}");
        }

        $resultados = $resposta->json('results') ?? [];

        $processados = 0;
        $totalEncontrado = count($resultados);

        foreach ($resultados as $payment) {
            if (($payment['status'] ?? null) !== 'approved') {
                continue;
            }

            // Checagem rápida antes de montar os dados: evita trabalho
            // desnecessário para pagamentos já lançados (pelo webhook ou por
            // uma execução anterior deste polling).
            if (ExtratoMaquina::where('id_end_to_end', (string) $payment['id'])->exists()) {
                continue;
            }

            $posId = $payment['pos_id'] ?? null;
            $serialNumber = $payment['point_of_interaction']['device']['serial_number'] ?? null;
            \Log::info("[Polling Mercado Pago] Pagamento aprovado recebido: id={$payment['id']}, credencial={$credencial->id_cred_api_pix}, valor={$payment['transaction_amount']}, pos_id={$posId}, serial_number={$serialNumber}");

            $dadoTransacao = NotificacaoService::montarDadosTransacao($payment);
            $resultadoProcessamento = NotificacaoService::processarPagamentoAprovado($dadoTransacao);
            $processados++;

            \Log::info("[Polling Mercado Pago] Resultado do processamento do pagamento {$payment['id']} (credencial {$credencial->id_cred_api_pix}): " . json_encode($resultadoProcessamento));
        }

        // Log de confirmação sempre presente, mesmo sem nada novo pra
        // processar: deixa rastro de que a consulta rodou (e não falhou
        // silenciosamente antes de chegar aqui), sem precisar inferir isso
        // pela ausência de outras linhas de log.
        \Log::info("[Polling Mercado Pago] Credencial {$credencial->id_cred_api_pix}: janela {$inicio->toIso8601String()} a {$fim->toIso8601String()}, {$totalEncontrado} pagamento(s) retornado(s) pela API, {$processados} novo(s) processado(s).");

        $credencial->update(['mp_ultima_consulta' => $fim]);
    }
}
