<?php
namespace App\Http\Controllers;

use App\Models\CredApiPix;
use App\Services\Efi\ConversorArquivoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Validation\ValidationException;

class CredApiPixController extends Controller
{
    public function index()
    {
        try {
            $cred = CredApiPix::all();

            // Descriptografar os dados ao retorná-los
            foreach ($cred as $c) {
                $c->client_secret = Crypt::decryptString($c->client_secret);
                $c->client_id = Crypt::decryptString($c->client_id);
                $c->caminho_certificado = Crypt::decryptString($c->caminho_certificado);
            }

            return response()->json($cred, 200);
        } catch (Exception $e) {
            return response()->json('Houve um erro ao tentar coletar as credenciais.', 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $dados = $request->all();

            /*$validator = Validator::make($dados, CredApiPix::rules(), CredApiPix::feedback());

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }*/
            $id_cliente = $dados['id_cliente'];


            $converter_arquivo_p12_para_pem = ConversorArquivoService::converterCertificadoEfi($request['caminho_certificado'], "Certificados", $id_cliente);

           \Log::info($converter_arquivo_p12_para_pem);
            
            if($converter_arquivo_p12_para_pem['status'] == 200){
                $caminho = $converter_arquivo_p12_para_pem['caminho_certificado'];
            }else{
                return response()->json(['message' => 'Houve um erro ao tentar cadastrar o certificado!', 'response' => $converter_arquivo_p12_para_pem], 500);
            }


            return DB::transaction(function () use ($dados, $caminho) {
                $cred = new CredApiPix();
                $cred->fill([
                    "id_cliente" => $dados['id_cliente'],
                    "client_secret" => Crypt::encryptString($dados['client_secret']),
                    "client_id" => Crypt::encryptString($dados['client_id']),
                    "caminho_certificado" => Crypt::encryptString($caminho),
                    "tipo_cred" => $dados['tipo_cred']
                ]);
                $cred->save();
                return response()->json(['message' => 'Credencial cadastrada com sucesso!', 'response' => $cred], 201);
            });
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Erro de validação: ' . $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['message' => 'Houve um erro ao tentar cadastrar a credencial.' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $cred = CredApiPix::find($id);

            if (!$cred) {
                return response()->json(["response" => "Credencial não encontrada"], 404);
            }

            // Descriptografar os dados ao retorná-los
            $cred->client_secret = Crypt::decryptString($cred->client_secret);
            $cred->client_id = Crypt::decryptString($cred->client_id);
            $cred->caminho_certificado = Crypt::decryptString($cred->caminho_certificado);

            return response()->json($cred, 200);
        } catch (Exception $e) {
            return response()->json(["response" => "Houve um erro ao tentar coletar a credencial de id: $id.", "error" => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $dados = $request->all();

            return DB::transaction(function () use ($dados, $id) {
                $cred = CredApiPix::findOrFail($id);

                $cred->fill([
                    "id_cliente" => $dados['id_cliente'],
                    "client_secret" => Crypt::encryptString($dados['client_secret']),
                    "client_id" => Crypt::encryptString($dados['client_id']),
                    "caminho_certificado" => Crypt::encryptString($dados['caminho_certificado'])
                ]);
                $cred->save();

                return response()->json(['message' => 'Credencial atualizada com sucesso!', 'response' => $cred], 200);
            });
        } catch (Exception $e) {
            return response()->json(["response" => "Houve um erro ao tentar atualizar a credencial de id: $id.", "error" => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        //
    }
}
