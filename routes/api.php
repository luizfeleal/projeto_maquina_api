<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

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
Route::post('tokenefi', 'App\Http\Controllers\Efi\AuthController@auth');
Route::post('auth/logout', 'App\Http\Controllers\AuthController@logout');
Route::get('/register', function (Request $request) {
    // Validação dos dados recebidos (opcional)
    
    // Criação do usuário
    return $user = User::create([
        'name' => 'Hardware',
        'email' => "hardware_swiftpaysolucoes12tyhf@swiftpaysolucoes.com",
        'password' => Hash::make('fjhk$re8teu*dh13'),
    ]);

    // Retorna o usuário criado como JSON
    return response()->json($user, 201);
});
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(['middleware' => ['apiJwt']], function(){

    Route::apiResource('usuarios','App\Http\Controllers\UsuariosController');
    Route::apiResource('logs','App\Http\Controllers\LogsController');
    Route::apiResource('clientes','App\Http\Controllers\ClientesController');
    Route::apiResource('maquinas','App\Http\Controllers\MaquinasController');
    Route::apiResource('clienteLocal','App\Http\Controllers\ClienteLocalController');
    Route::apiResource('gruposAcesso','App\Http\Controllers\GruposAcessoController');
    Route::apiResource('acessosTela','App\Http\Controllers\AcessosTelaController');
    Route::apiResource('extratoCliente','App\Http\Controllers\ExtratoClienteController');
    Route::apiResource('extratoMaquina','App\Http\Controllers\ExtratoMaquinaController');
    Route::get('extrato/acumulado','App\Http\Controllers\ExtratoMaquinaController@acumulatedPerMachine');
    Route::get('totalMaquinas','App\Http\Controllers\ExtratoMaquinaController@getTheLastTransactionPerMachine');
    Route::apiResource('locais','App\Http\Controllers\LocaisController');
    Route::apiResource('QRCode','App\Http\Controllers\QrController');
    Route::apiResource('credApiPix','App\Http\Controllers\CredApiPixController');
    Route::post('hardware/status', 'App\Http\Controllers\Hardware\StatusController@atualizarStatus');
    Route::post('hardware/liberarJogada', 'App\Http\Controllers\Hardware\JogadasController@liberarJogada');
    Route::post('hardware/maquinasDisponiveis', 'App\Http\Controllers\Hardware\MaquinasController@listarMaquinasDisponiveisParaRegistro');
});

//Route::post('webhook/efi', 'App\Http\Controllers\Efi\Webhooks\WebhookController@processamento');

Route::get('teste', function(){
    return 'cheguei';
});
Route::post('webhook/efi/pix', 'App\Http\Controllers\Efi\Webhooks\WebhookController@processamentoRequisicaoInicial');


