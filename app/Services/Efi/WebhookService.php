<?php

namespace App\Services\Efi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class WebhookService
{


    public static function criarEndpoint(string $token, string $chave)
    {
        //ogsService::criar(array("id_usuario"=>session()->get('id_usuario'), "tabela"=>"tipo_endereco", "funcao"=>"coletar", "datahora"=>now()));

        $url = "/v2/webhook/" . $chave;

        // Inicializa a sessão cURL
        $ch = curl_init($url);

        $data = array(
            "webhookUrl" => env('URL_RECEBIMENTO_WEBHOOK')
        );

        $data_string = json_encode($data);
        curl_setopt_array(
            $ch,
            array(
                CURLOPT_CUSTOMREQUEST => 'PUT',
                CURLOPT_POSTFIELDS => $data_string,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json', 'Authorization: Bearer ' . $token, 'x-skip-mtls-checking: false'],
                CURLOPT_SSL_VERIFYPEER => true, // Verifica o certificado do servidor
                CURLOPT_SSL_VERIFYHOST => 2, // Verifica o host do certificado
                CURLOPT_SSLCERT => $certificado // Define o certificado a ser usado
            )
        );

        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    
        $resposta = json_decode($result);

        return $resposta;
    }

    public static function criarLocation(string $tipoCob, string $token)
    {

        $arquivo = "Certificados/Naise/homologacaoTeste_cert.pem";

        // Obtém o caminho absoluto do arquivo de certificado
        $certificado = Storage::disk('local')->path($arquivo);

        // Verifique se o arquivo realmente existe
        if (!file_exists($certificado)) {
            throw new \Exception("O arquivo de certificado não foi encontrado: " . $caminhoCertificado);
        }

        $url = env('URL_EFI') . "/v2/loc";

        // Inicializa a sessão cURL
        $ch = curl_init($url);

        $data = array(
            "tipoCob" => $tipoCob
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
