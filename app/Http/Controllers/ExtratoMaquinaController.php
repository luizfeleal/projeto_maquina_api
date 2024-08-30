<?php

namespace App\Http\Controllers;

use App\Models\ExtratoMaquina;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;



class ExtratoMaquinaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try{
            $extrato = ExtratoMaquina::all();

            return response()->json($extrato, 200);
        }catch(Exception $e){
            return response()->json(500, 'Houve um erro ao tentar coletar o extrato.');
        }
    }



    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            $dados = $request->all();
            $validator = Validator::make($dados, ExtratoMaquina::rules(), ExtratoMaquina::feedback());
            //$validatedData = $request->validate((new Usuarios)->rules(), (new Usuarios)->feedback());

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            return DB::transaction(function () use ($dados) {
                $extrato = new ExtratoMaquina();
                $extrato->fill($dados);
                $extrato->save();
                return response()->json(['message' => 'Operação cadastrada com sucesso no extrato!', 'response' => $extrato], 201);
            });

        } catch (ValidationException $e) {
            return response()->json(['message' => 'Erro de validação: ' . $e->getMessage()], 422);
        } catch (Exception $e) {
            return response()->json(['message' => 'Houve um erro ao tentar cadastrar a operação no extrato.'], 500);
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
            $extrato = ExtratoMaquina::find($id);

            if(!$extrato) {
                return response()->json(["response" => "Operação não encontrada no extrato."], 404);
            }

            return response()->json($extrato, 200);
        } catch(\Exception $e) {
            return response()->json(["response" => "Houve um erro ao tentar coletar a operação no extrato de id: $id.", "error" => $e->getMessage()], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        try{

            $dados = $request->all();

            return DB::transaction(function() use ($dados, $id){
                $extrato = ExtratoMaquina::findOrFail($id);

                $extrato->fill($dados);
                $extrato->save();

                return response()->json(['message' => 'Extrato atualizado com sucesso!', 'response' => $extrato], 200);
            });
        }catch(\Exception $e) {
            return response()->json(["response" => "Houve um erro ao tentar atualizar o extrato de id: $id.", "error" => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
