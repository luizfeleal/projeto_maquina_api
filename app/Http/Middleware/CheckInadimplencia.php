<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Maquinas;
use App\Models\Locais;
use App\Models\Mensalidade;
use Carbon\Carbon;

class CheckInadimplencia
{
    public function handle(Request $request, Closure $next)
    {
        $idPlaca = $request->input('id_placa');

        if (!$idPlaca) {
            return $next($request);
        }

        $maquina = Maquinas::where('id_placa', $idPlaca)->first();

        if (!$maquina) {
            return $next($request);
        }

        $local = Locais::find($maquina->id_local);

        if (!$local || !$local->id_cliente) {
            return $next($request);
        }

        $diasTolerancia = (int) env('INADIMPLENCIA_DIAS', 5);
        $limiteVencimento = Carbon::today()->subDays($diasTolerancia);

        $inadimplente = Mensalidade::where('id_cliente', $local->id_cliente)
            ->where('status', '!=', 'pago')
            ->whereDate('vencimento', '<=', $limiteVencimento)
            ->exists();

        if ($inadimplente) {
            return response()->json([
                'message' => 'Acesso bloqueado. Existem faturas em atraso associadas a este cliente.',
            ], 402);
        }

        return $next($request);
    }
}
