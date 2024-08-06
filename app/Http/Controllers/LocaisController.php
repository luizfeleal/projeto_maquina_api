<?php

namespace App\Http\Controllers;

use App\Models\Locais;
use App\Models\Maquinas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;


use Illuminate\Http\Request;

class LocaisController extends Controller
{

    public function index()
    {

        try{
            $locais = Locais::all();

            return response()->json($locais, 200);
        }catch(Exception $e){
            return response()->json('Houve um erro ao tentar coletar os usuários.', 500);
        }
    }

    public function store(Request $request)
    {

        try {

            $dados = $request->all();

            $validator = Validator::make($dados, Locais::rules(), Locais::feedback());
            //$validatedData = $request->validate((new Locais)->rules(), (new Locais)->feedback());

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }


            return DB::transaction(function () use ($dados) {
                $local = new Locais();
                $local->fill($dados);
                $local->save();
                return response()->json(['message' => 'Usuário cadastrado com sucesso!' , 'response' => $local], 201);
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
            $local = Locais::find($id);

            if(!$local) {
                return response()->json(["response" => "Usuário não encontrado"], 404);
            }

            return response()->json($local, 200);
        } catch(\Exception $e) {
            return response()->json(["response" => "Houve um erro ao tentar coletar o usuário de id: $id.", "error" => $e->getMessage()], 500);
        }
    }


    public function update(Request $request, $id)
    {

        try{

            $dados = $request->all();


            return DB::transaction(function() use ($dados, $id){
                $local = Locais::findOrFail($id);

                $local->fill($dados);
                $local->save();

                return response()->json(['message' => 'Usuário atualizado com sucesso!', 'response' => $local], 200);

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
        DB::beginTransaction();
        try{
            $maquinas = Maquinas::where('id_local', $id)->get()->toArray();
            if(!empty($maquinas)){
                return response()->json(["message" => "O local não pôde ser removido pois há máquina(s) associada(s) à ele.", "response" => false]);
            }

            $local = Locais::find($id);
            $local->delete();


            DB::commit();

            return response()->json(["message" => "Local removido com sucesso!", "response" => true]);
        }catch(Exception $e){
            DB::rollBack();
            return response()->json(["message" => "Houve um erro ao tentar remover o local.", "response" => false]);
        }
    }
}
 