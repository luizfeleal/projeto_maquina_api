<?php

namespace App\Http\Controllers;

use App\Models\ClienteLocal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;



class ClienteLocalController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try{
            $cliente_local = ClienteLocal::all();

            return response()->json($cliente_local, 200);
        }catch(Exception $e){
            return response()->json(500, 'Houve um erro ao tentar coletar a associação cliente-local.');
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
            $validator = Validator::make($dados, ClienteLocal::rules(), ClienteLocal::feedback());

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            return DB::transaction(function () use ($dados) {
                $cliente_local = new ClienteLocal();
                $cliente_local->fill($dados);
                $cliente_local->save();
                return response()->json(['message' => 'Associação cliente-local cadastrada com sucesso!', 'response' => $cliente_local], 201);
            });

        } catch (ValidationException $e) {
            return response()->json(['message' => 'Erro de validação: ' . $e->getMessage()], 422);
        } catch (Exception $e) {
            return response()->json(['message' => 'Houve um erro ao tentar cadastrar a associação cliente-local.'], 500);
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
            $cliente = ClienteLocal::find($id);

            if(!$cliente) {
                return response()->json(["response" => "Associação cliente-local não encontrada"], 404);
            }

            return response()->json($cliente, 200);
        } catch(\Exception $e) {
            return response()->json(["response" => "Houve um erro ao tentar coletar a associação cliente-local de id: $id.", "error" => $e->getMessage()], 500);
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
                $cliente = ClienteLocal::findOrFail($id);

                $cliente->fill($dados);
                $cliente->save();

                return response()->json(['message' => 'Associação cliente-local atualizada com sucesso!', 'response' => $cliente_local], 200);
            });
        }catch(\Exception $e) {
            return response()->json(["response" => "Houve um erro ao tentar atualizar a associação cliente-local de id: $id.", "error" => $e->getMessage()], 500);
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
            $cliente = ClienteLocal::find($id);
            $cliente->delete();

            DB::commit();

            return response()->json(["message" => "Associação cliente-local removida com sucesso!", "response" => true]);
        }catch(Exception $e){
            DB::rollBack();
            return response()->json(["message" => "Houve um erro ao tentar remover a associação cliente-local.", "response" => false]);
        }
    }
}
