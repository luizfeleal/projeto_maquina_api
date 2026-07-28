<?php

namespace App\Http\Controllers;

use App\Models\Maquinas;
use App\Services\MaquinaResetParcialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ResetParcialController extends Controller
{
    public function store(Request $request, $idMaquina)
    {
        $validator = Validator::make($request->all(), [
            'realizado_por' => 'required',
            'observacao' => 'nullable|string|max:1000',
        ], [
            'realizado_por.required' => 'O campo realizado por é obrigatório.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = MaquinaResetParcialService::registrar(
                (int) $idMaquina,
                $validator->validated()
            );

            return response()->json([
                'message' => 'Reset parcial registrado com sucesso.',
                'data' => MaquinaResetParcialService::formatResetData(
                    $result['reset'],
                    $result['saldo_periodo']
                ),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            if ($e->getMessage() === 'not_found') {
                return response()->json(['message' => 'Máquina não encontrada.'], 404);
            }

            throw $e;
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Não foi possível obter o total acumulado.',
            ], 503);
        }
    }

    public function historico(Request $request)
    {
        $paginator = MaquinaResetParcialService::historico($request->all());

        return response()->json([
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'data' => collect($paginator->items())->map(function ($row) {
                $item = (array) $row;
                $item['valor_ultima_coleta'] = round((float) $item['valor_ultima_coleta'], 2);
                $item['valor_acumulado_total'] = round((float) $item['valor_acumulado_total'], 2);
                $item['created_at'] = MaquinaResetParcialService::formatIso8601($item['created_at']);

                return $item;
            })->values(),
        ], 200);
    }

    public function ultimo($idMaquina)
    {
        if (!Maquinas::find($idMaquina)) {
            return response()->json(['message' => 'Máquina não encontrada.'], 404);
        }

        $reset = MaquinaResetParcialService::ultimoPorMaquina((int) $idMaquina);

        if (!$reset) {
            return response()->json(['message' => 'Nenhum reset parcial registrado para esta máquina.'], 404);
        }

        return response()->json([
            'data' => MaquinaResetParcialService::formatResetData($reset),
        ], 200);
    }
}
