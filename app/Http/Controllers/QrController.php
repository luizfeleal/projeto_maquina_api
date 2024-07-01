<?php

namespace App\Http\Controllers;

use App\Models\QrCode;
use App\Services\Efi\QrCodeService;
use App\Services\Efi\LocationsService;
use App\Services\Efi\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;



class QrController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try{
            $qrCode = QrCode::all();

            return response()->json($qrCode, 200);
        }catch(Exception $e){
            return response()->json(500, 'Houve um erro ao tentar coletar os QR Codes.');
        }
    }



    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        
        try {
            $dados = $request->all();


            

            $token = AuthService::coletarToken();
        
            $location = LocationsService::criarLocation("cobv", $token);

            $idLocation = $location->id;



            $qr = QrCodeService::criarQr($idLocation, $token);

            $dadosParaInserir = [
                "id_location_efi" => $idLocation,
                "id_maquina" => $dados['select_maquina'],
                "id_local" => $dados['select_local'],
                "qr_image" => $qr->imagemQrcode,
                "ativo" => 1
            ]; 

            $validator = Validator::make($dadosParaInserir, QrCode::rules(), QrCode::feedback());
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }
            

            return DB::transaction(function () use ($dadosParaInserir) {
                $qrCode = new QrCode();
                $qrCode->fill($dadosParaInserir);
                $qrCode->save();
                return response()->json(['message' => 'Qr Code cadastrado com sucesso!', 'response' => $qrCode], 201);
            });

        } catch (ValidationException $e) {
            return response()->json(['message' => 'Erro de validação: ' . $e->getMessage()], 422);
        } catch (Exception $e) {
            return response()->json(['message' => 'Houve um erro ao tentar cadastrar o Qr Code.'], 500);
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
            $qrCode = QrCode::find($id);

            if(!$qrCode) {
                return response()->json(["response" => "QrCode não encontrado"], 404);
            }

            return response()->json($qrCode, 200);
        } catch(\Exception $e) {
            return response()->json(["response" => "Houve um erro ao tentar coletar o QR Code de id: $id.", "error" => $e->getMessage()], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        try{

            $dados = $request->all();

            return DB::transaction(function() use ($dados, $id){
                $qrCode = QrCode::findOrFail($id);

                $qrCode->fill($dados);
                $qrCode->save();

                return response()->json(['message' => 'Qr Code atualizado com sucesso!', 'response' => $qrCode], 200);
            });
        }catch(\Exception $e) {
            return response()->json(["response" => "Houve um erro ao tentar atualizar o Qr Code de id: $id.", "error" => $e->getMessage()], 500);
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
        //
    }
}
