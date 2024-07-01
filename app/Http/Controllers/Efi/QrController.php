<?php

namespace App\Http\Controllers\Efi;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QrController extends Controller
{

    public function auth(Request $request)
    {
        $credentials = $request->only(['email', 'password']);

        if (!$token = auth('api')->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $this->respondWithToken($token);
    }
}
