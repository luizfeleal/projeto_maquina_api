<?php

namespace App\Http\Controllers;

use App\Models\Maquinas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ResetAfericaoController extends Controller
{
    public function reset(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'realizado_por' => 'required|string|max:255',
            'observacao'    => 'nullable|string|max:1000',
        ], [
            'realizado_por.required' => 'O campo realizado_por é obrigatório.',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Dados inválidos.', 'errors' => $validator->errors()], 422);
        }

        $maquina = Maquinas::find($id);

        if (!$maquina) {
            return response()->json(['message' => 'Máquina não encontrada.'], 404);
        }

        $totalAcumulado = (float) DB::table('extrato_maquina')
            ->where('id_maquina', $id)
            ->sum('extrato_operacao_valor');

        return DB::transaction(function () use ($maquina, $totalAcumulado, $request) {
            $anteriorReset = (float) $maquina->ultimo_valor_reset;

            $maquina->ultimo_valor_reset = $totalAcumulado;
            $maquina->save();

            return response()->json([
                'message' => 'Aferição resetada com sucesso.',
                'data' => [
                    'id_maquina'          => $maquina->id_maquina,
                    'maquina_nome'        => $maquina->maquina_nome,
                    'valor_acumulado_total' => $totalAcumulado,
                    'valor_reset_anterior'  => $anteriorReset,
                    'saldo_periodo'         => round($totalAcumulado - $anteriorReset, 2),
                    'novo_saldo_afericao'   => 0.0,
                    'realizado_por'         => $request->realizado_por,
                    'observacao'            => $request->observacao,
                    'realizado_em'          => now()->toIso8601String(),
                ],
            ], 200);
        });
    }
}
