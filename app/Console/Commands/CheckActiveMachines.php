<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Maquinas;
use App\Services\Hardware\MaquinasService;
use App\Services\Hardware\AuthService;
use Carbon\Carbon;

class CheckActiveMachines extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature =  'machines:check-active';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check active machines in the system';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
	$token = AuthService::coletarToken();
        $placasAtivas = MaquinasService::coletarMaquinasAtivas($token);
        $maquinas = Maquinas::query()->update(['maquina_status' => 0]);
	//\Log::info('Placas ativas:', ['placas' => $placasAtivas]);
    foreach ($placasAtivas as $machineData) {
            //\Log::info($machineData);
            // Encontre a máquina pelo id_placa
            // Acessa o objeto correto
            $id = $machineData->id;
            $lastPing = $machineData->lastPing;
            $machine = Maquinas::where('id_placa', $id)->first();

            if ($machine) {
                // Converte lastPing para o formato Y-m-d H:i:s
                $lastPingFormatted = Carbon::createFromTimestampMs($lastPing)->format('Y-m-d H:i:s');

                // Atualize o status para 1 e o last_ping
                $machine->update([
                    'maquina_status' => 1,
                    'maquina_ultimo_contato' => $lastPingFormatted,
                ]);
            }
        }
        $this->info('Active machines checked successfully.');
        return Command::SUCCESS;
    }
}
