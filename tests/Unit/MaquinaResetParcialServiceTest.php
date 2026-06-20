<?php

namespace Tests\Unit;

use App\Services\MaquinaResetParcialService;
use Tests\TestCase;

class MaquinaResetParcialServiceTest extends TestCase
{
    /** @test */
    public function somar_extrato_sem_reset_considera_todo_historico()
    {
        $transacoes = [
            ['extrato_operacao' => 'C', 'extrato_operacao_valor' => 10.00, 'data_criacao' => '2026-06-01 10:00:00'],
            ['extrato_operacao' => 'C', 'extrato_operacao_valor' => 5.00, 'data_criacao' => '2026-06-02 10:00:00'],
            ['extrato_operacao' => 'D', 'extrato_operacao_valor' => 2.00, 'data_criacao' => '2026-06-03 10:00:00'],
        ];

        $this->assertSame(13.00, MaquinaResetParcialService::somarExtrato($transacoes));
    }

    /** @test */
    public function somar_extrato_com_reset_filtra_por_data()
    {
        $transacoes = [
            ['extrato_operacao' => 'C', 'extrato_operacao_valor' => 10.00, 'data_criacao' => '2026-06-01 10:00:00'],
            ['extrato_operacao' => 'C', 'extrato_operacao_valor' => 5.00, 'data_criacao' => '2026-06-02 10:00:00'],
            ['extrato_operacao' => 'C', 'extrato_operacao_valor' => 3.00, 'data_criacao' => '2026-06-03 11:00:00'],
        ];

        $this->assertSame(3.00, MaquinaResetParcialService::somarExtrato($transacoes, '2026-06-03 10:00:00'));
    }

    /** @test */
    public function enrich_acumulado_sem_reset_usa_total_como_saldo_periodo()
    {
        $row = (object) [
            'id_maquina' => 0,
            'total_maquina' => 300.00,
            'maquina_ultima_coleta' => null,
            'data_ultimo_reset' => null,
        ];

        $enriched = MaquinaResetParcialService::enrichAcumuladoRow($row);

        $this->assertFalse($enriched['tem_reset']);
        $this->assertNull($enriched['ultima_coleta']);
        $this->assertSame(300.00, $enriched['saldo_periodo']);
        $this->assertNull($enriched['data_ultimo_reset']);
    }

    /** @test */
    public function enrich_acumulado_formata_data_ultimo_reset()
    {
        $row = (object) [
            'id_maquina' => 0,
            'total_maquina' => 750.00,
            'maquina_ultima_coleta' => 500.00,
            'data_ultimo_reset' => '2026-06-01 14:30:00',
        ];

        $enriched = MaquinaResetParcialService::enrichAcumuladoRow($row);

        $this->assertTrue($enriched['tem_reset']);
        $this->assertSame(500.00, $enriched['ultima_coleta']);
        $this->assertNotNull($enriched['data_ultimo_reset']);
        $this->assertStringContainsString('2026-06-01', $enriched['data_ultimo_reset']);
    }
}
