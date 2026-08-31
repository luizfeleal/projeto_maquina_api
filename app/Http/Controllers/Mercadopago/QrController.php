<?php

namespace App\Http\Controllers\Mercadopago;

use App\Http\Controllers\Controller;
use App\Models\CredApiPix;
use App\Models\MercadopagoLoja;
use App\Models\MercadopagoPos;
use App\Services\Efi\DescriptografaCredService;
use App\Services\Mercadopago\PosService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QrController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            // qr_image é um longtext com o PNG do QR em base64; ver comentário
            // equivalente em App\Http\Controllers\QrController (fluxo Efí).
            if ($request->boolean('sem_imagem')) {
                $pos = MercadopagoPos::query()
                    ->select('id', 'id_cliente', 'id_local', 'id_maquina', 'external_pos_id', 'ativo', 'created_at', 'updated_at')
                    ->get();

                return response()->json($pos, 200);
            }

            $pos = MercadopagoPos::all();

            return response()->json($pos, 200);
        } catch (Exception $e) {
            \Log::error($e);
            return response()->json(['message' => 'Houve um erro ao tentar coletar os QR Codes do Mercado Pago.'], 500);
        }
    }

    /**
     * Cria (ou reaproveita) a Loja e o POS do Mercado Pago para a máquina
     * informada e devolve o QR Code estático já pronto para exibição/impressão.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            $idCliente = (int) $request->input('id_cliente');
            $idLocal = (int) $request->input('select_local');
            $idMaquina = (int) $request->input('select_maquina');

            $credencial = CredApiPix::where('id_cliente', $idCliente)
                ->where('tipo_cred', 'mercadopago')
                ->first();

            if (!$credencial) {
                return response()->json(['message' => 'Não foi encontrada uma credencial do Mercado Pago registrada para o cliente informado.'], 400);
            }

            $loja = MercadopagoLoja::where('id_cliente', $idCliente)->first();

            if (!$loja) {
                return response()->json(['message' => 'A loja do Mercado Pago ainda não foi cadastrada para este cliente. Cadastre a loja antes de criar o QR Code.'], 400);
            }

            $credencialDescriptografada = DescriptografaCredService::descriptografarCred($credencial->toArray());
            $accessToken = $credencialDescriptografada['client_secret'];

            return DB::transaction(function () use ($idCliente, $idLocal, $idMaquina, $loja, $accessToken) {
                $pos = PosService::criarOuObterPos($idMaquina, $idLocal, $idCliente, $loja, $accessToken);

                return response()->json(['message' => 'QR Code do Mercado Pago cadastrado com sucesso!', 'response' => $pos], 201);
            });
        } catch (Exception $e) {
            \Log::error($e);
            return response()->json(['message' => 'Houve um erro ao tentar cadastrar o QR Code do Mercado Pago.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {
            $pos = MercadopagoPos::find($id);

            if (!$pos) {
                return response()->json(['response' => 'QR Code do Mercado Pago não encontrado'], 404);
            }

            return response()->json($pos, 200);
        } catch (Exception $e) {
            return response()->json(['response' => "Houve um erro ao tentar coletar o QR Code do Mercado Pago de id: $id.", 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $pos = MercadopagoPos::find($id);
            $pos->delete();

            DB::commit();

            return response()->json(['message' => 'QR Code do Mercado Pago removido com sucesso!', 'response' => true]);
        } catch (Exception $e) {
            DB::rollBack();
            \Log::error($e);
            return response()->json(['message' => 'Houve um erro ao tentar remover o QR Code do Mercado Pago.', 'response' => false]);
        }
    }
}
