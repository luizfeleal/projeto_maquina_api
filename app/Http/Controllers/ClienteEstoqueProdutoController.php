<?php

namespace App\Http\Controllers;

use App\Models\ClienteEstoqueProduto;
use App\Models\EstoqueProduto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ClienteEstoqueProdutoController extends Controller
{
    public function index($id_cliente)
    {
        try {
            $vinculos = ClienteEstoqueProduto::with('produto')
                ->where('id_cliente', $id_cliente)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($vinculos, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Houve um erro ao listar os produtos do cliente.'], 500);
        }
    }

    public function store(Request $request, $id_cliente)
    {
        $validator = Validator::make($request->all(), [
            'produtos'                       => 'required|array|min:1',
            'produtos.*.id_estoque_produto'  => 'required|integer',
            'produtos.*.quantidade'          => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $criados = DB::transaction(function () use ($request, $id_cliente) {
                $criados = [];

                foreach ($request->input('produtos') as $item) {
                    $produto = EstoqueProduto::where('id', $item['id_estoque_produto'])->lockForUpdate()->first();

                    if (!$produto) {
                        throw new \RuntimeException("Produto de id {$item['id_estoque_produto']} não encontrado.");
                    }

                    if ($produto->quantidade < $item['quantidade']) {
                        throw new \RuntimeException("Estoque insuficiente para \"{$produto->nome_produto}\" (disponível: {$produto->quantidade}, solicitado: {$item['quantidade']}).");
                    }

                    $produto->quantidade -= $item['quantidade'];
                    $produto->save();

                    $criados[] = ClienteEstoqueProduto::create([
                        'id_cliente'         => $id_cliente,
                        'id_estoque_produto' => $produto->id,
                        'quantidade'         => $item['quantidade'],
                    ]);
                }

                return $criados;
            });

            return response()->json([
                'message'  => 'Produtos vinculados ao cliente com sucesso!',
                'response' => $criados,
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Houve um erro ao vincular os produtos ao cliente.'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $vinculo = ClienteEstoqueProduto::find($id);

                if (!$vinculo) {
                    return response()->json(['message' => 'Vínculo não encontrado.'], 404);
                }

                $produto = EstoqueProduto::where('id', $vinculo->id_estoque_produto)->lockForUpdate()->first();
                if ($produto) {
                    $produto->quantidade += $vinculo->quantidade;
                    $produto->save();
                }

                $vinculo->delete();

                return response()->json(['message' => 'Produto desvinculado e estoque restaurado com sucesso!'], 200);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'Houve um erro ao desvincular o produto.'], 500);
        }
    }
}
