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
    Route::post('maquinas/atualizar','App\Http\Controllers\MaquinasController@update');
    Route::apiResource('maquinasCartao','App\Http\Controllers\MaquinasCartaoController');
    Route::post('maquinasCartaoAtualizar','App\Http\Controllers\MaquinasCartaoController@inactive');
    Route::apiResource('clienteLocal','App\Http\Controllers\ClienteLocalController');
    Route::apiResource('gruposAcesso','App\Http\Controllers\GruposAcessoController');
    Route::apiResource('acessosTela','App\Http\Controllers\AcessosTelaController');
    Route::apiResource('extratoCliente','App\Http\Controllers\ExtratoClienteController');
    Route::apiResource('extratoMaquina','App\Http\Controllers\ExtratoMaquinaController');
    Route::get('extrato/acumulado','App\Http\Controllers\ExtratoMaquinaController@acumulatedPerMachine');
    Route::get('extrato/total/{id?}','App\Http\Controllers\ExtratoMaquinaController@getTotal');
    Route::get('extrato/devolucao/{id?}','App\Http\Controllers\ExtratoMaquinaController@getTotalDevolucao');
    Route::get('extrato/saldo/{id?}','App\Http\Controllers\ExtratoMaquinaController@getTotalSaldo');
    Route::get('extrato/acumuladoLocal','App\Http\Controllers\ExtratoMaquinaController@acumulatedPerMachineFromLocal');
    Route::get('totalMaquinas','App\Http\Controllers\ExtratoMaquinaController@getTheLastTransactionPerMachine');
    Route::post('relatorioTotalTransacoes','App\Http\Controllers\ExtratoMaquinaController@generateReportAllTransactions');
    Route::post('relatorioTotalTransacoesTotal','App\Http\Controllers\ExtratoMaquinaController@generateReportAllTransactionsGetTotal');
    Route::post('relatorioTotalTransacoesTaxa','App\Http\Controllers\ExtratoMaquinaController@generateReportAllTransactionsTax');
    Route::post('transacaoMaquinaCliente','App\Http\Controllers\ExtratoMaquinaController@getTheLastTransactionPerMachineOfClient');
    Route::post('totalTransacaoMaquinaCliente','App\Http\Controllers\ExtratoMaquinaController@indexClient');
    Route::post('totalTransacaoMaquinaAcumuladoCliente','App\Http\Controllers\ExtratoMaquinaController@acumulatedPerMachineOfClient');
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
Route::post('webhook/efi/pix', 'App\Http\Controllers\Efi\Webhooks\WebhookController@processamentoRequisicaoInicial');//->middleware('permissionWebhook');
Route::post('webhook/pagbank', 'App\Http\Controllers\Pagbank\Webhooks\WebhookController@processamentoWebhook');


