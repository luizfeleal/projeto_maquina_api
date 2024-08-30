<?php

namespace App\Services\Hardware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class JogadasService
{


    public static function liberarJogada($id_placa, int $valor, $transaction_id, $token)
    {
        //ogsService::criar(array("id_usuario"=>session()->get('id_usuario'), "tabela"=>"tipo_endereco", "funcao"=>"coletar", "datahora"=>now()));

        try{

            $url = env('URL_HARDWARE') . "/publish-credits";

            // Inicializa a sessão cURL
            $ch = curl_init($url);

            $data = array(
                "id" => $id_placa,
                "credits" => $valor,
                "transaction_id" => $transaction_id
            );

            $data_string = json_encode($data);
            curl_setopt_array(
                $ch,
                array(
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => $data_string,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json', 'Authorization: Bearer ' . $token],
                )
            );

            $result = curl_exec($ch);

            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            
            if (curl_errno($ch)) {
                throw new \Exception("Erro durante a requisição cURL: " . curl_error($ch));
            }
            curl_close($ch);


            $resposta = ["http_code"=> $httpcode, "resposta" => $result];

            return $resposta;
        }catch(\Exception $e){
            return $e;
        }
    }
}
