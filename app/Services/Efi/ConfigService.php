<?php

namespace App\Services\Efi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ConfigService
{


    public static function setarConfiguracaoWebhook(string $token, $estruturaConfig)
    {
        //ogsService::criar(array("id_usuario"=>session()->get('id_usuario'), "tabela"=>"tipo_endereco", "funcao"=>"coletar", "datahora"=>now()));
        $arquivo = "Certificados/Naise/homologacaoTesteNaise_cert.pem";

        // Obtém o caminho absoluto do arquivo de certificado
        $certificado = Storage::disk('local')->path($arquivo);

        // Verifique se o arquivo realmente existe
        if (!file_exists($certificado)) {
            throw new \Exception("O arquivo de certificado não foi encontrado: " . $certificado);
        }

        $url = env('URL_EFI') . "/v2/gn/config";

        // Inicializa a sessão cURL
        $ch = curl_init($url);

        

        $data_string = json_encode($estruturaConfig);
        curl_setopt_array(
            $ch,
            array(
                CURLOPT_CUSTOMREQUEST => 'PUT',
                CURLOPT_POSTFIELDS => $data_string,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json', 'Authorization: Bearer ' . $token, 'x-skip-mtls-checking: true'],
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

    public static function coletarWebhooks(string $token, string $chave)
    {

        $arquivo = "Certificados/Naise/homologacaoTesteNaise_cert.pem";

        // Obtém o caminho absoluto do arquivo de certificado
        $certificado = Storage::disk('local')->path($arquivo);

        // Verifique se o arquivo realmente existe
        if (!file_exists($certificado)) {
            throw new \Exception("O arquivo de certificado não foi encontrado: " . $certificado);
        }

        $url = env('URL_EFI') . "/v2/webhook/";

        // Inicializa a sessão cURL
        $ch = curl_init($url);

        curl_setopt_array(
            $ch,
            array(
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json', 'Authorization: Bearer ' . $token],
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
}
