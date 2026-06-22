<?php

namespace App\Http\Controllers\Efi\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Mensalidade;
use App\Services\Efi\BoletoService;
use Efi\Exception\EfiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BoletoWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $token = $request->input('notification');

        if (!$token) {
            return response()->json(['message' => 'ok'], 200);
        }

        try {
            $notification = BoletoService::consultarNotificacao($token);

            Log::info('Efi boleto webhook', ['notification' => $notification]);

            $chargeId = $notification['data'][0]['charge']['id'] ?? null;
            $status   = $notification['data'][0]['status']['current'] ?? null;

            if (!$chargeId) {
                return response()->json(['message' => 'ok'], 200);
            }

            $mensalidade = Mensalidade::where('efi_charge_id', $chargeId)->first();

            if ($mensalidade && $status) {
                $mensalidade->boleto_status = $status;

                if ($status === 'paid') {
                    $mensalidade->status = 'pago';
                }

                $mensalidade->save();
            }
        } catch (EfiException $e) {
            Log::error('Efi boleto webhook error', [
                'code'        => $e->code,
                'error'       => $e->error,
                'description' => $e->errorDescription,
            ]);
        } catch (\Exception $e) {
            Log::error('Efi boleto webhook unexpected error', ['message' => $e->getMessage()]);
        }

        return response()->json(['message' => 'ok'], 200);
    }
}
