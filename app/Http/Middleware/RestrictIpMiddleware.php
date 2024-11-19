<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RestrictIpMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Defina os IPs permitidos
        $allowedIps = ['123.456.789.000'];

        // Verifica se o IP da requisição está na lista de IPs permitidos
        if (!in_array($request->ip(), $allowedIps)) {
            // Se o IP não for permitido, retornar uma resposta de erro 403 (Acesso negado)
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return $next($request); // Se o IP for permitido, continue com a requisição
    }
}
