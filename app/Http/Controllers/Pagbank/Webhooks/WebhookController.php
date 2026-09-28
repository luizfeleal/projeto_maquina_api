<?php

namespace App\Http\Controllers\Pagbank\Webhooks;

use App\Models\Maquinas;
use App\Models\MaquinaCartao;
use App\Models\Logs;
use App\Models\ExtratoMaquina;
use App\Services\Hardware\JogadasService;
use App\Services\Hardware\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use App\Services\PagBank\NotificacaoService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\LockTimeoutException;



class WebhookController extends Controller
{
    



    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return boolean
     */
    public function processamentoWebhook(Request $request)
    {
        $dado = $request;
        
        \Log::info('req inicial webhook pagabank ------------------');
        \Log::info($dado);
          $tipoNotificacao = $dado['notificationType'];
          $codigoNotificacao = $dado['notificationCode'];
          if($tipoNotificacao == 'transaction'){
              $liberarJogada = true;
              $notificacao = NotificacaoService::coletarDadosNotificacao($codigoNotificacao);

            \Log::info('Notificacao webhook pagabank ------------------');
            \Log::info($notificacao);

            $codigoTransacao = $notificacao['resposta']['credito']['id_end_to_end'] ?? null;

            // Lock por transação: evita que duas notificações concorrentes para a mesma
            // transação (ex.: o PagBank reenviando por timeout enquanto a primeira ainda
            // está sendo processada) passem pela checagem de idempotência ao mesmo tempo,
            // o que duplicaria o extrato e liberaria a jogada duas vezes.
            $lock = $codigoTransacao !== null
                ? Cache::lock("webhook:pagbank:transacao:$codigoTransacao", 30)
                : null;

            if ($lock !== null) {
                try {
                    $lock->block(10);
                } catch (LockTimeoutException $e) {
                    \Log::info("Timeout aguardando lock da transação $codigoTransacao; outra requisição já está processando essa notificação.");
                    return true;
                }
            }

            try {

            // O PagBank pode reenviar a mesma notificação (retry por timeout, notificação
            // duplicada etc.). Sem essa checagem, cada reenvio duplicava o par Cartão/Taxa
            // no extrato e liberava a jogada novamente.
            if ($codigoTransacao !== null && ExtratoMaquina::where('id_end_to_end', $codigoTransacao)->exists()) {
                \Log::info("Notificação duplicada do PagBank para a transação $codigoTransacao, já processada anteriormente.");
                return true;
            }

            $device_numero = $notificacao['resposta']['device'];

            \Log::info('---------Numero device --------');
            \Log::info( $device_numero);

            $device = MaquinaCartao::where('device',$device_numero)->where('status', 1)->get()->toArray();
            \Log::info('---------Device Encontrado--------');
            \Log::info( $device);
            if(empty($device)){
                Logs::create([
                    "descricao" => "Erro ao tentar liberar uma jogada, device de número: $device_numero não foi encontrado no sistema.",
                    "status" => "erro",
                    "acao" => "liberar jogada",
                    "id_maquina" => 0
                ]);
            }
            $id_maquina = $device[0]['id_maquina'];

            \Log::info('---------ID Maquina pelo device--------');
            \Log::info($id_maquina);

            $notificacao['resposta']['credito']['id_maquina'] = $id_maquina;
            $notificacao['resposta']['debito']['id_maquina'] = $id_maquina;

            if($device[0]['status'] != 1){
                $liberarJogada = false;
                Logs::create([
                    "descricao" => "Erro ao tentar liberar jogadas! A máquina de cartão se encontra como inativa",
                    "status" => "erro",
                    "acao" => "liberar jogada",
                    "id_maquina" => $id_maquina
                ]);
                return;
            }
            
            $maquina = Maquinas::where('id_maquina', $id_maquina)->get();
            \Log::info('--------Máquina encontrada cartão------');
            \Log::info($maquina);
            if(isset($maquina[0])){

                if(!empty($maquina) && $maquina[0]['bloqueio_jogada_pagbank'] == 1){
                    $liberarJogada = false;
                    Logs::create([
                        "descricao" => "Erro ao tentar liberar jogadas! A máquina de cartão se encontra como bloqueada para liberar jogadas por maquininha de cartão.",
                        "status" => "erro",
                        "acao" => "liberar jogada",
                        "id_maquina" => $id_maquina
                    ]);
                    return;
                }
            }else{
                $liberarJogada = false;
                    Logs::create([
                        "descricao" => "Erro ao tentar liberar jogadas! Não foi possível encontrar a máquina. Verifique o registro!",
                        "status" => "erro",
                        "acao" => "liberar jogada",
                        "id_maquina" => $id_maquina
                    ]);
                    return;
            }

            // Registramos o extrato assim que decidimos processar a notificação, antes de
            // acionar o hardware: se o PagBank reenviar a mesma notificação (retry), a
            // checagem de idempotência no início do método passa a barrar o reprocessamento.
            $dadosExtrato = [
                $notificacao['resposta']['credito'],
                $notificacao['resposta']['debito']
            ];
            ExtratoMaquina::insert($dadosExtrato);

            $tentativas = 0;
            $maxTentativas = env('TENTATIVAS_PERSISTENCIA_JOGADA');
            $resposta = null;
            do {

                if($liberarJogada == true){

                    $valor = $notificacao['resposta']['credito']['extrato_operacao_valor'];
                    $idE2E = $notificacao['resposta']['credito']['id_end_to_end'];
    
                    $maquina = Maquinas::find($id_maquina);
    
                    $id_placa = $maquina['id_placa'];
    
                    $token = AuthService::coletarToken();
                    $resposta = JogadasService::liberarJogada($id_placa, $valor, $idE2E, $token);
                    \Log::info('hardware----------------------');
                    \Log::info($resposta);
                    $tentativas++;
                    
                    // Verifica se o http_code é 200
                    if ($resposta['http_code'] == 200) {
                        break;
                    }
                    
                    // Se atingir o número máximo de tentativas, exibe uma mensagem de erro ou realiza outra ação
                    if ($tentativas >= $maxTentativas) {
                        
                        Logs::create([
                            "descricao" => "Erro ao tentar liberar jogadas, número de tentativas de comunicação com a máquina foi excedido.",
                            "status" => "erro",
                            "acao" => "liberar jogada",
                            "id_maquina" => $id_maquina
                        ]);
                        
                        //Fazer o estorno aqui
                        break;
    
                    }
                }else{
                    break;
                }

            } while ($resposta['http_code'] != 200);

            } finally {
                if ($lock !== null) {
                    $lock->release();
                }
            }
          }
        \Log::info('Liberação  de jogada----------------------');
        \Log::info($resposta);
        
        return true;
    }

    
}
