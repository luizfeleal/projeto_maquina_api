<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Maquinas;
use App\Models\ExtratoMaquina;
use App\Services\Hardware\MaquinasService;
use App\Services\Hardware\AuthService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetTransactionsMachine extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature =  'machines:get-transactions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get the transactions';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Iniciar uma transação para melhorar a performance e garantir consistência
        DB::beginTransaction();
        
        try {
            $token = AuthService::coletarToken();
            $transacoes = MaquinasService::coletarTransaçõesMaquina($token);
            if($transacoes['http_code'] == 200){

                $transacoes = $transacoes['resposta'];
                $maquinas = Maquinas::all()->keyBy('id_placa');
                $insercoes = []; // Array para armazenar os dados que serão inseridos em massa
                
                foreach ($transacoes as $machineData) {
                    $id_placa = $machineData->deviceId;
                    $transacoes_maquina = $machineData->transactions;
                    
                    if(property_exists($maquinas, $id_placa)){
                        
                        $id_maquina = $maquinas[$id_placa]['id_maquina'];
                        foreach ($transacoes_maquina as $transacao) {
                            // Adicione os dados no array de inserções
                            $insercoes[] = [
                                "id_maquina" => $id_maquina,
                                "id_end_to_end" => $transacao->transaction_id,
                                "extrato_operacao" => "C",
                                "extrato_operacao_tipo" => "Dinheiro",
                                "extrato_operacao_valor" => $transacao->credits,
                                "extrato_operacao_status" => 1
                            ];
                        }
                    }else{
                        continue;
                    }
                }
                
                // Execute a inserção em massa
                $extratoMaquina = ExtratoMaquina::insert($insercoes);
                \Log::info($extratoMaquina);
                
                // Confirma a transação
                DB::commit();
            }else{
                DB::rollBack();
            }
        } catch (\Exception $e) {
            // Em caso de erro, faça o rollback da transação
            DB::rollBack();
            // Lide com o erro conforme necessário
            // Por exemplo: Log::error($e->getMessage());
            throw $e; // ou trate o erro da forma mais apropriada
        }

        //MaquinasService::limparTransaçõesMaquinaAposColeta($token);
        $this->info('Active machines checked successfully.');
        return Command::SUCCESS;
    }
}
