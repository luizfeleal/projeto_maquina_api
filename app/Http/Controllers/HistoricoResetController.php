<?php

namespace App\Http\Controllers;

use App\Models\HistoricoReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class HistoricoResetController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = HistoricoReset::query();

            if ($request->filled('id_maquina')) {
                $query->where('id_maquina', $request->id_maquina);
            }

            if ($request->filled('data_inicio')) {
                $query->whereDate('data', '>=', $request->data_inicio);
            }

            if ($request->filled('data_fim')) {
                $query->whereDate('data', '<=', $request->data_fim);
            }

            $historico = $query->orderBy('data', 'desc')->get();

            return response()->json($historico, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Houve um erro ao listar o histórico de resets.'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $dados = $request->all();
            $validator = Validator::make($dados, HistoricoReset::rules(), HistoricoReset::feedback());

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            return DB::transaction(function () use ($dados) {
                $reset = new HistoricoReset();
                $reset->fill($dados);
                $reset->save();
                return response()->json(['message' => 'Histórico de reset cadastrado com sucesso!', 'response' => $reset], 201);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'Houve um erro ao cadastrar o histórico de reset.'], 500);
        }
    }

    public function show($id)
    {
        try {
            $reset = HistoricoReset::find($id);

            if (!$reset) {
                return response()->json(['message' => 'Histórico de reset não encontrado.'], 404);
            }

            return response()->json($reset, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => "Houve um erro ao buscar o histórico de reset de id: $id."], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $dados = $request->all();
            $rules = [
                'id_maquina'  => 'sometimes|integer',
                'valor_total' => 'sometimes|numeric|min:0',
                'valor_reset' => 'sometimes|numeric|min:0',
                'data'        => 'sometimes|date',
            ];
            $validator = Validator::make($dados, $rules, HistoricoReset::feedback());

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            return DB::transaction(function () use ($dados, $id) {
                $reset = HistoricoReset::findOrFail($id);
                $reset->fill($dados);
                $reset->save();
                return response()->json(['message' => 'Histórico de reset atualizado com sucesso!', 'response' => $reset], 200);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => "Houve um erro ao atualizar o histórico de reset de id: $id."], 500);
        }
    }

    public function destroy($id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $reset = HistoricoReset::find($id);

                if (!$reset) {
                    return response()->json(['message' => 'Histórico de reset não encontrado.'], 404);
                }

                $reset->delete();
                return response()->json(['message' => 'Histórico de reset excluído com sucesso!'], 200);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'Houve um erro ao excluir o histórico de reset.'], 500);
        }
    }
}
