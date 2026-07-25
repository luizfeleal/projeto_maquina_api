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
            5 => ['mailable' => AlertaMensalidade5Dias::class, 'coluna' => 'alerta_5_enviado_em'],
            3 => ['mailable' => AlertaMensalidade3Dias::class, 'coluna' => 'alerta_3_enviado_em'],
            0 => ['mailable' => AlertaMensalidadeVencimentoHoje::class, 'coluna' => 'alerta_0_enviado_em'],
        ];

        foreach ($cenarios as $dias => $cenario) {
            $mailableClass = $cenario['mailable'];
            $coluna        = $cenario['coluna'];
            $vencimento    = $hoje->copy()->addDays($dias);

            $mensalidades = Mensalidade::whereDate('vencimento', $vencimento)
                ->where('status', '!=', 'pago')
                ->whereNull($coluna)
                ->get();

            $enviados = 0;

            foreach ($mensalidades as $mensalidade) {
                $cliente = Clientes::find($mensalidade->id_cliente);

                if (!$cliente || !$cliente->cliente_email) {
                    continue;
                }

                Mail::to($cliente->cliente_email)
                    ->send(new $mailableClass($mensalidade, $cliente));

                $mensalidade->update([$coluna => $hoje]);
                $enviados++;
            }

            $this->info("Alertas de {$dias} dias enviados: {$enviados} mensalidade(s).");
        }

        return Command::SUCCESS;
    }
}
