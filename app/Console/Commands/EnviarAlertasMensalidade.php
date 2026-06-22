<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Mensalidade;
use App\Models\Clientes;
use App\Mail\AlertaMensalidade5Dias;
use App\Mail\AlertaMensalidade3Dias;
use App\Mail\AlertaMensalidadeVencimentoHoje;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class EnviarAlertasMensalidade extends Command
{
    protected $signature = 'app:enviar-alertas-mensalidade';

    protected $description = 'Envia e-mails de alerta para mensalidades com vencimento em 5, 3 e 0 dias';

    public function handle(): int
    {
        $hoje = Carbon::today();

        $cenarios = [
            5 => AlertaMensalidade5Dias::class,
            3 => AlertaMensalidade3Dias::class,
            0 => AlertaMensalidadeVencimentoHoje::class,
        ];

        foreach ($cenarios as $dias => $mailableClass) {
            $vencimento = $hoje->copy()->addDays($dias);

            $mensalidades = Mensalidade::whereDate('vencimento', $vencimento)
                ->where('status', '!=', 'pago')
                ->get();

            foreach ($mensalidades as $mensalidade) {
                $cliente = Clientes::find($mensalidade->id_cliente);

                if (!$cliente || !$cliente->cliente_email) {
                    continue;
                }

                Mail::to($cliente->cliente_email)
                    ->send(new $mailableClass($mensalidade, $cliente));
            }

            $this->info("Alertas de {$dias} dias enviados: {$mensalidades->count()} mensalidade(s).");
        }

        return Command::SUCCESS;
    }
}
