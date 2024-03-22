<?php

namespace App\Http\Controllers;

use App\Models\Usuarios;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;


use Illuminate\Http\Request;

class UsuariosController extends Controller
{

    public function index()
    {

        try{
            $usuarios = Usuarios::all();

            return response()->json($usuarios, 200);
        }catch(Exception $e){
            return response()->json('Houve um erro ao tentar coletar os usuários.', 500);
        }
    }

    public function store(Request $request)
    {

        try {

            $dados = $request->all();

            $validator = Validator::make($dados, Usuarios::rules(), Usuarios::feedback());
            //$validatedData = $request->validate((new Usuarios)->rules(), (new Usuarios)->feedback());

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }


            return DB::transaction(function () use ($dados) {
                $usuario = new Usuarios();
                $usuario->fill($dados);
                $usuario->save();
                return response()->json(['message' => 'Usuário cadastrado com sucesso!' , 'response' => $usuario], 201);
            });

        } catch (ValidationException $e) {
            return response()->json(['message' => 'Erro de validação: ' . $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['message' => 'Houve um erro ao tentar cadastrar o usuário.'], 500);
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
            $usuario = Usuarios::find($id);

            if(!$usuario) {
                return response()->json(["response" => "Usuário não encontrado"], 404);
            }

            return response()->json($usuario, 200);
        } catch(\Exception $e) {
            return response()->json(["response" => "Houve um erro ao tentar coletar o usuário de id: $id.", "error" => $e->getMessage()], 500);
        }
    }


    public function update(Request $request, $id)
    {

        try{

            $dados = $request->all();


            return DB::transaction(function() use ($dados, $id){
                $usuario = Usuarios::findOrFail($id);

                $usuario->fill($dados);
                $usuario->save();

                return response()->json(['message' => 'Usuário atualizado com sucesso!', 'response' => $usuario], 200);

            });
        }catch(\Exception $e) {
            return response()->json(["response" => "Houve um erro ao tentar atualizar o usuário de id: $id.", "error" => $e->getMessage()], 500);
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
