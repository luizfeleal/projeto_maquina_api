<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function desempenho(Request $request)
    {
        try {
            $ano       = (int) $request->get('ano', now()->year);
            $idCliente = $request->input('id_cliente');
            $idMaquina = $request->input('id_maquina');

            $query = DB::table('extrato_maquina')
                ->join('maquinas', 'extrato_maquina.id_maquina', '=', 'maquinas.id_maquina')
                ->join('locais', 'maquinas.id_local', '=', 'locais.id_local')
                ->whereNull('maquinas.deleted_at')
                ->whereYear('extrato_maquina.data_criacao', $ano)
                ->where('extrato_maquina.extrato_operacao', 'C');

            if ($idCliente) {
                $query->join('cliente_local', 'locais.id_local', '=', 'cliente_local.id_local')
                      ->where('cliente_local.id_cliente', $idCliente);
            }

            if ($idMaquina) {
                $query->where('extrato_maquina.id_maquina', $idMaquina);
            }

            $rows = $query->select(
                DB::raw('MONTH(extrato_maquina.data_criacao) as mes'),
                DB::raw('COALESCE(SUM(extrato_maquina.extrato_operacao_valor), 0) as total'),
                DB::raw('COALESCE(SUM(CASE WHEN LOWER(extrato_maquina.extrato_operacao_tipo) LIKE "%pix%" THEN extrato_maquina.extrato_operacao_valor ELSE 0 END), 0) as total_pix'),
                DB::raw('COALESCE(SUM(CASE WHEN LOWER(extrato_maquina.extrato_operacao_tipo) LIKE "%cart%" THEN extrato_maquina.extrato_operacao_valor ELSE 0 END), 0) as total_cartao'),
                DB::raw('COALESCE(SUM(CASE WHEN LOWER(extrato_maquina.extrato_operacao_tipo) LIKE "%dinheir%" OR LOWER(extrato_maquina.extrato_operacao_tipo) LIKE "%fisic%" THEN extrato_maquina.extrato_operacao_valor ELSE 0 END), 0) as total_dinheiro')
            )->groupBy(DB::raw('MONTH(extrato_maquina.data_criacao)'))->get()->keyBy('mes');

            $meses = [];
            $nomeMeses = [
                1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
                5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
                9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
            ];

            for ($m = 1; $m <= 12; $m++) {
                $row = $rows->get($m);
                $meses[] = [
                    'mes'           => $m,
                    'mes_nome'      => $nomeMeses[$m],
                    'total'         => (float) ($row->total ?? 0),
                    'total_pix'     => (float) ($row->total_pix ?? 0),
                    'total_cartao'  => (float) ($row->total_cartao ?? 0),
                    'total_dinheiro' => (float) ($row->total_dinheiro ?? 0),
                ];
            }

            $trimestres = [];
            foreach ([[1,3], [4,6], [7,9], [10,12]] as $idx => [$inicio, $fim]) {
                $slice = array_slice($meses, $inicio - 1, 3);
                $trimestres[] = [
                    'trimestre'     => $idx + 1,
                    'label'         => "T" . ($idx + 1) . " {$ano}",
                    'total'         => round(array_sum(array_column($slice, 'total')), 2),
                    'total_pix'     => round(array_sum(array_column($slice, 'total_pix')), 2),
                    'total_cartao'  => round(array_sum(array_column($slice, 'total_cartao')), 2),
                    'total_dinheiro' => round(array_sum(array_column($slice, 'total_dinheiro')), 2),
                ];
            }

            $totalGeral = round(array_sum(array_column($meses, 'total')), 2);

            return response()->json([
                'ano'         => $ano,
                'total_geral' => $totalGeral,
                'mensal'      => $meses,
                'trimestral'  => $trimestres,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Houve um erro ao gerar o relatório de desempenho.', 'message' => $e->getMessage()], 500);
        }
    }
}
