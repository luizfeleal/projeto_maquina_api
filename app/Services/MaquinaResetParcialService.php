<?php

namespace App\Services;

use App\Models\MaquinaResetParcial;
use App\Models\Maquinas;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MaquinaResetParcialService
{
    public static function obterTotalMaquina(int $idMaquina): float
    {
        $total = DB::table('extrato_maquina')
            ->where('id_maquina', $idMaquina)
            ->sum('extrato_operacao_valor');

        return round((float) $total, 2);
    }

    public static function enrichAcumuladoRow(object $row): array
    {
        $data = (array) $row;
        $total = round((float) ($data['total_maquina'] ?? 0), 2);
        $ultimaColetaRaw = $data['maquina_ultima_coleta'] ?? null;
        $temReset = $ultimaColetaRaw !== null;

        $data['id_maquina'] = $data['id_maquina'] ?? null;
        $data['ultima_coleta'] = $temReset ? round((float) $ultimaColetaRaw, 2) : null;
        $data['tem_reset'] = $temReset;
        $data['saldo_periodo'] = $temReset
            ? round($total - (float) $ultimaColetaRaw, 2)
            : $total;

        if (isset($data['data_ultimo_reset']) && $data['data_ultimo_reset'] !== null) {
            $data['data_ultimo_reset'] = self::formatIso8601($data['data_ultimo_reset']);
        } else {
            $data['data_ultimo_reset'] = null;
        }

        unset($data['maquina_ultima_coleta']);

        return $data;
    }

    public static function enrichAcumuladoCollection(Collection $rows): Collection
    {
        return $rows->map(fn ($row) => self::enrichAcumuladoRow($row));
    }

    /**
     * @return array{reset: MaquinaResetParcial, saldo_periodo: float}
     */
    public static function registrar(int $idMaquina, array $dados): array
    {
        if (!Maquinas::find($idMaquina)) {
            throw new \InvalidArgumentException('not_found');
        }

        return DB::transaction(function () use ($idMaquina, $dados) {
            $maquina = Maquinas::lockForUpdate()->find($idMaquina);

            if (!$maquina) {
                throw new \InvalidArgumentException('not_found');
            }

            $total = self::obterTotalMaquina($idMaquina);

            $reset = MaquinaResetParcial::create([
                'id_maquina' => $idMaquina,
                'valor_ultima_coleta' => $total,
                'valor_acumulado_total' => $total,
                'realizado_por' => (string) $dados['realizado_por'],
                'observacao' => $dados['observacao'] ?? null,
                'created_at' => now(),
            ]);

            $maquina->maquina_ultima_coleta = $total;
            $maquina->save();

            return [
                'reset' => $reset,
                'saldo_periodo' => 0.00,
            ];
        });
    }

    public static function ultimoPorMaquina(int $idMaquina): ?MaquinaResetParcial
    {
        return MaquinaResetParcial::query()
            ->where('id_maquina', $idMaquina)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    public static function historico(array $filtros = []): LengthAwarePaginator
    {
        $perPage = max((int) ($filtros['per_page'] ?? 10), 1);
        $page = max((int) ($filtros['page'] ?? 1), 1);

        $query = DB::table('maquina_resets_parciais as mrp')
            ->join('maquinas', 'maquinas.id_maquina', '=', 'mrp.id_maquina')
            ->leftJoin('locais', 'maquinas.id_local', '=', 'locais.id_local')
            ->leftJoin('usuarios', 'usuarios.id_usuario', '=', DB::raw('CAST(mrp.realizado_por AS UNSIGNED)'))
            ->select(
                'mrp.id',
                'mrp.id_maquina',
                'maquinas.maquina_nome',
                'locais.local_nome',
                'mrp.valor_ultima_coleta',
                'mrp.valor_acumulado_total',
                'mrp.realizado_por',
                'usuarios.usuario_nome as realizado_por_nome',
                'mrp.observacao',
                'mrp.created_at'
            )
            ->orderByDesc('mrp.created_at')
            ->orderByDesc('mrp.id');

        if (!empty($filtros['id_maquina'])) {
            $query->where('mrp.id_maquina', $filtros['id_maquina']);
        }

        if (!empty($filtros['id_cliente'])) {
            $query->join('cliente_local', 'locais.id_local', '=', 'cliente_local.id_local')
                ->where('cliente_local.id_cliente', $filtros['id_cliente']);
        }

        if (!empty($filtros['data_inicio'])) {
            $query->whereDate('mrp.created_at', '>=', $filtros['data_inicio']);
        }

        if (!empty($filtros['data_fim'])) {
            $query->whereDate('mrp.created_at', '<=', $filtros['data_fim']);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public static function formatResetData(MaquinaResetParcial $reset, ?float $saldoPeriodo = null): array
    {
        return [
            'id' => $reset->id,
            'id_maquina' => (string) $reset->id_maquina,
            'valor_ultima_coleta' => round((float) $reset->valor_ultima_coleta, 2),
            'valor_acumulado_total' => round((float) $reset->valor_acumulado_total, 2),
            'realizado_por' => $reset->realizado_por,
            'observacao' => $reset->observacao,
            'created_at' => self::formatIso8601($reset->created_at),
            'saldo_periodo' => $saldoPeriodo !== null ? round($saldoPeriodo, 2) : null,
        ];
    }

    public static function formatIso8601($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d\TH:i:s.000000\Z');
        }

        return date('Y-m-d\TH:i:s.000000\Z', strtotime((string) $value));
    }
}
