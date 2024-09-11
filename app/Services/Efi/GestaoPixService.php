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

        

        $homolog = false; // false para produção

        $config = [
            "certificado" => $certificado, // certificado em .pem de produção ou homologação
            ];

            $body = [
                "valor" => (string) $valor // Converte o valor para string explicitamente
            ];
            
            $body_json = json_encode($body, JSON_UNESCAPED_UNICODE); // Evita problemas com caracteres especiais
            


        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => $homolog ? "https://pix-h.api.efipay.com.br". "/v2/pix/{$e2eId}/devolucao/{$idTransacao}" : "https://pix.api.efipay.com.br" . "/v2/pix/{$e2eId}/devolucao/{$idTransacao}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "PUT",
        CURLOPT_POSTFIELDS => $body_json,
        CURLOPT_SSLCERT => $config["certificado"], // Caminho do certificado
        CURLOPT_SSLCERTPASSWD => "",
        CURLOPT_HTTPHEADER => array(
        "Authorization: Bearer ".$token,
        "Content-Type: application/json",
        "x-skip-mtls-checking: true"
        ),
        ));

        $result = json_decode(curl_exec($curl), true);
        curl_close($curl);

        $resposta = [
            "result" => $result
        ];

        return $resposta;
        
    }
}
