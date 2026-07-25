<?php

namespace App\Http\Controllers;

use App\Models\EstoqueProduto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EstoqueProdutoController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = EstoqueProduto::query();

            if ($request->filled('lote')) {
                $query->where('lote', $request->lote);
            }

            if ($request->filled('nome_produto')) {
                $query->where('nome_produto', 'like', '%' . $request->nome_produto . '%');
            }

            $produtos = $query->orderBy('created_at', 'desc')->get();

            return response()->json($produtos, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Houve um erro ao listar o estoque de produtos.'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $dados = $request->all();
            $validator = Validator::make($dados, EstoqueProduto::rules(), EstoqueProduto::feedback());

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            return DB::transaction(function () use ($dados) {
                $produto = new EstoqueProduto();
                $produto->fill($dados);
                $produto->save();
                return response()->json(['message' => 'Produto cadastrado com sucesso!', 'response' => $produto], 201);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'Houve um erro ao cadastrar o produto.'], 500);
        }
    }

    public function show($id)
    {
        try {
            $produto = EstoqueProduto::find($id);

            if (!$produto) {
                return response()->json(['message' => 'Produto não encontrado.'], 404);
            }

            return response()->json($produto, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => "Houve um erro ao buscar o produto de id: $id."], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $dados = $request->all();
            $rules = [
                'lote'          => 'nullable|string|max:100',
                'nome_produto'  => 'sometimes|string|max:150',
                'descricao'     => 'nullable|string',
                'quantidade'    => 'sometimes|integer|min:0',
                'valor'         => 'sometimes|numeric|min:0',
                'cobrar_mensal' => 'boolean',
            ];
            $validator = Validator::make($dados, $rules, EstoqueProduto::feedback());

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            return DB::transaction(function () use ($dados, $id) {
                $produto = EstoqueProduto::findOrFail($id);
                $produto->fill($dados);
                $produto->save();
                return response()->json(['message' => 'Produto atualizado com sucesso!', 'response' => $produto], 200);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => "Houve um erro ao atualizar o produto de id: $id."], 500);
        }
    }

    public function destroy($id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $produto = EstoqueProduto::find($id);

                if (!$produto) {
                    return response()->json(['message' => 'Produto não encontrado.'], 404);
                }

                $produto->delete();
                return response()->json(['message' => 'Produto excluído com sucesso!'], 200);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'Houve um erro ao excluir o produto.'], 500);
        }
    }
}
