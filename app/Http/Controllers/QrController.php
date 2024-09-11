<?php

namespace App\Http\Controllers;

use App\Models\QrCode;
use App\Models\Clientes;
use App\Models\Maquinas;
use App\Models\CredApiPix;
use App\Services\Efi\QrCodeService;
use App\Services\Efi\ConfigService;
use App\Services\Efi\LocationsService;
use App\Services\Efi\ChaveAleatoriaService;
use App\Services\Efi\WebhookService;
use App\Services\Efi\DescriptografaCredService;
use App\Models\ChavePix;
use App\Services\Efi\AuthService;
use App\Services\Hardware\AuthService as HardwareAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Mpdf\QrCode\QrCode as MpdfQrCode;
use Mpdf\QrCode\Output;



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
            $id_cliente = $request['id_cliente'];
            $cred_api_pix = CredApiPix::where('id_cliente', $id_cliente)->get()[0];
            
            return $cred_api_pix;

            $dadoCredDescriptografado = DescriptografaCredService::descriptografarCred($cred_api_pix);
            $coletarChavePix = ChavePix::where('id_cliente', $id_cliente)->get();
            
            if(empty($coletarChavePix->toArray())){

               $token = AuthService::coletarToken($dadoCredDescriptografado);


                $criarChavePix = ChaveAleatoriaService::criarChaveAleatoria($token, $dadoCredDescriptografado['caminho_certificado']);

              
              	$chave = $criarChavePix['chave'];
		$registrarChavePix = new ChavePix();
                $registrarChavePix->fill(['id_cliente' => $id_cliente, 'chave' => $chave, 'status' => 1]);
                $registrarChavePix->save();

                $id_chave_pix = $registrarChavePix['id_chave_pix'];

                $webhook = WebhookService::criarEndpoint($token, $chave, $dadoCredDescriptografado['caminho_certificado']);

                $estruturaConfig =  (new QrCodeService)->setarEstruturaWebhook($chave);
                $setarConfig = ConfigService::setarConfiguracaoWebhook($token ,$estruturaConfig, $dadoCredDescriptografado['caminho_certificado']);

                if($criarChavePix){
                    $chavePix = $chave;
                }else{
                    throw new Exception();
                }
            }else{
                $chavePix = $coletarChavePix[0]['chave'];
                $id_chave_pix = $coletarChavePix[0]['id_chave_pix'];
            }

            #RESGATAR NOME DO CLIENTE
            $cliente = Clientes::find($id_cliente);
            $nomeCliente = $cliente['cliente_nome'];
            


            #RESGATAR ID PLACA DA MÁQUINA ESCOLHIDA
            $maquina = Maquinas::find($id_maquina);
            
            $id_placa = $maquina['id_placa'];
            #GERAR TXID
            $txid = (new QrCodeService)->criarTxidComIdPlaca($id_placa);

            $partesNome = explode(' ', trim($nomeCliente));
    
            // Retorna a primeira parte do nome
            
            $payload = (new QrCodeService)->setChavePix($chavePix)
                                      ->setDescricao('Pagamento')
                                      ->setNomeTitularConta($partesNome[0])
                                      ->setNomeCidadeTitularConta($cliente['cliente_cidade'])
                                      ->setTxid($txid)
                                      ->setValorTransacao(0.00);

            $payloadQrCode = $payload->getPayload();
            
                                  
            // Gerar o QR code
           $obQrCode = new MpdfQrCode($payloadQrCode);
           
           $image = (new Output\Png)->output($obQrCode, 400);
           
           
            // Obter a imagem do QR code em formato base64
            $base64Imagem = base64_encode($image);
            
            $base64Imagem = "data:image/png;base64, " . $base64Imagem;

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
        DB::beginTransaction();
        try{
            $qr = QrCode::find($id);
            $qr->delete();

            DB::commit();

            return response()->json(["message" => "QR Code removido com sucesso!", "response" => true]);
        }catch(Exception $e){
            DB::rollBack();
            return response()->json(["message" => "Houve um erro ao tentar remover o QR Code.", "response" => false]);
        }
    }
}
