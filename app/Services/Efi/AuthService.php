<?php

namespace App\Services\Efi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\QrCode;

class AuthService
{


    public static function coletarToken($credApiPix)
    {

	$arquivo = $credApiPix['caminho_certificado']; 

        // Obtém o caminho absoluto do arquivo de certificado
        $certificado = Storage::disk('local')->path($arquivo);

        $homolog = false; // false para produção

        $config = [
        "certificado" => $certificado, // certificado em .pem de produção ou homologação
        "client_id" => $credApiPix['client_id'],
        "client_secret" => $credApiPix['client_secret']
        ];


        $autorizacao =  base64_encode($config["client_id"] . ":" . $config["client_secret"]);
        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => $homolog ? "https://pix-h.api.efipay.com.br/oauth/token" : "https://pix.api.efipay.com.br/oauth/token",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => '{"grant_type": "client_credentials"}',
        CURLOPT_SSLCERT => $config["certificado"], // Caminho do certificado
        CURLOPT_SSLCERTPASSWD => "",
        CURLOPT_HTTPHEADER => array(
        "Authorization: Basic $autorizacao",
        "Content-Type: application/json"
        ),
        ));
//        var_dump(curl_exec($curl));
        $returnAuth = json_decode(curl_exec($curl), true);
        $access_token = $returnAuth['access_token'];
        curl_close($curl);

        return $access_token;
    }

		    
}

