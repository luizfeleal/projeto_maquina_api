<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('auth/login', 'App\Http\Controllers\AuthController@login');
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(['middleware' => ['apiJwt']], function(){

    Route::apiResource('usuarios','App\Http\Controllers\UsuariosController');
    Route::apiResource('clientes','App\Http\Controllers\ClientesController');
    Route::apiResource('maquinas','App\Http\Controllers\MaquinasController');
    Route::apiResource('gruposAcesso','App\Http\Controllers\GruposAcessoController');
    Route::apiResource('acessosTela','App\Http\Controllers\AcessosTelaController');
    Route::apiResource('extratoCliente','App\Http\Controllers\ExtratoClienteController');
    Route::apiResource('extratoMaquina','App\Http\Controllers\ExtratoMaquinaController');
    Route::apiResource('locais','App\Http\Controllers\LocaisController');
});

