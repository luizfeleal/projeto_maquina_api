<?php

namespace App\Http\Controllers;

use App\Models\QrCode;
use App\Models\Clientes;
use App\Models\Maquinas;
use App\Services\Efi\QrCodeService;
use App\Services\Efi\LocationsService;
use App\Services\Efi\ChaveAleatoriaService;
use App\Services\Efi\WebhookService;
use App\Models\ChavePix;
use App\Services\Efi\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;



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

            $id_local = $dados['select_local'];
            $id_maquina = $dados['select_maquina'];


            #COLETAR CHAVE PIX
            #CASO NÃO TENHA, CRIAR A CHAVE NA EFÍ E REALIZAR O CADASTRO DA CHAVE NA BASE
            $id_cliente = 1;

            $coletarChavePix = ChavePix::where('id_cliente', $id_cliente)->get();

            if(empty($coletarChavePix->toArray())){
                $token = AuthService::coletarToken();
                //$criarChavePix = ChaveAleatoriaService::criarChaveAleatoria($token);
                $chave = "5ee22d18-d5a4-4d02-be4b-9adb456409f8";
                $webhook = WebhookService::criarEndpoint($token, $chave);

                if($criarChavePix){
                    $chavePix = $criarChavePix['chavePix'];
                }else{
                    throw new Exception();
                }
            }else{
                $chavePix = $coletarChavePix[0]['chave'];
                $id_chave_pix = $coletarChavePix[0]['id_chave_pix'];
            }

            #RESGATAR NOME DO CLIENTE
            $coletarNomeTitular = Clientes::find($id_cliente);
            $nomeCliente = $coletarNomeTitular['cliente_nome'];


            #RESGATAR ID PLACA DA MÁQUINA ESCOLHIDA
            $maquina = Maquinas::find($id_maquina);
            
            $id_placa = $maquina['id_placa'];
            #GERAR TXID
            $txid = (new QrCodeService)->criarTxidComIdPlaca($id_placa);

            $payload = (new QrCodeService)->setChavePix($chavePix)
                                      ->setDescricao('')
                                      ->setNomeTitularConta($nomeCliente)
                                      ->setNomeCidadeTitularConta('')
                                      ->setTxid($txid)
                                      ->setValorTransacao(0.00);

            $payloadQrCode = $payload->getPayload();
                                  
            // Gerar o QR code
            $result = Builder::create()
            ->writer(new PngWriter())
            ->writerOptions([])
            ->data($payloadQrCode)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::HIGH) // Use a constante diretamente
            ->size(300)
            ->margin(10)
            ->roundBlockSizeMode(RoundBlockSizeMode::MARGIN) // Use a constante diretamente
            ->build();
            // Obter a imagem do QR code em formato base64
            $image = $result->getString();
            $base64Imagem = base64_encode($image);

            //$token = AuthService::coletarToken();
            
            //Verifica se existe chave pix
            

            $dadosParaInserir = [
                "id_chave_pix" => $id_chave_pix,
                "id_maquina" => $id_maquina,
                "id_local" => $id_local,
                "qr_image" => $base64Imagem,
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
