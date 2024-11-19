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

            $device_numero = $notificacao['resposta']['device'];

            $device = MaquinaCartao::where('device',$device_numero)->where('status', 1)->get()->toArray();
            if(empty($device)){
                Logs::create([
                    "descricao" => "Erro ao tentar liberar uma jogada, device de número: $device_numero não foi encontrado no sistema.",
                    "status" => "erro",
                    "acao" => "liberar jogada",
                    "id_maquina" => 0
                ]);
            }
            $id_maquina = $device[0]['id_maquina'];

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
            }

            if($device[0]['bloqueio_jogada_pagbank'] == 1){
                $liberarJogada = false;
                Logs::create([
                    "descricao" => "Erro ao tentar liberar jogadas! A máquina de cartão se encontra como bloqueada para liberar jogadas por maquininha de cartão.",
                    "status" => "erro",
                    "acao" => "liberar jogada",
                    "id_maquina" => $id_maquina
                ]);

            }
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

            $dadosExtrato = [
                $notificacao['resposta']['credito'],
                $notificacao['resposta']['debito']
            ];

            ExtratoMaquina::insert($dadosExtrato);
          }
        \Log::info('Liberação  de jogada----------------------');
        \Log::info($resposta);
        
        return true;
    }

    
}
