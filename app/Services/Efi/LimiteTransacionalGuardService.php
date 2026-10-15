<?php

namespace App\Services\Efi;

use App\Models\Logs;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Controla a trava de retentativa de devoluções Pix rejeitadas pela Efí por
 * "Limite transacional insuficiente" (Parecer Técnico Efí).
 *
 * Usa a tabela `logs` existente como persistência (sem nova tabela): cada
 * bloqueio e cada liberação manual viram uma linha em `logs`, identificadas
 * pela ação e pelo id_end_to_end embutido na descrição. O payload completo do
 * erro vai para o log de arquivo (storage/logs), já que `logs.descricao` é
 * varchar(200) e não comporta o JSON inteiro.
 */
class LimiteTransacionalGuardService
{
    private const ACAO_BLOQUEIO = 'Devolução Pix - Bloqueio Limite Insuficiente';
    private const ACAO_LIBERACAO_MANUAL = 'Devolução Pix - Liberação Manual';
    private const ACAO_CONCLUIDA = 'Devolução Pix - Concluída';

    /**
     * Registra o bloqueio: status de falha definitiva, motivo e timestamp em
     * `logs`, e o payload bruto recebido da Efí no log de arquivo.
     */
    public static function registrarBloqueio(
        string $idE2E,
        ?string $txid,
        float $valor,
        string $motivo,
        array $payloadBruto,
        ?int $idMaquina
    ): void {
        Log::error('Devolução Pix bloqueada por limite transacional insuficiente', [
            'id_end_to_end' => $idE2E,
            'txid' => $txid,
            'valor' => $valor,
            'motivo' => $motivo,
            'payload' => $payloadBruto,
        ]);

        Logs::create([
            "descricao" => self::descricaoTruncada($idE2E, $motivo),
            "status" => "erro",
            "acao" => self::ACAO_BLOQUEIO,
            "id_maquina" => $idMaquina,
        ]);
    }

    public static function registrarSucesso(string $idE2E, ?int $idMaquina): void
    {
        Logs::create([
            "descricao" => self::descricaoTruncada($idE2E, 'Devolução concluída com sucesso'),
            "status" => "sucesso",
            "acao" => self::ACAO_CONCLUIDA,
            "id_maquina" => $idMaquina,
        ]);
    }

    /**
     * Libera manualmente uma devolução bloqueada (ex.: operação confirmou que
     * o limite diário da conta Efí foi ajustado/renovado).
     */
    public static function liberarManualmente(string $idE2E, ?int $idUsuario, ?int $idMaquina): void
    {
        Logs::create([
            "id_usuario" => $idUsuario,
            "descricao" => self::descricaoTruncada($idE2E, 'Liberação manual solicitada pela operação'),
            "status" => "sucesso",
            "acao" => self::ACAO_LIBERACAO_MANUAL,
            "id_maquina" => $idMaquina,
        ]);
    }

    /**
     * Verdadeiro quando existe um bloqueio para esse id_end_to_end que ainda
     * não foi resolvido: sem liberação manual posterior e dentro do mesmo dia
     * em que foi bloqueado (regra: só reliberar após virada do dia OU
     * evidência manual de alteração do limite).
     */
    public static function estaBloqueada(string $idE2E): bool
    {
        $ultimoBloqueio = self::ultimoLogDaAcao(self::ACAO_BLOQUEIO, $idE2E);

        if (!$ultimoBloqueio) {
            return false;
        }

        $liberacaoManual = self::ultimoLogDaAcao(self::ACAO_LIBERACAO_MANUAL, $idE2E);
        if ($liberacaoManual && $liberacaoManual->data_criacao >= $ultimoBloqueio->data_criacao) {
            return false;
        }

        // Virada do dia: se o bloqueio não é de hoje, já pode tentar de novo.
        return Carbon::parse($ultimoBloqueio->data_criacao)->isToday();
    }

    private static function ultimoLogDaAcao(string $acao, string $idE2E)
    {
        return Logs::where('acao', $acao)
            ->where('descricao', 'like', '%' . self::marcador($idE2E) . '%')
            ->orderByDesc('id')
            ->first();
    }

    private static function marcador(string $idE2E): string
    {
        return "e2e={$idE2E}";
    }

    private static function descricaoTruncada(string $idE2E, string $motivo): string
    {
        $prefixo = self::marcador($idE2E) . ' - ';
        $espacoDisponivel = 200 - strlen($prefixo);

        return $prefixo . mb_substr($motivo, 0, max($espacoDisponivel, 0));
    }
}
