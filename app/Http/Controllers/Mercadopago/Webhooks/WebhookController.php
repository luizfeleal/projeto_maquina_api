<?php

namespace App\Http\Controllers\Mercadopago\Webhooks;

use App\Models\Maquinas;
use App\Models\MaquinaCartao;
use App\Models\Logs;
use App\Models\ExtratoMaquina;
use App\Services\Hardware\JogadasService;
use App\Services\Hardware\AuthService;
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

        $codigoTransacao = $notificacao['resposta']['credito']['id_end_to_end'];

        if (ExtratoMaquina::where('id_end_to_end', $codigoTransacao)->exists()) {
            \Log::info("Notificação duplicada do Mercado Pago para o pagamento $codigoTransacao, já processada anteriormente.");
            return response()->json(['message' => 'Notificação já processada'], 200);
        }

        $liberarJogada = true;
        $device_numero = $notificacao['resposta']['device'];

        \Log::info('---------Numero device --------');
        \Log::info($device_numero);

        $device = MaquinaCartao::where('device', $device_numero)->where('status', 1)->get()->toArray();
        \Log::info('---------Device Encontrado--------');
        \Log::info($device);
        if (empty($device)) {
            Logs::create([
                "descricao" => "Erro ao tentar liberar uma jogada, device de número: $device_numero não foi encontrado no sistema.",
                "status" => "erro",
                "acao" => "liberar jogada",
                "id_maquina" => 0
            ]);
            return response()->json(['message' => 'Device não encontrado'], 200);
        }
        $id_maquina = $device[0]['id_maquina'];

        \Log::info('---------ID Maquina pelo device--------');
        \Log::info($id_maquina);

        $notificacao['resposta']['credito']['id_maquina'] = $id_maquina;
        $notificacao['resposta']['debito']['id_maquina'] = $id_maquina;

        $maquina = Maquinas::where('id_maquina', $id_maquina)->get();
        \Log::info('--------Máquina encontrada cartão------');
        \Log::info($maquina);
        if (isset($maquina[0])) {
            if (!empty($maquina) && $maquina[0]['bloqueio_jogada_mercadopago'] == 1) {
                $liberarJogada = false;
                Logs::create([
                    "descricao" => "Erro ao tentar liberar jogadas! A máquina de cartão se encontra como bloqueada para liberar jogadas por maquininha de cartão (Mercado Pago).",
                    "status" => "erro",
                    "acao" => "liberar jogada",
                    "id_maquina" => $id_maquina
                ]);
            }
        } else {
            $liberarJogada = false;
            Logs::create([
                "descricao" => "Erro ao tentar liberar jogadas! Não foi possível encontrar a máquina. Verifique o registro!",
                "status" => "erro",
                "acao" => "liberar jogada",
                "id_maquina" => $id_maquina
            ]);
        }

        // Registramos o extrato assim que decidimos processar a notificação, antes de
        // acionar o hardware: se o Mercado Pago reenviar a mesma notificação (retry),
        // a checagem de idempotência acima passa a barrar o reprocessamento.
        $dadosExtrato = [
            $notificacao['resposta']['credito'],
            $notificacao['resposta']['debito']
        ];
        ExtratoMaquina::insert($dadosExtrato);

        $tentativas = 0;
        $maxTentativas = env('TENTATIVAS_PERSISTENCIA_JOGADA');
        $resposta = null;
        do {
            if ($liberarJogada == true) {
                $valor = $notificacao['resposta']['credito']['extrato_operacao_valor'];
                $idE2E = $notificacao['resposta']['credito']['id_end_to_end'];

                $maquina = Maquinas::find($id_maquina);

                $id_placa = $maquina['id_placa'];

                $token = AuthService::coletarToken();
                $resposta = JogadasService::liberarJogada($id_placa, $valor, $idE2E, $token);
                \Log::info('hardware----------------------');
                \Log::info($resposta);
                $tentativas++;

                if ($resposta['http_code'] == 200) {
                    break;
                }

                if ($tentativas >= $maxTentativas) {
                    Logs::create([
                        "descricao" => "Erro ao tentar liberar jogadas, número de tentativas de comunicação com a máquina foi excedido.",
                        "status" => "erro",
                        "acao" => "liberar jogada",
                        "id_maquina" => $id_maquina
                    ]);

                    //Fazer o estorno aqui
                    break;
                }
            } else {
                break;
            }
        } while ($resposta['http_code'] != 200);

        \Log::info('Liberação de jogada----------------------');
        \Log::info($resposta);

        return response()->json([], 200);
    }
}
