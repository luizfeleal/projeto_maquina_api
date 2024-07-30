<?php

namespace App\Http\Controllers\Efi\Webhooks;

use App\Models\AcessosTela;
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

        try {

            DB::beginTransaction();

            $webhook = array (
                'pix' => 
                array (
                  0 => 
                  array (
                    'endToEndId' => 'E18236120202407232014s13d4641028',
                    'txid' => '6699d72e658d712345678',
                    'chave' => '5ee22d18-d5a4-4d02-be4b-9adb456409f8',
                    'valor' => '0.01',
                    'horario' => '2024-07-23T20:15:42.000Z',
                    'gnExtras' => 
                    array (
                      'tarifa' => '0.01',
                      'pagador' => 
                      array (
                        'nome' => 'LUIZ FELIPE LEAL DE ARAUJO',
                        'cpf' => '***.986.847-**',
                        'codigoBanco' => '18236120',
                      ),
                    ),
                  ),
                ),
                'ignorar' => '/pix',
            );

            $idE2E = $webhook['pix'][0]['endToEndId'];

            $txid = $webhook['pix'][0]['txid'];
            $valor = intval($webhook['pix'][0]['valor']);
            $tarifa = $webhook['pix'][0]['gnExtras']['tarifa'];

            $id_placa = intval(substr($txid, -8));

            //Tentar liberar jogada

            //Se der certo, registrar o sucesso

            //Se der erro, processar o estorno

            $tentativas = 0;
            $maxTentativas = env('TENTATIVAS_PERSISTENCIA_JOGADA');
            $resposta = null;

                do {
                    $resposta = JogadasService::liberarJogada($id_placa, $valor);
                    $tentativas++;
                    
                    // Verifica se o http_code é 200
                    if ($resposta['http_code'] == 200) {
                        break;
                    }
                    
                    // Se atingir o número máximo de tentativas, exibe uma mensagem de erro ou realiza outra ação
                    if ($tentativas >= $maxTentativas) {
                        // Lidar com falha após tentativas
    
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
                        "extrato_operacao" => "D",
                        "extrato_operacao_tipo" => "Taxa",
                        "extrato_operacao_valor" => $tarifa,
                        "extrato_operacao_status" => 1,
                    ]
                ];
                
                $extrato = ExtratoMaquina::insert($dadosExtrato);
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
