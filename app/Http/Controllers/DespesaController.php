<?php

namespace App\Http\Controllers;

use App\Models\Despesa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class DespesaController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Despesa::query();

            if ($request->filled('id_categoria')) {
                $query->where('id_categoria', $request->id_categoria);
            }

            if ($request->filled('data_inicio')) {
                $query->whereDate('data', '>=', $request->data_inicio);
            }

            if ($request->filled('data_fim')) {
                $query->whereDate('data', '<=', $request->data_fim);
            }

            $despesas = $query->orderBy('data', 'desc')->get();

            return response()->json($despesas, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Houve um erro ao listar as despesas.'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $rules = array_merge(Despesa::rules(), [
                'arquivo' => 'nullable|file|mimes:pdf,jpeg,png,jpg|max:10240',
            ]);

            $validator = Validator::make($request->all(), $rules, Despesa::feedback());

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $dados = $request->except('arquivo');

            if ($request->hasFile('arquivo')) {
                $path = $request->file('arquivo')->store('comprovantes_despesas', 'public');
                $dados['anexo_path'] = $path;
            }

            return DB::transaction(function () use ($dados) {
                $despesa = new Despesa();
                $despesa->fill($dados);
                $despesa->save();
                return response()->json(['message' => 'Despesa cadastrada com sucesso!', 'response' => $despesa], 201);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'Houve um erro ao cadastrar a despesa.'], 500);
        }
    }

    public function show($id)
    {
        try {
            $despesa = Despesa::find($id);

            if (!$despesa) {
                return response()->json(['message' => 'Despesa não encontrada.'], 404);
            }

            return response()->json($despesa, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => "Houve um erro ao buscar a despesa de id: $id."], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $dados = $request->all();

            return DB::transaction(function () use ($dados, $id) {
                $despesa = Despesa::findOrFail($id);
                $despesa->fill($dados);
                $despesa->save();
                return response()->json(['message' => 'Despesa atualizada com sucesso!', 'response' => $despesa], 200);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => "Houve um erro ao atualizar a despesa de id: $id."], 500);
        }
    }

    public function destroy($id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $despesa = Despesa::find($id);

                if (!$despesa) {
                    return response()->json(['message' => 'Despesa não encontrada.'], 404);
                }

                $despesa->delete();
                return response()->json(['message' => 'Despesa excluída com sucesso!'], 200);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'Houve um erro ao excluir a despesa.'], 500);
        }
    }
}
