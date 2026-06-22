<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class VendasController extends Controller
{
    public function index()
    {
        try {
            $hoje      = Carbon::today('America/Sao_Paulo');
            $mesAtual  = Carbon::now('America/Sao_Paulo')->startOfMonth();
            $anoAtual  = Carbon::now('America/Sao_Paulo')->startOfYear();

            $baseQuery = fn () => DB::table('extrato_maquina')
                ->join('maquinas', 'extrato_maquina.id_maquina', '=', 'maquinas.id_maquina')
                ->whereNull('maquinas.deleted_at')
                ->where('extrato_maquina.extrato_operacao', 'C');

            $totalHoje   = (float) (clone $baseQuery())->whereDate('extrato_maquina.data_criacao', $hoje)->sum('extrato_maquina.extrato_operacao_valor');
            $totalMes    = (float) (clone $baseQuery())->where('extrato_maquina.data_criacao', '>=', $mesAtual)->sum('extrato_maquina.extrato_operacao_valor');
            $totalAno    = (float) (clone $baseQuery())->where('extrato_maquina.data_criacao', '>=', $anoAtual)->sum('extrato_maquina.extrato_operacao_valor');
            $totalGeral  = (float) (clone $baseQuery())->sum('extrato_maquina.extrato_operacao_valor');

            $totalMaquinas = DB::table('maquinas')->whereNull('deleted_at')->count();
            $maquinasAtivas = DB::table('maquinas')
                ->whereNull('deleted_at')
                ->where('maquina_status', 1)
                ->count();

            $ultimasTransacoes = DB::table('extrato_maquina')
                ->join('maquinas', 'extrato_maquina.id_maquina', '=', 'maquinas.id_maquina')
                ->join('locais', 'maquinas.id_local', '=', 'locais.id_local')
                ->whereNull('maquinas.deleted_at')
                ->where('extrato_maquina.extrato_operacao', 'C')
                ->select(
                    'maquinas.maquina_nome',
                    'locais.local_nome',
                    'extrato_maquina.extrato_operacao_valor',
                    'extrato_maquina.extrato_operacao_tipo',
                    DB::raw("DATE_FORMAT(extrato_maquina.data_criacao, '%d/%m/%Y %H:%i') as data_criacao")
                )
                ->orderByDesc('extrato_maquina.data_criacao')
                ->limit(10)
                ->get();

            return response()->json([
                'resumo' => [
                    'total_hoje'     => $totalHoje,
                    'total_mes'      => $totalMes,
                    'total_ano'      => $totalAno,
                    'total_geral'    => $totalGeral,
                    'total_maquinas' => $totalMaquinas,
                    'maquinas_ativas' => $maquinasAtivas,
                ],
                'ultimas_transacoes' => $ultimasTransacoes,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Houve um erro ao coletar os dados de vendas.'], 500);
        }
    }
}
