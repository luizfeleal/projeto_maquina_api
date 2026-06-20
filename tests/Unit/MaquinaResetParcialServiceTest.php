<?php

namespace Tests\Unit;

use App\Services\MaquinaResetParcialService;
use Tests\TestCase;

class MaquinaResetParcialServiceTest extends TestCase
{
    /** @test */
    public function enrich_acumulado_sem_reset_usa_total_como_saldo_periodo()
    {
        $row = (object) [
            'id_maquina' => 1,
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
    public function enrich_acumulado_com_reset_calcula_saldo_periodo()
    {
        $row = (object) [
            'id_maquina' => 42,
            'total_maquina' => 750.00,
            'maquina_ultima_coleta' => 500.00,
            'data_ultimo_reset' => '2026-06-01 14:30:00',
        ];

        $enriched = MaquinaResetParcialService::enrichAcumuladoRow($row);

        $this->assertTrue($enriched['tem_reset']);
        $this->assertSame(500.00, $enriched['ultima_coleta']);
        $this->assertSame(250.00, $enriched['saldo_periodo']);
        $this->assertNotNull($enriched['data_ultimo_reset']);
    }

    /** @test */
    public function enrich_acumulado_permite_saldo_periodo_negativo()
    {
        $row = (object) [
            'id_maquina' => 7,
            'total_maquina' => 400.00,
            'maquina_ultima_coleta' => 500.00,
            'data_ultimo_reset' => '2026-06-01 14:30:00',
        ];

        $enriched = MaquinaResetParcialService::enrichAcumuladoRow($row);

        $this->assertSame(-100.00, $enriched['saldo_periodo']);
    }
}
