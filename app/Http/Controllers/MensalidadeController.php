<?php

namespace App\Http\Controllers;

use App\Models\Mensalidade;
use App\Models\Clientes;
use App\Services\Efi\BoletoService;
use Efi\Exception\EfiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MensalidadeController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Mensalidade::query();

            if ($request->filled('id_cliente')) {
                $query->where('id_cliente', $request->id_cliente);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('vencimento_inicio')) {
                $query->whereDate('vencimento', '>=', $request->vencimento_inicio);
            }

            if ($request->filled('vencimento_fim')) {
                $query->whereDate('vencimento', '<=', $request->vencimento_fim);
            }

            $mensalidades = $query->orderBy('vencimento', 'desc')->get();

            return response()->json($mensalidades, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Houve um erro ao listar as mensalidades.'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $dados = $request->all();
            $validator = Validator::make($dados, Mensalidade::rules(), Mensalidade::feedback());

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            return DB::transaction(function () use ($dados, $request) {
                $mensalidade = new Mensalidade();
                $mensalidade->fill($dados);
                $mensalidade->save();

                $gerarBoleto = filter_var($request->input('gerar_boleto', true), FILTER_VALIDATE_BOOLEAN);

                if ($gerarBoleto) {
                    $cliente = Clientes::find($dados['id_cliente']);

                    if ($cliente) {
                        try {
                            $boleto = BoletoService::criarBoleto($mensalidade, $cliente);

                            $mensalidade->update([
                                'efi_charge_id' => $boleto['chargeId'],
                                'boleto_barcode' => $boleto['barcode'],
                                'boleto_link'    => $boleto['link'],
                                'boleto_pdf'     => $boleto['pdf'],
                                'boleto_status'  => $boleto['status'],
                            ]);
                        } catch (EfiException $e) {
                            Log::error('Efi boleto creation failed', [
                                'mensalidade_id' => $mensalidade->id,
                                'code'           => $e->code,
                                'error'          => $e->error,
                                'description'    => $e->errorDescription,
                            ]);
                        } catch (\Exception $e) {
                            Log::error('Boleto creation unexpected error', [
                                'mensalidade_id' => $mensalidade->id,
                                'message'        => $e->getMessage(),
                            ]);
                        }
                    }
                }

                return response()->json([
                    'message'  => 'Mensalidade cadastrada com sucesso!',
                    'response' => $mensalidade->fresh(),
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'Houve um erro ao cadastrar a mensalidade.'], 500);
        }
    }

    public function show($id)
    {
        try {
            $mensalidade = Mensalidade::find($id);

            if (!$mensalidade) {
                return response()->json(['message' => 'Mensalidade não encontrada.'], 404);
            }

            return response()->json($mensalidade, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => "Houve um erro ao buscar a mensalidade de id: $id."], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $dados = $request->all();
            $rules = [
                'id_cliente' => 'sometimes|integer',
                'valor'      => 'sometimes|numeric|min:0',
                'vencimento' => 'sometimes|date',
                'status'     => 'sometimes|in:pago,pendente,atrasado',
            ];
            $validator = Validator::make($dados, $rules, Mensalidade::feedback());

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            return DB::transaction(function () use ($dados, $id) {
                $mensalidade = Mensalidade::findOrFail($id);
                $mensalidade->fill($dados);
                $mensalidade->save();
                return response()->json(['message' => 'Mensalidade atualizada com sucesso!', 'response' => $mensalidade], 200);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => "Houve um erro ao atualizar a mensalidade de id: $id."], 500);
        }
    }

    public function destroy($id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $mensalidade = Mensalidade::find($id);

                if (!$mensalidade) {
                    return response()->json(['message' => 'Mensalidade não encontrada.'], 404);
                }

                $mensalidade->delete();
                return response()->json(['message' => 'Mensalidade excluída com sucesso!'], 200);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'Houve um erro ao excluir a mensalidade.'], 500);
        }
    }

    public function boleto($id)
    {
        try {
            $mensalidade = Mensalidade::find($id);

            if (!$mensalidade) {
                return response()->json(['message' => 'Mensalidade não encontrada.'], 404);
            }

            if (!$mensalidade->efi_charge_id) {
                return response()->json(['message' => 'Esta mensalidade não possui boleto gerado.'], 404);
            }

            $detalhe = BoletoService::detalharCobranca($mensalidade->efi_charge_id);

            $novoStatus = $detalhe['data']['status'] ?? null;
            if ($novoStatus && $novoStatus !== $mensalidade->boleto_status) {
                $mensalidade->update(['boleto_status' => $novoStatus]);
            }

            return response()->json([
                'mensalidade' => $mensalidade->fresh(),
                'efi'         => $detalhe,
            ], 200);
        } catch (EfiException $e) {
            return response()->json([
                'message' => 'Erro ao consultar boleto na Efi.',
                'code'    => $e->code,
                'error'   => $e->error,
            ], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erro ao buscar dados do boleto.'], 500);
        }
    }

    public function gerarBoleto($id)
    {
        try {
            $mensalidade = Mensalidade::findOrFail($id);
            $cliente     = Clientes::find($mensalidade->id_cliente);

            if (!$cliente) {
                return response()->json(['message' => 'Cliente não encontrado.'], 404);
            }

            $boleto = BoletoService::criarBoleto($mensalidade, $cliente);

            $mensalidade->update([
                'efi_charge_id' => $boleto['chargeId'],
                'boleto_barcode' => $boleto['barcode'],
                'boleto_link'    => $boleto['link'],
                'boleto_pdf'     => $boleto['pdf'],
                'boleto_status'  => $boleto['status'],
            ]);

            return response()->json([
                'message'    => 'Boleto gerado com sucesso!',
                'mensalidade' => $mensalidade->fresh(),
            ], 201);
        } catch (EfiException $e) {
            return response()->json([
                'message' => 'Erro ao gerar boleto na Efi.',
                'code'    => $e->code,
                'error'   => $e->error,
            ], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erro ao gerar boleto.'], 500);
        }
    }

    public function cancelarBoleto($id)
    {
        try {
            $mensalidade = Mensalidade::findOrFail($id);

            if (!$mensalidade->efi_charge_id) {
                return response()->json(['message' => 'Esta mensalidade não possui boleto.'], 404);
            }

            BoletoService::cancelarCobranca($mensalidade->efi_charge_id);

            $mensalidade->update(['boleto_status' => 'cancelled']);

            return response()->json(['message' => 'Boleto cancelado com sucesso!'], 200);
        } catch (EfiException $e) {
            return response()->json([
                'message' => 'Erro ao cancelar boleto na Efi.',
                'code'    => $e->code,
                'error'   => $e->error,
            ], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erro ao cancelar boleto.'], 500);
        }
    }

    public function reenviarBoleto(Request $request, $id)
    {
        try {
            $mensalidade = Mensalidade::findOrFail($id);

            if (!$mensalidade->efi_charge_id) {
                return response()->json(['message' => 'Esta mensalidade não possui boleto.'], 404);
            }

            $email = $request->input('email');
            if (!$email) {
                $cliente = Clientes::find($mensalidade->id_cliente);
                $email   = $cliente?->cliente_email;
            }

            if (!$email) {
                return response()->json(['message' => 'E-mail não informado.'], 400);
            }

            BoletoService::reenviarBoleto($mensalidade->efi_charge_id, $email);

            return response()->json(['message' => 'Boleto reenviado com sucesso!'], 200);
        } catch (EfiException $e) {
            return response()->json([
                'message' => 'Erro ao reenviar boleto na Efi.',
                'code'    => $e->code,
                'error'   => $e->error,
            ], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erro ao reenviar boleto.'], 500);
        }
    }
}
