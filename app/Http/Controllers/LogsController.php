<?php

namespace App\Http\Controllers;

use App\Models\Logs;
use App\Models\Maquinas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;


use Illuminate\Http\Request;

class LogsController extends Controller
{

    public function index()
    {

        try{
            $logs = Logs::all();

            return response()->json($logs, 200);
        }catch(Exception $e){
            return response()->json('Houve um erro ao tentar coletar os logs.', 500);
        }
    }

    public function store(Request $request)
    {

        try {

            $dados = $request->all();

            $validator = Validator::make($dados, Logs::rules(), Logs::feedback());
            //$validatedData = $request->validate((new Locais)->rules(), (new Locais)->feedback());

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }


            return DB::transaction(function () use ($dados) {
                $log = new Logs();
                $log->fill($dados);
                $log->save();
                return response()->json(['message' => 'Log cadastrado com sucesso!' , 'response' => $log], 201);
            });

        } catch (ValidationException $e) {
            return response()->json(['message' => 'Erro de validação: ' . $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['message' => 'Houve um erro ao tentar cadastrar o log.'], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {
            $local = Locais::find($id);

            if(!$local) {
                return response()->json(["response" => "Usuário não encontrado"], 404);
            }

            return response()->json($local, 200);
        } catch(\Exception $e) {
            return response()->json(["response" => "Houve um erro ao tentar coletar o log de id: $id.", "error" => $e->getMessage()], 500);
        }
    }


    public function update(Request $request, $id)
    {

        return response()->json(['message' => 'Não é possível atualizar um registro de log!'], 400);

           
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        return response()->json(['message' => 'Não é possível deletar um registro de log!'], 400);
        
    }
}
 