<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Maquinas;
use App\Models\ExtratoMaquina;
use App\Services\Hardware\MaquinasService;
use App\Services\Hardware\AuthService;
use Carbon\Carbon;

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
            \Log::info($transacoes);
            $maquinas = MaquinasService::all()->keyBy('id_placa');
            $insercoes = []; // Array para armazenar os dados que serão inseridos em massa

            foreach ($transacoes as $machineData) {
                $id_placa = $machineData->deviceId;
                $transacoes_maquina = $machineData->transactions;

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
            }

            // Execute a inserção em massa
            ExtratoMaquina::insert($insercoes);

            // Confirma a transação
            DB::commit();
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
