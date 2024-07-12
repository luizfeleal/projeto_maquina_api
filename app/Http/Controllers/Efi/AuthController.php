<?php

namespace App\Http\Controllers\Efi;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\Efi\AuthService;
use App\Http\Controllers\Controller;

class AuthController extends Controller
{

    public function auth(Request $request)
    {
        return AuthService::coletarToken();

        if (!$token = auth('api')->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $this->respondWithToken($token);
    }
}
