<?php

namespace App\Http\Controllers;

use App\Models\GruposAcesso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;



class GruposAcessoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try{
            $grupos_acesso = GruposAcesso::all();

            return response()->json($grupos_acesso, 200);
        }catch(Exception $e){
            return response()->json(500, 'Houve um erro ao tentar coletar os grupos de acesso.');
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
            $validator = Validator::make($dados, GruposAcesso::rules(), GruposAcesso::feedback());
            //$validatedData = $request->validate((new Usuarios)->rules(), (new Usuarios)->feedback());

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            return DB::transaction(function () use ($dados) {
                $grupos_acesso = new GruposAcesso();
                $grupos_acesso->fill($dados);
                $grupos_acesso->save();
                return response()->json(['message' => 'Grupo de acesso cadastrado com sucesso!', 'response' => $grupos_acesso], 201);
            });

        } catch (ValidationException $e) {
            return response()->json(['message' => 'Erro de validação: ' . $e->getMessage()], 422);
        } catch (Exception $e) {
            return response()->json(['message' => 'Houve um erro ao tentar cadastrar o grupo de acesso.'], 500);
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
            $grupos_acesso = GruposAcesso::find($id);

            if(!$grupos_acesso) {
                return response()->json(["response" => "Grupo de acesso não encontrado"], 404);
            }

            return response()->json($grupos_acesso, 200);
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
                $grupos_acesso = GruposAcesso::findOrFail($id);

                $grupos_acesso->fill($dados);
                $grupos_acesso->save();

                return response()->json(['message' => 'Grupo de acesso atualizado com sucesso!', 'response' => $grupos_acesso], 200);
            });
        }catch(\Exception $e) {
            return response()->json(["response" => "Houve um erro ao tentar atualizar o grupo de acesso de id: $id.", "error" => $e->getMessage()], 500);
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
