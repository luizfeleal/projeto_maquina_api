<?php

namespace App\Http\Controllers;

use App\Models\MaquinaCartao;
use App\Models\QrCode;
use App\Services\Hardware\AuthService;
use App\Services\Hardware\MaquinasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Exception;



class MaquinasCartaoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try{
            $maquinas = MaquinaCartao::all();

            return response()->json($maquinas, 200);
        }catch(Exception $e){
            return response()->json(500, 'Houve um erro ao tentar coletar as máquinas.');
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
            $validator = Validator::make($dados, MaquinaCartao::rules(), MaquinaCartao::feedback());
            //$validatedData = $request->validate((new Usuarios)->rules(), (new Usuarios)->feedback());

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            return DB::transaction(function () use ($dados) {
                $maquinas = new MaquinaCartao();
                $maquinas->fill($dados);
                $maquinas->save();
                return response()->json(['message' => 'Máquina cadastrada com sucesso!', 'response' => $maquinas], 201);
            });

        } catch (ValidationException $e) {
            return response()->json(['message' => 'Erro de validação: ' . $e->getMessage()], 422);
        } catch (Exception $e) {
            \Log::error($e);

            return response()->json(['message' => 'Houve um erro ao tentar cadastrar a máquina.'], 500);
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
            $maquina = MaquinaCartao::find($id);

            if(!$maquina) {
                return response()->json(["response" => "Máquina não encontrada"], 404);
            }

            return response()->json($maquina, 200);
        } catch(\Exception $e) {
            return response()->json(["response" => "Houve um erro ao tentar coletar a máquina de id: $id.", "error" => $e->getMessage()], 500);
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
                $maquina = MaquinaCartao::findOrFail($id);

                $maquina->fill($dados);
                $maquina->save();

                return response()->json(['message' => 'Máquina de cartão atualizado com sucesso!', 'response' => $maquina], 200);
            });
        }catch(\Exception $e) {
            \Log::error($e);

            return response()->json(["message" => "Houve um erro ao tentar atualizar a máquina de cartão de id: $id.", "error" => $e->getMessage()], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function inactive(Request $request)
    {
        try{

            $dados = $request->except('id');
            $id = $request['id'];

            return DB::transaction(function() use ($dados, $id){
                $maquina = MaquinaCartao::findOrFail($id);

                $maquina->fill($dados);
                $maquina->save();

                return response()->json(['message' => 'Máquina de cartão atualizado com sucesso!', 'response' => $maquina], 200);
            });
        }catch(\Exception $e) {
            \Log::error($e);

            return response()->json(["message" => "Houve um erro ao tentar atualizar a máquina de cartão de id: $id.", "error" => $e->getMessage()], 500);
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
        DB::beginTransaction();
        try{
            $maquina = MaquinaCartao::find($id);
            $maquina->delete();
            DB::commit();

            return response()->json(["message" => "Máquina removida com sucesso!", "response" => true]);
        }catch(Exception $e){

            DB::rollBack();
            \Log::error($e);
            return response()->json(["message" => "Houve um erro ao tentar remover a máquina.", "response" => false]);
        }
    }
}
