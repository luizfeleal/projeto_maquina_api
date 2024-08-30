<?php

namespace App\Http\Controllers\Hardware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Maquinas;
use App\Services\Hardware\JogadasService;
use App\Services\Hardware\AuthService as HardwareAuth;
use App\Services\Efi\GestaoPixService;
use Carbon\Carbon;
use Throwable;
use Exception;

class JogadasController extends Controller
{

    public function liberarJogada(Request $request)
    {

       // try{

            $dadosRequest = $request->all();

            $validator = Validator::make($dadosRequest, ["id_placa" => "required", "valor" => "required|integer"], ['required' => 'O campo :attribute é obrigatório.', 'max' => 'O campo :attribute não pode ter mais de :max caracteres.']);
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }
		
            $id_placa = $request['id_placa'];
            $valor = $request['valor'];

            $hora_atual = Carbon::now('America/Sao_Paulo')->format('Y-m-d H:i:s');
//return "cheguei aqui";
            $tentativas = env("TENTATIVAS_PERSISTENCIA_JOGADA", 1);
            for ($i = 0; $i <= $tentativas; $i++) {
	     $token = HardwareAuth::coletarToken();

                $liberarJogada = JogadasService::liberarJogada($id_placa, $valor, $request['id_transacao'], $token);
 
                if ($liberarJogada['http_code'] == 200) {
                    return response()->json(["message" => "Jogada liberada com sucesso", "response" => ["horario_conexao" => $hora_atual]], 200);
                }

		if ($liberarJogada['http_code'] == 404) {								
                    return response()->json(["message" => "Máquina não encontrada", "http_code" => $liberarJogada['http_code']]);
                }   
    
                // Aguarde um tempo antes da próxima tentativa
                sleep(1); 
            }

            // Se todas as tentativas falharem, lance uma exceção
            throw new Exception("Falha ao liberar jogada após $tentativas tentativas.");
            
//        }catch(Exception $e){
            //aqui será executada a lógica do  pix em caso de erro
           // GestaoPixService::solicitarDevolucao();
  //          return response()->json(["message" => "Houve um erro ao tentar se comunicar com o hardware e liberar a jogada." . $e->getMessage()], 500);

       // }catch(Throwable $e){
           // return response()->json(["message" => "Houve um erro ao tentar liberar a jogada.", "erro" => $e], 500);
        //}
    }
}
