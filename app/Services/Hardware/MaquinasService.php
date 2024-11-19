<?php

namespace App\Services\Hardware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\QrCode;

class MaquinasService
{


    public static function coletarMaquinasDisponiveisParaRegistro($token)
    {
        //LogsService::criar(array("id_usuario"=>session()->get('id_usuario'), "tabela"=>"tipo_endereco", "funcao"=>"coletar", "datahora"=>now()));

        try{
            $url = env('URL_HARDWARE') . "/available-devices";

            // Inicializa a sessão cURL
            $ch = curl_init($url);


            curl_setopt_array(
                $ch,
                array(
                    CURLOPT_CUSTOMREQUEST => 'GET',
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


            $resposta = ["http_code"=> $httpcode, "resposta" => json_decode($result)];

            return $resposta;
        }catch(\Exception $e){
            return $e;
        }

    }

    public static function registrarMaquinas($token, array $ids_placa)
    {
        try{
            $url = env('URL_HARDWARE') . "/register-devices";

            // Inicializa a sessão cURL
            $ch = curl_init($url);

            $data = array(
                "deviceIds" => $ids_placa,
                
            );

            $data_string = json_encode($data);
            curl_setopt_array(
                $ch,
                array(
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => $data_string,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 10, // Tempo máximo para a execução (em segundos)
                    CURLOPT_CONNECTTIMEOUT => 10, // Tempo máximo para estabelecer a conexão (em segundos)
                    CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json', 'Authorization: Bearer ' . $token],
                )
            );

            $result = curl_exec($ch);

            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);


            $resposta = ["http_code"=> $httpcode, "resposta" => json_decode($result)];

            return $resposta;
        }catch(\Exception $e){
            return $e;
        }

    }

public static function removerMaquina($token, $id)
    {
        try{
            $url = env('URL_HARDWARE') . "/removed-devices";

            // Inicializa a sessão cURL
            $ch = curl_init($url);

            $data = array(
                "deviceId" => $id,
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

            curl_close($ch);


            $resposta = ["http_code"=> $httpcode, "resposta" => json_decode($result)];

            return $resposta;
        }catch(\Exception $e){
            return $e;
        }
    }

	public static function coletarMaquinasAtivas($token){
        $url = env('URL_HARDWARE') . "/validated-devices";

        // Inicializa a sessão cURL
        $ch = curl_init($url);

        curl_setopt_array(
            $ch,
            array(
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json', 'Authorization: Bearer ' . $token],
            )
        );

        $result = curl_exec($ch);

        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return json_decode($result);
    }
    public static function coletarTransaçõesMaquina($token){
        //LogsService::criar(array("id_usuario"=>session()->get('id_usuario'), "tabela"=>"tipo_endereco", "funcao"=>"coletar", "datahora"=>now()));
    
            try{
                $url = env('URL_HARDWARE') . "/local-transaction-log";
    
                // Inicializa a sessão cURL
                $ch = curl_init($url);
    
    
                curl_setopt_array(
                    $ch,
                    array(
                        CURLOPT_CUSTOMREQUEST => 'GET',
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
    
    
                $resposta = ["http_code"=> $httpcode, "resposta" => json_decode($result)];
    
                return $resposta;
            }catch(\Exception $e){
                return $e;
            }
    }
    
    public static function limparTransaçõesMaquinaAposColeta($token){
        try{
            $url = env('URL_HARDWARE') . "/confirm-transaction-log";
    
            // Inicializa a sessão cURL
            $ch = curl_init($url);
    
    
            curl_setopt_array(
                $ch,
                array(
                    CURLOPT_CUSTOMREQUEST => 'POST',
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
    
    
            $resposta = ["http_code"=> $httpcode, "resposta" => json_decode($result)];
    
            return $resposta;
        }catch(\Exception $e){
            return $e;
        }
    }
    
}