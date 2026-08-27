<?php

namespace App\Http\Controllers\Mercadopago;

use App\Http\Controllers\Controller;
use App\Models\CredApiPix;
use App\Models\MercadopagoLoja;
use App\Services\Efi\DescriptografaCredService;
use App\Services\Mercadopago\LojaService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LojaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            $lojas = MercadopagoLoja::all();

            return response()->json($lojas, 200);
        } catch (Exception $e) {
            \Log::error($e);
            return response()->json(['message' => 'Houve um erro ao tentar coletar as lojas do Mercado Pago.'], 500);
        }
    }

    /**
     * Cria a Loja do cliente no Mercado Pago (passo prévio, independente da
     * criação do QR/POS de cada máquina). Se a loja já existir, apenas retorna
     * o registro existente.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            $idCliente = (int) $request->input('id_cliente');

            $credencial = CredApiPix::where('id_cliente', $idCliente)
                ->where('tipo_cred', 'mercadopago')
                ->first();

            if (!$credencial) {
                return response()->json(['message' => 'Não foi encontrada uma credencial do Mercado Pago registrada para o cliente informado.'], 400);
            }

            $credencialDescriptografada = DescriptografaCredService::descriptografarCred($credencial->toArray());
            $accessToken = $credencialDescriptografada['client_secret'];

            return DB::transaction(function () use ($idCliente, $accessToken) {
                $loja = LojaService::criarOuObterLoja($idCliente, $accessToken);

                return response()->json(['message' => 'Loja do Mercado Pago cadastrada com sucesso!', 'response' => $loja], 201);
            });
        } catch (Exception $e) {
            \Log::error($e);
            return response()->json(['message' => 'Houve um erro ao tentar cadastrar a loja do Mercado Pago.', 'error' => $e->getMessage()], 500);
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
            $loja = MercadopagoLoja::find($id);

            if (!$loja) {
                return response()->json(['response' => 'Loja do Mercado Pago não encontrada'], 404);
            }

            return response()->json($loja, 200);
        } catch (Exception $e) {
            return response()->json(['response' => "Houve um erro ao tentar coletar a loja do Mercado Pago de id: $id.", 'error' => $e->getMessage()], 500);
        }
    }
}
