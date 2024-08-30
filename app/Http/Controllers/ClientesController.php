<?php

namespace App\Http\Controllers;

use App\Models\Clientes;
use App\Models\ClienteLocal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;



class ClientesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try{
            $clientes = Clientes::all();

            return response()->json($clientes, 200);
        }catch(Exception $e){
            return response()->json(500, 'Houve um erro ao tentar coletar os clientes.');
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
            $validator = Validator::make($dados, Clientes::rules(), Clientes::feedback());
            //$validatedData = $request->validate((new Usuarios)->rules(), (new Usuarios)->feedback());

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            return DB::transaction(function () use ($dados) {
                $clientes = new Clientes();
                $clientes->fill($dados);
                $clientes->save();
                return response()->json(['message' => 'Cliente cadastrado com sucesso!', 'response' => $clientes], 201);
            });

        } catch (ValidationException $e) {
            return response()->json(['message' => 'Erro de validação: ' . $e->getMessage()], 422);
        } catch (Exception $e) {
            return response()->json(['message' => 'Houve um erro ao tentar cadastrar o cliente.'], 500);
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
            $cliente = Clientes::find($id);

            if(!$cliente) {
                return response()->json(["response" => "Cliente não encontrado"], 404);
            }

            return response()->json($cliente, 200);
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
                $cliente = Clientes::findOrFail($id);

                $cliente->fill($dados);
                $cliente->save();

                return response()->json(['message' => 'Cliente atualizado com sucesso!', 'response' => $cliente], 200);
            });
        }catch(\Exception $e) {
            return response()->json(["response" => "Houve um erro ao tentar atualizar o cliente de id: $id.", "error" => $e->getMessage()], 500);
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
            // Obter todos os registros com o id_maquina especificado
            $clienteLocal = ClienteLocal::where('id_cliente', $id)->get()->toArray();

            if(!empty($clienteLocal)){
                return response()->json(["message" => "O cliente não pôde ser excluído pois há local/locais associado(s) à ele.", "response" => false]);
            }
            $clientes = Clientes::find($id);
            if($clientes){
                $clientes->delete();
            }else{
                return response()->json(["message" => "O cliente não foi encontrado.", "response" => false]);
            }

            DB::commit();

            return response()->json(["message" => "Cliente excluído com sucesso!", "response" => true]);
        }catch(Exception $e){
            DB::rollBack();
            return response()->json(["message" => "Houve um erro ao tentar excluir o cliente.", "response" => false]);
        }
    }
    
}
