<?php

namespace App\Http\Controllers\Efi\Webhooks;

use App\Models\AcessosTela;
use App\Models\CredApiPix;
use App\Models\ClienteLocal;
use App\Models\Logs;
use App\Models\ExtratoMaquina;
use App\Mail\AlertaDevolucaoPixBloqueada;
use App\Services\Efi\GestaoPixService;
use App\Services\Efi\AuthService as AuthEfiService;
use App\Services\Efi\DescriptografaCredService;
use App\Services\Efi\LimiteTransacionalGuardService;
use App\Services\Hardware\JogadasService;
use App\Services\Hardware\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Validation\ValidationException;
use Exception;



class WebhookController extends Controller
{




    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function processamentoRequisicaoInicial(Request $request)
    {

        \Log::info('webhook ----------------------');
        \Log::info($request);
        try {

            $maquina_bloqueada = false;

            DB::beginTransaction();
            if ($request['evento'] && $request['evento'] == "teste_webhook") {
                return response()->json(['message' => "URL OK"], 200);
            }


            $webhook = $request;


            $idE2E = $webhook['pix'][0]['endToEndId'];

            $txid = $webhook['pix'][0]['txid'];
            $valor = floatval($webhook['pix'][0]['valor']);
            $tarifa = 0;
            if (isset($webhook['pix'][0]['gnExtras']['tarifa'])) {
                $tarifa = $webhook['pix'][0]['gnExtras']['tarifa'];
            }

            $id_placa_primeiros_dezoito_digitos = substr($txid, 0, 18);

            \Log::info('------id_placa_extraído----');
            \Log::info( $id_placa_primeiros_dezoito_digitos);

            $id_placa_result = DB::table('maquinas')
                ->where('id_placa', [$id_placa_primeiros_dezoito_digitos])->where('deleted_at', NULL)
                ->get()->toArray();

                \Log::info('------Placa encontrada----');
            \Log::info( $id_placa_result);

            $id_placa = $id_placa_result[0]->id_placa;
            $id_maquina = $id_placa_result[0]->id_maquina;

            if($id_placa_result[0]->bloqueio_jogada_efi == 1){
                $maquina_bloqueada = true;
                Logs::create([
                    "descricao" => "Erro ao tentar liberar jogada. A máquina possui um bloqueio de liberação de jogadas por pix: [id_placa: $id_placa]",
                    "status" => "erro",
                    "acao" => "Liberar jogada",
                    "id_maquina" => $id_maquina
                ]);
            }

            $cliente_local = ClienteLocal::where('id_local', $id_placa_result[0]->id_local)->where('cliente_local_principal', 1)->get()->toArray();

            $id_cliente = $cliente_local[0]['id_cliente'];
            $cliente_credencial = CredApiPix::where('id_cliente', $id_cliente)->get()->toArray();

            if (isset($webhook['pix'][0]['devolucoes']) && !empty($webhook['pix'][0]['devolucoes'])) {
                $statusDevolucao = $webhook['pix'][0]['devolucoes'][0]['status'] ?? null;

                if ($statusDevolucao == "DEVOLVIDO") {
                    $extrato = ExtratoMaquina::create([
                        'id_maquina' => $id_maquina,
                        'id_end_to_end' => $idE2E,
                        'extrato_operacao' => 'D',
                        'extrato_operacao_tipo' => 'Estorno',
                        'extrato_operacao_valor' => $valor,
                        'extrato_operacao_status' => 1,
                    ]);
                    DB::commit();
                    \Log::error("Cadastro devolucao no extrato ---------------------");
                    \Log::error($extrato);
                    \Log::error("------------------------------------");
                    return response()->json(['message' => 'Devolução registrada'], 200);
                }

                if ($statusDevolucao == "EM_PROCESSAMENTO") {
                    DB::commit();
                    return response()->json(['message' => 'Devolução em processamento'], 200);
                }

                // Rejeitada (ex.: NAO_REALIZADO por limite transacional insuficiente). Antes
                // esse caso era ignorado (return silencioso): nada era registrado e nada
                // impedia uma nova tentativa na próxima vez que esse fluxo fosse acionado.
                // Agora: status definitivo de falha registrado, equipe alertada, e nenhuma
                // nova tentativa automática é feita a partir daqui (Parecer Técnico Efí).
                $motivo = $webhook['pix'][0]['devolucoes'][0]['motivo']
                    ?? $webhook['pix'][0]['devolucoes'][0]['descricao']
                    ?? 'Devolução rejeitada pela Efí (motivo não informado no webhook)';

                LimiteTransacionalGuardService::registrarBloqueio(
                    $idE2E,
                    $txid,
                    $valor,
                    $motivo,
                    $webhook->all(),
                    $id_maquina
                );

                self::alertarEquipeOperacional($idE2E, $txid, $valor, $motivo, $id_maquina);

                DB::commit();
                return response()->json(['message' => 'Devolução rejeitada registrada'], 200);
            }

            // Idempotência: a Efí pode reentregar o mesmo webhook (timeout de resposta,
            // falha de rede etc.). Sem essa checagem, cada reentrega duplicava o crédito
            // no extrato e repetia a liberação de jogada / solicitação de devolução — a
            // causa raiz do ciclo contínuo de retentativas de Pix apontado no Parecer
            // Técnico Efí. Mesma proteção já usada em Pagbank\Webhooks\WebhookController.
            if (ExtratoMaquina::where('id_end_to_end', $idE2E)->exists()) {
                \Log::info("Notificação duplicada da Efí para id_end_to_end=$idE2E, já processada anteriormente.");
                DB::commit();
                return response()->json(['message' => 'Notificação duplicada, já processada'], 200);
            }

            // Lock por transação: evita que duas entregas concorrentes do mesmo webhook
            // passem pela checagem de idempotência acima ao mesmo tempo.
            $lock = Cache::lock("webhook:efi:transacao:$idE2E", 30);

            try {
                $lock->block(10);
            } catch (LockTimeoutException $e) {
                \Log::info("Timeout aguardando lock da transação $idE2E; outra requisição já está processando esse webhook.");
                DB::commit();
                return response()->json(['message' => 'Processamento em andamento'], 200);
            }

            try {
                if (ExtratoMaquina::where('id_end_to_end', $idE2E)->exists()) {
                    \Log::info("Notificação duplicada da Efí para id_end_to_end=$idE2E (detectada após lock), já processada anteriormente.");
                    DB::commit();
                    return response()->json(['message' => 'Notificação duplicada, já processada'], 200);
                }

                //Tentar liberar jogada

                //Se der certo, registrar o sucesso

                //Se der erro, processar o estorno

                $tentativas = 0;
                $maxTentativas = env('TENTATIVAS_PERSISTENCIA_JOGADA');
                $resposta = null;
                $gerarDevolucao = false;

                do {

                    if($maquina_bloqueada == true){
                        $gerarDevolucao = true;
                        break;
                    }

                    $token = AuthService::coletarToken();
                    \Log::info('--------------Aqui está o id da placa encontrado-------------');
                    \Log::info($id_placa);
                    $resposta = JogadasService::liberarJogada($id_placa, $valor, substr($idE2E, 0, 25), $token);
                    \Log::info('-------------tenativa-liberar-joagada-hardware-hardware----------------');
                    \Log::info($resposta);
                    $tentativas++;

                    // Verifica se o http_code é 200
                    if ($resposta['http_code'] == 200) {
                        break;
                    }

                    \Log::info('------------- $tentativas ------------');
                    \Log::info($tentativas);
                    \Log::info('------------- $maxTentativas ------------');
                    \Log::info($maxTentativas);
                    // Se atingir o número máximo de tentativas, exibe uma mensagem de erro ou realiza outra ação
                    if ($tentativas >= $maxTentativas) {
                        $gerarDevolucao = true;


                        Logs::create([
                            "descricao" => "Erro ao tentar liberar jogadas, número de tentativas de comunicação com a máquina foi excedido.",
                            "status" => "erro",
                            "acao" => "liberar jogada",
                            "id_maquina" => $id_maquina
                        ]);

                        //Fazer o estorno aqui
                        break;
                    }
                } while ($resposta['http_code'] != 200);
                //Salvar a transacao

                $dadosExtrato = [
                    [
                        "id_maquina" => $id_maquina,
                        "id_end_to_end" => $idE2E,
                        "extrato_operacao" => "C",
                        "extrato_operacao_tipo" => "PIX",
                        "extrato_operacao_valor" => $valor,
                        "extrato_operacao_status" => 1,
                    ],
                    [
                        "id_maquina" => $id_maquina,
                        "id_end_to_end" => $idE2E,
                        "extrato_operacao" => "D",
                        "extrato_operacao_tipo" => "Taxa",
                        "extrato_operacao_valor" => $tarifa,
                        "extrato_operacao_status" => 1,
                    ]
                ];

                $extrato = ExtratoMaquina::insert($dadosExtrato);

                if ($gerarDevolucao == true) {
                    // Trava: se essa transação já está bloqueada por limite insuficiente e
                    // continua dentro da mesma janela (mesmo dia, sem liberação manual),
                    // não tenta de novo — só registra que segue bloqueada.
                    if (LimiteTransacionalGuardService::estaBloqueada($idE2E)) {
                        \Log::warning("Devolução Pix para id_end_to_end=$idE2E permanece bloqueada por limite insuficiente; nenhuma nova tentativa automática foi feita.");
                    } else {
                        $id_cliente = $request['id_cliente'];
                        $cred_api_pix = $cliente_credencial[0];

                        $dadoCredDescriptografado = DescriptografaCredService::descriptografarCred($cred_api_pix);
                        $token = AuthEfiService::coletarToken($dadoCredDescriptografado);
                        $id_transacao = ExtratoMaquina::where('id_maquina', $id_maquina)->where('id_end_to_end', $idE2E)->get()->toArray()[0]['id_extrato_maquina'];
                        $devolucao = GestaoPixService::solicitarDevolucao($token, $idE2E, $id_transacao, $valor, $dadoCredDescriptografado['caminho_certificado']);

                        \Log::info('----------- Devolução do PIX -------------');
                        \Log::info($devolucao);

                        if ($devolucao['rejeitado_por_limite'] ?? false) {
                            $motivo = $devolucao['result']['mensagem'] ?? 'Limite transacional insuficiente';

                            LimiteTransacionalGuardService::registrarBloqueio(
                                $idE2E,
                                $txid,
                                $valor,
                                $motivo,
                                $devolucao['result'] ?? [],
                                $id_maquina
                            );

                            self::alertarEquipeOperacional($idE2E, $txid, $valor, $motivo, $id_maquina);
                        } elseif (($devolucao['http_code'] ?? 0) >= 400) {
                            \Log::error('Erro ao solicitar devolução Pix (motivo não relacionado a limite transacional)', ['resposta' => $devolucao]);
                        } else {
                            LimiteTransacionalGuardService::registrarSucesso($idE2E, $id_maquina);
                        }
                    }
                }
                //retornar codigo 200

                DB::commit();
                return response()->json([''], 200);
            } finally {
                $lock->release();
            }
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erro de validação: ' . $e->getMessage()], 422);
        } catch (Exception $e) {
            DB::rollBack();
            \Log::error('Erro ao processar webhook Efí Pix: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Houve um erro ao tentar registrar o extrato.'], 500);
        }
    }

    /**
     * Notifica a equipe operacional por e-mail quando uma devolução Pix é
     * bloqueada por limite transacional insuficiente. Falha de envio nunca
     * deve derrubar o processamento do webhook — só é registrada em log.
     */
    private static function alertarEquipeOperacional(string $idE2E, ?string $txid, float $valor, string $motivo, ?int $idMaquina): void
    {
        $destinatarios = array_values(array_filter(array_map('trim', explode(',', (string) config('services.alerta_pix.email')))));

        if (empty($destinatarios)) {
            \Log::warning('ALERTA_PIX_EMAIL não configurado; alerta de devolução Pix bloqueada não foi enviado por e-mail.');
            return;
        }

        try {
            Mail::to($destinatarios)->send(new AlertaDevolucaoPixBloqueada($idE2E, $txid, $valor, $motivo, $idMaquina));
        } catch (Exception $e) {
            \Log::error('Falha ao enviar e-mail de alerta de devolução Pix bloqueada: ' . $e->getMessage());
        }
    }

    public function setarUrlWebhook(Request $request)
    {
        $token = AuthService::coletarToken();

        $arquivo = "Certificados/Naise/homologacaoTeste_cert.pem";

        // Obtém o caminho absoluto do arquivo de certificado
        $certificado = Storage::disk('local')->path($arquivo);

        // Verifique se o arquivo realmente existe
        if (!file_exists($certificado)) {
            throw new \Exception("O arquivo de certificado não foi encontrado: " . $caminhoCertificado);
        }

        $url = env('URL_EFI') . "/v2/webhook/:chave";

        // Inicializa a sessão cURL
        $ch = curl_init($url);

        $data = array(
            "webhookUrl" => $tipoCob
        );

        $data_string = json_encode($data);
        curl_setopt_array(
            $ch,
            array(
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $data_string,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json', 'Authorization: Bearer ' . $token],
                CURLOPT_SSL_VERIFYPEER => true, // Verifica o certificado do servidor
                CURLOPT_SSL_VERIFYHOST => 2, // Verifica o host do certificado
                CURLOPT_SSLCERT => $certificado // Define o certificado a ser usado
            )
        );

        $result = curl_exec($ch);

        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);


        if (curl_errno($ch)) {
            throw new \Exception("Erro durante a requisição cURL: " . curl_error($ch));
        }
        curl_close($ch);

        $resposta = json_decode($result);

        return $resposta;
    }
}
