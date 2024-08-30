<?php

namespace App\Services\Efi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GestaoPixService
{


    public static function solicitarDevolucao(string $token, $e2eId, $idTransacao, $valor, $caminhoCertificado)
    {

        $arquivo = $caminhoCertificado;

        // Obtém o caminho absoluto do arquivo de certificado
        $certificado = Storage::disk('local')->path($arquivo);

        // Verifique se o arquivo realmente existe
        if (!file_exists($certificado)) {
            throw new \Exception("O arquivo de certificado não foi encontrado: " . $certificado);
        }

        $url = env('URL_EFI') . " /v2/pix/{$e2eId}/devolucao/{$idTransacao}";

        // Inicializa a sessão cURL
        $ch = curl_init($url);


        $data = array(
            "valor" => $valor
        );
        $data_string = json_encode($data);
        curl_setopt_array(
            $ch,
            array(
                CURLOPT_CUSTOMREQUEST => 'PUT',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POSTFIELDS => $data_string,
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

        $resposta = [
            "http_code" => $httpcode,
            "result" => json_decode($result)
        ];

        return $resposta;
        
    }
}
