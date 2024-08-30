<?php

namespace App\Http\Controllers\Hardware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Maquinas;
use Carbon\Carbon;
use Throwable;

class StatusController extends Controller
{

    public function atualizarStatus(Request $request)
    {

        try{

            $dadosRequest = $request->all();

            $validator = Validator::make($dadosRequest, ["id_placa" => "required|integer", "status" => "required|boolean"], ['required' => 'O campo :attribute é obrigatório.', 'max' => 'O campo :attribute não pode ter mais de :max caracteres.']);
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }
            $id_placa = $request['id_placa'];
            $status = $request['status'];

            $hora_atual = Carbon::now('America/Sao_Paulo')->format('Y-m-d H:i:s');
    
            $dados = [];
            $dados['maquina_status'] = $status;
            $dados['maquina_ultimo_contato'] = $hora_atual;
            
            $maquina = Maquinas::where('id_placa', $id_placa)->firstOrFail();
            
            
            $maquina->fill($dados);
            $maquina->save();

            $response = $hora_atual;

            return response()->json(["message" => "Conexão efetuada com sucesso", "response" => ["horario_conexao" => $hora_atual]], 200);
        }catch(Throwable $e){
            return response()->json(["message" => "Houve um erro ao tentar salvar o status no servidor."], 500);
        }
    }
}
