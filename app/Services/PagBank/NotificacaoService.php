<?php

namespace App\Services\PagBank;

use App\Models\CredApiPix;
use App\Services\Efi\DescriptografaCredService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\QrCode;

class NotificacaoService
{


    public static function coletarDadosNotificacao($codigoNotificacao)
{
    $credenciais = CredApiPix::where("tipo_cred", "pagbank")->get()->toArray();

    
    foreach ($credenciais as $index => $credencial) {
        $dadoCredDescriptografado = DescriptografaCredService::descriptografarCred($credencial);
        \Log::info('---------------CUma credencial usada---------------');
        \Log::info($dadoCredDescriptografado);
        $email = $dadoCredDescriptografado['client_id'];
        $token = $dadoCredDescriptografado['client_secret'];
        $url = env('URL_PAGBANK_NOTIFICACAO') . "/$codigoNotificacao?email=$email&token=$token";

        $ch = curl_init($url);

        curl_setopt_array(
            $ch,
            array(
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_RETURNTRANSFER => true,
            )
        );

        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpcode == 200) {
            \Log::info("Requisição bem-sucedida com a credencial $index.");
            $xml = simplexml_load_string($result);
            $xml_array = json_decode(json_encode($xml), true);

            $codigo_transacao = $xml_array['code'];
            $valor_transacao = $xml_array['grossAmount'];
            $valor_taxa = $xml_array['grossAmount'] - $xml_array['netAmount'];
            $device_info = $xml_array['deviceInfo'];

            $data_credito = [
                'id_end_to_end' => $codigo_transacao,
                'id_maquina' => 0,
                'extrato_operacao_valor' => $valor_transacao,
                'extrato_operacao_tipo' => 'Cartão',
                'extrato_operacao_status' => 1,
                'extrato_operacao' => "C"
            ];
            $data_debito = [
                'id_end_to_end' => $codigo_transacao,
                'id_maquina' => 0,
                'extrato_operacao_valor' => $valor_taxa,
                'extrato_operacao_tipo' => 'Taxa',
                'extrato_operacao_status' => 1,
                'extrato_operacao' => "D"
            ];

            $dado_transacao = [
                "credito" => $data_credito,
                "debito" => $data_debito,
                "device" => $device_info['serialNumber']
            ];

            return ["http_code" => $httpcode, "resposta" => $dado_transacao];
        } else {
            \Log::warning("Credencial $index falhou com código HTTP: $httpcode.");
        }
    }

    // Caso nenhuma credencial funcione
    \Log::error("Todas as credenciais falharam para o código de notificação: $codigoNotificacao.");
    return ["http_code" => 500, "resposta" => null];
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

    
}
