<?php

namespace App\Http\Controllers\Efi\Webhooks;

use App\Models\AcessosTela;
use App\Models\CredApiPix;
use App\Models\ClienteLocal;
use App\Models\Logs;
use App\Models\ExtratoMaquina;
use App\Services\Efi\GestaoPixService;
use App\Services\Efi\AuthService as AuthEfiService;
use App\Services\Efi\DescriptografaCredService;
use App\Services\Hardware\JogadasService;
use App\Services\Hardware\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;



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

            DB::beginTransaction();
	    if($request['evento'] && $request['evento'] == "teste_webhook"){
                return response()->json(['message' => "URL OK"], 200);
            }

            $webhook = $request;
            $idE2E = $webhook['pix'][0]['endToEndId'];

            $txid = $webhook['pix'][0]['txid'];
            $valor = intval($webhook['pix'][0]['valor']);
            $tarifa = 0;
            if (isset($webhook['pix'][0]['gnExtras']['tarifa'])) {
                $tarifa = $webhook['pix'][0]['gnExtras']['tarifa'];
            }

            $id_placa_ultimos_quatro_digitos = intval(substr($txid, -4));

            $id_placa_result = DB::table('maquinas')
            ->whereRaw('RIGHT(id_placa, 4) = ?', [$id_placa_ultimos_quatro_digitos])
            ->get()->toArray();

            $id_placa = $id_placa_result[0]->id_placa;
            $id_maquina = $id_placa_result[0]->id_maquina;

            $cliente_local = ClienteLocal::where('id_local', $id_placa_result[0]->id_local)->where('cliente_local_principal', 1)->get()->toArray();

            $id_cliente = $cliente_local[0]['id_cliente'];
            $cliente_credencial = CredApiPix::where('id_cliente', $id_cliente)->get()->toArray();


            //Tentar liberar jogada

            //Se der certo, registrar o sucesso

            //Se der erro, processar o estorno

            $tentativas = 0;
            $maxTentativas = env('TENTATIVAS_PERSISTENCIA_JOGADA');
            $resposta = null;
            $gerarDevolucao = false;

                do {

                    $token = AuthService::coletarToken();
                    $resposta = JogadasService::liberarJogada($id_placa, $valor, $idE2E, $token);
                    \Log::info('hardware----------------------');
                    \Log::info($resposta);
                    $tentativas++;
                    
                    // Verifica se o http_code é 200
                    if ($resposta['http_code'] == 200) {
                        break;
                    }
                    
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

                $gerarDevolucao == true;
                if($gerarDevolucao == true){
                    $id_cliente = $request['id_cliente'];
                    $cred_api_pix = $cliente_credencial[0];

                    $dadoCredDescriptografado = DescriptografaCredService::descriptografarCred($cred_api_pix);
                    $token = AuthEfiService::coletarToken($dadoCredDescriptografado);
                    $id_transacao = ExtratoMaquina::where('id_maquina', $id_maquina)->where('id_end_to_end', $idE2E)->get()->toArray()[0]['id_extrato_maquina'];
                    $devolucao = GestaoPixService::solicitarDevolucao($token, $idE2E, $id_transacao, $valor, $dadoCredDescriptografado['caminho_certificado']);

                    //return isset($result['status']) && $result['status'] == "EM_PROCESSAMENTO";
                    if(isset($result['status']) && $result['status'] == "EM_PROCESSAMENTO"){
                        $dadosExtrato = [
                                [
                                    "id_maquina" => $id_maquina,
                                    "id_end_to_end" => $idE2E,
                                    "extrato_operacao" => "D",
                                    "extrato_operacao_tipo" => "Estorno",
                                    "extrato_operacao_valor" => $valor,
                                    "extrato_operacao_status" => 1,
                                ]
                            ];
                            $extrato = ExtratoMaquina::insert($dadosExtrato);
                            \Log::error("Cadastro devolucao no extrato ---------------------");
                            \Log::error($extrato);
                            \Log::error("------------------------------------");

                        
                    }else{

                        Logs::create([
                            "descricao" => "Erro ao tentar efetuar a devolução do PIX. Informações: [id_maquina: $id_maquina]",
                            "status" => "erro",
                            "acao" => "Devolução Pix",
                            "id_maquina" => $id_maquina
                        ]);
                        \Log::error($devolucao);
                    }

                }
                //retornar codigo 200

                DB::commit();
                return response()->json([''], 200);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erro de validação: ' . $e->getMessage()], 422);
        } catch (Exception $e) {
            DB::rollBack();
            //REGISTRAR LOGS
            return response()->json(['message' => 'Houve um erro ao tentar registrar o extrato.'], 500);
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
