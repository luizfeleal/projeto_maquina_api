<?php

namespace App\Services\Hardware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\QrCode;

class AuthService
{


    public static function coletarToken()
    {
        //LogsService::criar(array("id_usuario"=>session()->get('id_usuario'), "tabela"=>"tipo_endereco", "funcao"=>"coletar", "datahora"=>now()));

        try{

            $filePath = storage_path("app/Token"); 
            if (file_exists($filePath)) {
                $token = file_get_contents($filePath);
                return $token;
                // Use $token as needed
            } else {
                // Handle the error if the file is not accessible
                throw new \Exception("Unable to access the token file.");
            }
        } catch (Exception $e) {
            return response()->json(['message' => 'Houve um erro ao tentar ler o arquivo: ' . $e->getMessage()], 500);
        }
    }

    
}
