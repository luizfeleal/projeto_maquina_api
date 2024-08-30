<?php

namespace App\Http\Controllers\Hardware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Maquinas;
use App\Services\Hardware\MaquinasService;
use App\Services\Hardware\AuthService as HardwareAuth;
use App\Services\Efi\GestaoPixService;
use Carbon\Carbon;
use Throwable;
use Exception;

class MaquinasController extends Controller
{

    public function listarMaquinasDisponiveisParaRegistro()
    {

        try{

            $token = HardwareAuth::coletarToken();
            $coletarMaquinas = MaquinasService::coletarMaquinasDisponiveisParaRegistro($token);
//return $coletarMaquinas;
            if($coletarMaquinas['http_code'] != 200){
//                throw new Exception("Houve um erro ao tentar coletar as máquinas disponíveis para registro.");   
            }
		return response()->json(["message"=> "Máquinas coletadas com sucesso", "response" => $coletarMaquinas], 200);            
        }catch(Exception $e){
            return response()->json(["message" => "Houve um erro." . $e->getMessage()], 500);

        }catch(Throwable $e){
            return response()->json(["message" => "Houve um erro " .  $e->getMessage()], 500);
        }
    }
}
