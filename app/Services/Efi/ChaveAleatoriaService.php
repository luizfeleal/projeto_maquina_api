<?php

namespace App\Services\Efi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ChaveAleatoriaService
{


    public static function criarChaveAleatoria(string $token, string $caminhoCertificado)
    {

      	$arquivo = $caminhoCertificado;

        // Obtém o caminho absoluto do arquivo de certificado
        $certificado = Storage::disk('local')->path($arquivo);

        // Verifique se o arquivo realmente existe
        if (!file_exists($certificado)) {
            throw new \Exception("O arquivo de certificado não foi encontrado: " . $certificado);
        }

        $homolog = false; // false para produção

        $config = [
        "certificado" => $certificado, // certificado em .pem de produção ou homologação
        ];


        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => $homolog ? "https://pix-h.api.efipay.com.br/v2/gn/evp" : "https://pix.api.efipay.com.br/v2/gn/evp",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_SSLCERT => $config["certificado"], // Caminho do certificado
        CURLOPT_SSLCERTPASSWD => "",
        CURLOPT_HTTPHEADER => array(
        "Authorization: Bearer $token",
        "Content-Type: application/json"
        ),
        ));
        $response = json_decode(curl_exec($curl), true);
        curl_close($curl);

        return $response;
        
    }
    public static function coletarChaveAleatoria(string $token)
    {

        $arquivo = "Certificados/Naise/homologacaoTeste_cert.pem";

        // Obtém o caminho absoluto do arquivo de certificado
        $certificado = Storage::disk('local')->path($arquivo);

        // Verifique se o arquivo realmente existe
        if (!file_exists($certificado)) {
            throw new \Exception("O arquivo de certificado não foi encontrado: " . $certificado);
        }

        $url = env('URL_EFI') . "/v2/gn/evp";

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

        
        if (curl_errno($ch)) {
            throw new \Exception("Erro durante a requisição cURL: " . curl_error($ch));
        }
        curl_close($ch);

        $resposta = json_decode($result);

        return $resposta;
        
    }
}
