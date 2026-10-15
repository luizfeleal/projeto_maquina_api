<?php

namespace App\Http\Controllers\Efi;

use App\Http\Controllers\Controller;
use App\Models\ClienteLocal;
use App\Models\CredApiPix;
use App\Models\ExtratoMaquina;
use App\Models\Maquinas;
use App\Services\Efi\AuthService as AuthEfiService;
use App\Services\Efi\DescriptografaCredService;
use App\Services\Efi\GestaoPixService;
use App\Services\Efi\LimiteTransacionalGuardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Ação operacional manual para devoluções Pix (Efí) bloqueadas por limite
 * transacional insuficiente (Parecer Técnico Efí): a equipe só deve chamar
 * essa rota depois de confirmar que o limite diário da conta foi renovado
 * ou ajustado — o webhook nunca reexecuta isso sozinho.
 */
class DevolucaoPixController extends Controller
{
    public function retentar(Request $request)
    {
        $idE2E = $request->input('id_end_to_end');

        if (!$idE2E) {
            return response()->json(['message' => 'id_end_to_end é obrigatório.'], 422);
        }

        $extrato = ExtratoMaquina::where('id_end_to_end', $idE2E)
            ->where('extrato_operacao', 'C')
            ->first();

        if (!$extrato) {
            return response()->json(['message' => 'Transação não encontrada para esse id_end_to_end.'], 404);
        }

        $idMaquina = $extrato->id_maquina;
        $valor = (float) $extrato->extrato_operacao_valor;

        $maquina = Maquinas::find($idMaquina);
        if (!$maquina) {
            return response()->json(['message' => 'Máquina não encontrada para essa transação.'], 404);
        }

        $clienteLocal = ClienteLocal::where('id_local', $maquina->id_local)->where('cliente_local_principal', 1)->first();
        $credencial = $clienteLocal ? CredApiPix::where('id_cliente', $clienteLocal->id_cliente)->first() : null;

        if (!$credencial) {
            return response()->json(['message' => 'Credencial Efí não encontrada para o cliente dessa máquina.'], 404);
        }

        LimiteTransacionalGuardService::liberarManualmente($idE2E, $request->input('id_usuario'), $idMaquina);

        $dadoCredDescriptografado = DescriptografaCredService::descriptografarCred($credencial->toArray());
        $token = AuthEfiService::coletarToken($dadoCredDescriptografado);
        $devolucao = GestaoPixService::solicitarDevolucao(
            $token,
            $idE2E,
            $extrato->id_extrato_maquina,
            $valor,
            $dadoCredDescriptografado['caminho_certificado']
        );

        Log::info('Retentativa manual de devolução Pix', ['id_end_to_end' => $idE2E, 'resposta' => $devolucao]);

        if ($devolucao['rejeitado_por_limite'] ?? false) {
            $motivo = $devolucao['result']['mensagem'] ?? 'Limite transacional insuficiente';
            LimiteTransacionalGuardService::registrarBloqueio($idE2E, null, $valor, $motivo, $devolucao['result'] ?? [], $idMaquina);

            return response()->json(['message' => 'Devolução ainda rejeitada por limite insuficiente.', 'resposta' => $devolucao], 409);
        }

        if (($devolucao['http_code'] ?? 0) >= 400) {
            return response()->json(['message' => 'Erro ao solicitar devolução.', 'resposta' => $devolucao], 502);
        }

        LimiteTransacionalGuardService::registrarSucesso($idE2E, $idMaquina);

        return response()->json(['message' => 'Devolução reenviada com sucesso.', 'resposta' => $devolucao], 200);
    }
}
