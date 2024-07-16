<?php

namespace App\Services\Efi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\QrCode;

class AuthService
{


    public static function coletarToken()
    {
        //LogsService::criar(array("id_usuario"=>session()->get('id_usuario'), "tabela"=>"tipo_endereco", "funcao"=>"coletar", "datahora"=>now()));

        $arquivo = "Certificados/Naise/homologacaoTeste_cert.pem";

        // Obtém o caminho absoluto do arquivo de certificado
        $certificado = Storage::disk('local')->path($arquivo);

        // Verifique se o arquivo realmente existe
        if (!file_exists($certificado)) {
            throw new \Exception("O arquivo de certificado não foi encontrado: " . $certificado);
        }

        $cliente_id = "Client_Id_4234c0c63d5e67362cd20f7f2f467f25be4d36b7";
        $client_secret = "Client_Secret_16a1b84181cd7025ef5b8920bb53335dcb9892c1";

        $credenciaisBase64 = base64_encode($cliente_id . ":" . $client_secret);

        $url = env('URL_EFI') . "/oauth/token";

        // Inicializa a sessão cURL
        $ch = curl_init();


        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Verifica o certificado do servidor
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); // Verifica o host do certificado
        curl_setopt($ch, CURLOPT_SSLCERT, $certificado); // Define o certificado a ser usado


        // Adiciona os dados do cliente como autenticação básica
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            "Authorization: Basic " . $credenciaisBase64
            
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'client_credentials',
        ]));

        // Executa a requisição e obtém a resposta
        $resposta = curl_exec($ch);

        if(curl_errno($ch)){
            // Trate os erros de cURL se necessário
            throw new \Exception(curl_error($ch));
        }

        // Fecha a sessão cURL
        curl_close($ch);

        // Decodifica a resposta JSON
        $respostaDecoded = json_decode($resposta);

        // Verifica se houve erro na decodificação
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Erro ao decodificar a resposta JSON: ' . json_last_error_msg());
        }

        // Obtém o token da resposta
        $token = $respostaDecoded->access_token;

        return $token;


    }

    
}
