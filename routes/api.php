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
    Route::apiResource('QRCode','App\Http\Controllers\QrController');
    Route::post('hardware/status', 'App\Http\Controllers\Hardware\StatusController@atualizarStatus');
    Route::post('hardware/liberarJogada', 'App\Http\Controllers\Hardware\JogadasController@liberarJogada');

});

//Route::post('webhook/efi', 'App\Http\Controllers\Efi\Webhooks\WebhookController@processamento');

Route::get('teste', function(){
    return 'cheguei';
});
Route::post('webhook/efi/pix', 'App\Http\Controllers\Efi\Webhooks\WebhookController@processamentoRequisicaoInicial');


