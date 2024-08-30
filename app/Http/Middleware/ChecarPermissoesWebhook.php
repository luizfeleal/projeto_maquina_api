<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
class ChecarPermissoesWebhook
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
        
        Log::info('Middleware ChecarPermissoes está sendo executado.');

        $allowedOrigins = [
            'https://www.example.com',
            'https://api.example.com'
        ];

        $origin = $request->headers->get('origin');
        $userAgent = $request->headers->get('User-Agent');

        /*if (!in_array($origin, $allowedOrigins)) {
            Log::warning('Origem não permitida: ' . $origin);
            return response()->json(['error' => 'Origem não permitida.'], 403);
        }*/
        /*if (strpos($userAgent, 'PostmanRuntime') === false && !in_array($origin, $allowedOrigins)) {
            Log::warning('Origem não permitida: ' . $origin);
            return response()->json(['error' => 'Origem não permitida.'], 403);
        }*/
        return response()->json(['success' => 'tudo ok'], 200);

        return $next($request);
    }
}
