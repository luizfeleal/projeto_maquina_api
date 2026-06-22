<?php

namespace App\Http\Controllers;

use App\Models\EstoquePlaca;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EstoquePlacaController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = EstoquePlaca::query();

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('id_cliente_associado')) {
                $query->where('id_cliente_associado', $request->id_cliente_associado);
            }

            $placas = $query->orderBy('created_at', 'desc')->get();

            return response()->json($placas, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Houve um erro ao listar o estoque de placas.'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $dados = $request->all();
            $validator = Validator::make($dados, EstoquePlaca::rules(), EstoquePlaca::feedback());

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            return DB::transaction(function () use ($dados) {
                $placa = new EstoquePlaca();
                $placa->fill($dados);
                $placa->save();
                return response()->json(['message' => 'Placa cadastrada com sucesso!', 'response' => $placa], 201);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'Houve um erro ao cadastrar a placa.'], 500);
        }
    }

    public function show($id)
    {
        try {
            $placa = EstoquePlaca::find($id);

            if (!$placa) {
                return response()->json(['message' => 'Placa não encontrada.'], 404);
            }

            return response()->json($placa, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => "Houve um erro ao buscar a placa de id: $id."], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $dados = $request->all();
            $rules = [
                'serial'               => "sometimes|string|max:100|unique:estoque_placas,serial,$id",
                'status'               => 'sometimes|string|max:50',
                'id_cliente_associado' => 'nullable|integer',
            ];
            $validator = Validator::make($dados, $rules, EstoquePlaca::feedback());

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            return DB::transaction(function () use ($dados, $id) {
                $placa = EstoquePlaca::findOrFail($id);
                $placa->fill($dados);
                $placa->save();
                return response()->json(['message' => 'Placa atualizada com sucesso!', 'response' => $placa], 200);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => "Houve um erro ao atualizar a placa de id: $id."], 500);
        }
    }

    public function destroy($id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $placa = EstoquePlaca::find($id);

                if (!$placa) {
                    return response()->json(['message' => 'Placa não encontrada.'], 404);
                }

                $placa->delete();
                return response()->json(['message' => 'Placa excluída com sucesso!'], 200);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'Houve um erro ao excluir a placa.'], 500);
        }
    }
}
