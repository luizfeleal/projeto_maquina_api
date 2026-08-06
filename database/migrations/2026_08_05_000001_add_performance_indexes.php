<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Índices de performance das telas de Home, Extrato e Máquinas.
 *
 * Antes desta migração as tabelas quentes só tinham a PRIMARY KEY, então toda
 * consulta de extrato (filtro por máquina, por período, ordenação por
 * data_criacao, SUM por tipo de operação) fazia full table scan. Como o banco
 * fica em servidor remoto, cada varredura dessas custa não só CPU do MySQL mas
 * também o tempo de transferência do resultado.
 *
 * A criação é idempotente: só cria o índice se ele ainda não existir, para poder
 * rodar em bases que já receberam parte destes índices manualmente.
 */
return new class extends Migration
{
    /**
     * [tabela, nome do índice, colunas]
     */
    private array $indices = [
        // Extrato: coluna de filtro/ordenação em praticamente toda consulta.
        ['extrato_maquina', 'idx_em_maquina_data',   ['id_maquina', 'data_criacao']],
        ['extrato_maquina', 'idx_em_data',           ['data_criacao']],
        ['extrato_maquina', 'idx_em_operacao_data',  ['extrato_operacao', 'data_criacao']],
        ['extrato_maquina', 'idx_em_tipo_data',      ['extrato_operacao_tipo', 'data_criacao']],

        // Máquinas: join com locais + filtro de soft delete.
        ['maquinas', 'idx_maquinas_local',   ['id_local']],
        ['maquinas', 'idx_maquinas_deleted', ['deleted_at']],

        // Locais: filtro de soft delete.
        ['locais', 'idx_locais_deleted', ['deleted_at']],

        // Cliente/local: join dos dois lados, usado em todo filtro por cliente.
        ['cliente_local', 'idx_cl_cliente_local', ['id_cliente', 'id_local']],
        ['cliente_local', 'idx_cl_local_cliente', ['id_local', 'id_cliente']],

        // QR Code: lookup por máquina ativa (dashboard).
        ['qr_code', 'idx_qr_maquina_ativo', ['id_maquina', 'ativo']],
    ];

    public function up(): void
    {
        foreach ($this->indices as [$tabela, $nome, $colunas]) {
            if (!Schema::hasTable($tabela) || $this->indiceExiste($tabela, $nome)) {
                continue;
            }

            // Se alguma coluna não existir nesta base, pula em vez de quebrar a migração.
            foreach ($colunas as $coluna) {
                if (!Schema::hasColumn($tabela, $coluna)) {
                    continue 2;
                }
            }

            $lista = implode(', ', array_map(fn ($c) => "`{$c}`", $colunas));
            DB::statement("CREATE INDEX `{$nome}` ON `{$tabela}` ({$lista})");
        }
    }

    public function down(): void
    {
        foreach ($this->indices as [$tabela, $nome, $colunas]) {
            if (Schema::hasTable($tabela) && $this->indiceExiste($tabela, $nome)) {
                DB::statement("DROP INDEX `{$nome}` ON `{$tabela}`");
            }
        }
    }

    private function indiceExiste(string $tabela, string $nome): bool
    {
        return !empty(DB::select(
            'SELECT 1 FROM information_schema.statistics
              WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?
              LIMIT 1',
            [$tabela, $nome]
        ));
    }
};
