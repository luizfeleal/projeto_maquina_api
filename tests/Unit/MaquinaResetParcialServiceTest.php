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
    public function enrich_acumulado_sem_reset_usa_total_como_saldo_periodo_mesmo_com_id_maquina_valido()
    {
        // Regressão: total_maquina é uma soma "crua" (sem inverter sinal de devolução),
        // enquanto obterSaldoPeriodo() soma com sinal (C soma, D subtrai). Sem reset, o
        // saldo do período deve ser igual ao total acumulado exibido na tela — e não pode
        // disparar uma segunda consulta que recalcule o valor com uma fórmula diferente.
        $row = (object) [
            'id_maquina' => 42,
            'total_maquina' => 300.00,
            'maquina_ultima_coleta' => null,
            'data_ultimo_reset' => null,
        ];

        $enriched = MaquinaResetParcialService::enrichAcumuladoRow($row);

        $this->assertFalse($enriched['tem_reset']);
        $this->assertSame(300.00, $enriched['saldo_periodo']);
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

    /** @test */
    public function enrich_acumulado_com_reset_usa_saldo_ja_agregado_no_banco()
    {
        // A query de acumulado agora traz saldo_periodo_calc junto do GROUP BY.
        // Quando ele vem, enrichAcumuladoRow não pode disparar a consulta por
        // máquina (o N+1 que travava a Home com o banco remoto).
        $row = (object) [
            'id_maquina' => 42,
            'total_maquina' => 750.00,
            'maquina_ultima_coleta' => 500.00,
            'data_ultimo_reset' => '2026-06-01 14:30:00',
            'saldo_periodo_calc' => 250.00,
        ];

        $enriched = MaquinaResetParcialService::enrichAcumuladoRow($row);

        $this->assertTrue($enriched['tem_reset']);
        $this->assertSame(250.00, $enriched['saldo_periodo']);
        // O campo interno não deve vazar para a resposta da API.
        $this->assertArrayNotHasKey('saldo_periodo_calc', $enriched);
    }

    /** @test */
    public function enrich_acumulado_sem_reset_ignora_saldo_agregado_e_usa_total()
    {
        // Sem reset o período é o histórico inteiro, então o valor exibido segue
        // sendo o total acumulado — mesmo que a agregação tenha devolvido 0.
        $row = (object) [
            'id_maquina' => 42,
            'total_maquina' => 300.00,
            'maquina_ultima_coleta' => null,
            'data_ultimo_reset' => null,
            'saldo_periodo_calc' => 0.0,
        ];

        $enriched = MaquinaResetParcialService::enrichAcumuladoRow($row);

        $this->assertFalse($enriched['tem_reset']);
        $this->assertSame(300.00, $enriched['saldo_periodo']);
        $this->assertArrayNotHasKey('saldo_periodo_calc', $enriched);
    }

    /** @test */
    public function expr_saldo_periodo_soma_com_sinal_apenas_apos_o_ultimo_reset()
    {
        $sql = MaquinaResetParcialService::exprSaldoPeriodo();

        // Mesmo recorte de obterSaldoPeriodo(): só transações posteriores ao reset...
        $this->assertStringContainsString('extrato_maquina.data_criacao > ultimo_reset_por_maquina.ultimo_reset', $sql);
        // ...e máquina sem reset não entra na soma.
        $this->assertStringContainsString('ultimo_reset_por_maquina.ultimo_reset IS NOT NULL', $sql);
        // ...somando com sinal (D subtrai).
        $this->assertStringContainsString("WHEN extrato_maquina.extrato_operacao = 'D' THEN -extrato_maquina.extrato_operacao_valor", $sql);
    }
}
