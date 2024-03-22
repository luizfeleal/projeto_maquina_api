<?php

namespace App\Http\Controllers;

use App\Models\AcessosTela;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;



class AcessosTelaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try{
            $acesso_tela = AcessosTela::all();

            return response()->json($acesso_tela, 200);
        }catch(Exception $e){
            return response()->json(500, 'Houve um erro ao tentar coletar as telas de acesso.');
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
            $validator = Validator::make($dados, AcessosTela::rules(), AcessosTela::feedback());
            //$validatedData = $request->validate((new Usuarios)->rules(), (new Usuarios)->feedback());

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            return DB::transaction(function () use ($dados) {
                $acesso_tela = new AcessosTela();
                $acesso_tela->fill($dados);
                $acesso_tela->save();
                return response()->json(['message' => 'Tela de acesso cadastrada com sucesso!', 'response' => $acesso_tela], 201);
            });

        } catch (ValidationException $e) {
            return response()->json(['message' => 'Erro de validação: ' . $e->getMessage()], 422);
        } catch (Exception $e) {
            return response()->json(['message' => 'Houve um erro ao tentar cadastrar a tela de acesso.'], 500);
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
            $acesso_tela = AcessosTela::find($id);

            if(!$acesso_tela) {
                return response()->json(["response" => "Tela de acesso não encontrada"], 404);
            }

            return response()->json($acesso_tela, 200);
        } catch(\Exception $e) {
            return response()->json(["response" => "Houve um erro ao tentar coletar o cliente de id: $id.", "error" => $e->getMessage()], 500);
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
                $acesso_tela = AcessosTela::findOrFail($id);

                $acesso_tela->fill($dados);
                $acesso_tela->save();

                return response()->json(['message' => 'Tela de acesso atualizada com sucesso!', 'response' => $acesso_tela], 200);
            });
        }catch(\Exception $e) {
            return response()->json(["response" => "Houve um erro ao tentar atualizar a tela de acesso de id: $id.", "error" => $e->getMessage()], 500);
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
