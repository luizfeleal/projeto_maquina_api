<?php

namespace App\Http\Controllers;

use App\Models\ExtratoMaquina;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;



class ExtratoMaquinaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try{
            //$extrato = ExtratoMaquina::paginate(1000);
            // Pegando os parâmetros de paginação
            $perPage = $request->get('length', 10); // Número de registros por página
            $page = $request->get('start', 0) / $perPage + 1; // Página atual
        
            $query = DB::table('extrato_maquina')
                ->join('maquinas', 'extrato_maquina.id_maquina', '=', 'maquinas.id_maquina')
                ->join('locais', 'maquinas.id_local', '=', 'locais.id_local') // Relaciona a tabela locais com a tabela maquinas
                ->select(
                    'locais.local_nome',
                    'maquinas.maquina_nome',
                    'extrato_maquina.extrato_operacao',
                    'extrato_maquina.extrato_operacao_valor',
                    'extrato_maquina.extrato_operacao_tipo',
                    'extrato_maquina.data_criacao'
                );
        
            // Total de registros
            $totalRecords = $query->count();
        
            // Paginar os dados
            $extrato = $query->offset($request->get('start', 0))
                             ->limit($perPage)
                             ->get();
        
            // Responder no formato esperado pelo DataTables
            return response()->json([
                'data' => $extrato,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $totalRecords // Se houver filtros, você deve atualizar isso para refletir o número filtrado
            ], 200);
        }catch(Exception $e){
            return response()->json(500, 'Houve um erro ao tentar coletar o extrato.');
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
            $validator = Validator::make($dados, ExtratoMaquina::rules(), ExtratoMaquina::feedback());
            //$validatedData = $request->validate((new Usuarios)->rules(), (new Usuarios)->feedback());

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            return DB::transaction(function () use ($dados) {
                $extrato = new ExtratoMaquina();
                $extrato->fill($dados);
                $extrato->save();
                return response()->json(['message' => 'Operação cadastrada com sucesso no extrato!', 'response' => $extrato], 201);
            });

        } catch (ValidationException $e) {
            return response()->json(['message' => 'Erro de validação: ' . $e->getMessage()], 422);
        } catch (Exception $e) {
            return response()->json(['message' => 'Houve um erro ao tentar cadastrar a operação no extrato.'], 500);
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
            $extrato = ExtratoMaquina::find($id);

            if(!$extrato) {
                return response()->json(["response" => "Operação não encontrada no extrato."], 404);
            }

            return response()->json($extrato, 200);
        } catch(\Exception $e) {
            return response()->json(["response" => "Houve um erro ao tentar coletar a operação no extrato de id: $id.", "error" => $e->getMessage()], 500);
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
                $extrato = ExtratoMaquina::findOrFail($id);

                $extrato->fill($dados);
                $extrato->save();

                return response()->json(['message' => 'Extrato atualizado com sucesso!', 'response' => $extrato], 200);
            });
        }catch(\Exception $e) {
            return response()->json(["response" => "Houve um erro ao tentar atualizar o extrato de id: $id.", "error" => $e->getMessage()], 500);
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

    public function acumulatedPerMachine(Request $request)
    {
        try {
            // Ajusta a consulta para incluir todas as máquinas, mesmo sem registros de extrato
            $query = DB::table('maquinas')
                ->leftJoin('extrato_maquina', 'maquinas.id_maquina', '=', 'extrato_maquina.id_maquina')
                ->leftJoin('locais', 'maquinas.id_local', '=', 'locais.id_local')
                ->select(
                    'locais.local_nome',
                    'maquinas.maquina_nome',
                    'maquinas.id_placa',
                    'maquinas.maquina_status',
                    DB::raw('COALESCE(SUM(extrato_maquina.extrato_operacao_valor), 0) as total_maquina'),
                    DB::raw('COALESCE(SUM(CASE WHEN extrato_maquina.extrato_operacao_tipo = "PIX" THEN extrato_maquina.extrato_operacao_valor ELSE 0 END), 0) as total_pix'),
                    DB::raw('COALESCE(SUM(CASE WHEN extrato_maquina.extrato_operacao_tipo = "Cartão" THEN extrato_maquina.extrato_operacao_valor ELSE 0 END), 0) as total_cartao'),
                    DB::raw('COALESCE(SUM(CASE WHEN extrato_maquina.extrato_operacao_tipo = "Dinheiro" THEN extrato_maquina.extrato_operacao_valor ELSE 0 END), 0) as total_dinheiro')
                )
                ->groupBy('locais.local_nome', 'maquinas.maquina_nome', 'maquinas.id_placa', 'maquinas.maquina_status');
    
            // Total de registros para a contagem
            $totalRecords = DB::table('maquinas')->count();
    
            // Paginar os dados
            $extrato = $query->offset($request->get('start', 0))
                             ->limit($request->get('length', 10))
                             ->get();
    
            // Responder no formato esperado pelo DataTables
            return response()->json([
                'data' => $extrato,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $totalRecords
            ], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Houve um erro ao tentar coletar o extrato.'], 500);
        }
    }

    public function acumulatedPerMachineFromLocal(Request $request)
    {

        try {
            $idLocal = $request->id_local;
            // Ajusta a consulta para incluir todas as máquinas, mesmo sem registros de extrato
            $query = DB::table('maquinas')
                ->leftJoin('extrato_maquina', 'maquinas.id_maquina', '=', 'extrato_maquina.id_maquina')
                ->leftJoin('locais', 'maquinas.id_local', '=', 'locais.id_local')
                ->select(
                    'locais.local_nome',
                    'maquinas.maquina_nome',
                    'maquinas.id_placa',
                    'maquinas.maquina_status',
                    DB::raw('COALESCE(SUM(extrato_maquina.extrato_operacao_valor), 0) as total_maquina'),
                    DB::raw('COALESCE(SUM(CASE WHEN extrato_maquina.extrato_operacao_tipo = "PIX" THEN extrato_maquina.extrato_operacao_valor ELSE 0 END), 0) as total_pix'),
                    DB::raw('COALESCE(SUM(CASE WHEN extrato_maquina.extrato_operacao_tipo = "Cartão" THEN extrato_maquina.extrato_operacao_valor ELSE 0 END), 0) as total_cartao'),
                    DB::raw('COALESCE(SUM(CASE WHEN extrato_maquina.extrato_operacao_tipo = "Dinheiro" THEN extrato_maquina.extrato_operacao_valor ELSE 0 END), 0) as total_dinheiro')
                )->where('maquinas.id_local', $idLocal)
                ->groupBy('locais.local_nome', 'maquinas.maquina_nome', 'maquinas.id_placa', 'maquinas.maquina_status');
    
            // Total de registros para a contagem
            $totalRecords = DB::table('maquinas')->count();
    
            // Paginar os dados
            $extrato = $query->offset($request->get('start', 0))
                             ->limit($request->get('length', 10))
                             ->get();
    
            // Responder no formato esperado pelo DataTables
            return response()->json([
                'data' => $extrato,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $totalRecords
            ], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Houve um erro ao tentar coletar o extrato.'], 500);
        }
    }

    public function getTheLastTransactionPerMachine(Request $request)
    {
        try {
            // 1. Recuperar todas as máquinas
            $machines = DB::table('maquinas')
                ->join('locais', 'maquinas.id_local', '=', 'locais.id_local')
                ->select(
                    'maquinas.id_maquina',
                    'maquinas.id_local',
                    'maquinas.maquina_nome',
                    'maquinas.maquina_status',
                    'locais.local_nome',
                    'maquinas.data_criacao'
                )
                ->get()
                ->keyBy('id_maquina'); // Indexar por id_maquina para fácil acesso
    
            // 2. Recuperar a última transação para cada máquina usando uma subconsulta
            $lastTransactions = DB::table('extrato_maquina as em')
                ->select(
                    'em.id_maquina',
                    'em.extrato_operacao',
                    'em.extrato_operacao_valor',
                    'em.extrato_operacao_tipo',
                    'em.data_criacao'
                )
                ->join(DB::raw('(SELECT id_maquina, MAX(data_criacao) AS last_transaction_date FROM extrato_maquina GROUP BY id_maquina) as latest'), function ($join) {
                    $join->on('em.id_maquina', '=', 'latest.id_maquina')
                         ->on('em.data_criacao', '=', 'latest.last_transaction_date');
                })
                ->whereIn('em.id_maquina', $machines->keys())
                ->get()
                ->keyBy('id_maquina'); // Indexar por id_maquina para fácil acesso
    
            // 3. Montar a resposta com todas as máquinas e suas últimas transações
            $result = $machines->map(function ($machine) use ($lastTransactions) {
                $lastTransaction = $lastTransactions->get($machine->id_maquina); // Pegando a última transação ou nulo
    
                return [
                    'id_local' => $machine->id_local,
                    'id_maquina' => $machine->id_maquina,
                    'local_nome' => $machine->local_nome,
                    'maquina_nome' => $machine->maquina_nome,
                    'maquina_status' => $machine->maquina_status,
                    'extrato_operacao' => $lastTransaction ? $lastTransaction->extrato_operacao : 'N/A',
                    'extrato_operacao_valor' => $lastTransaction ? $lastTransaction->extrato_operacao_valor : 0,
                    'extrato_operacao_tipo' => $lastTransaction ? $lastTransaction->extrato_operacao_tipo : 'N/A',
                    'data_criacao' => $machine->data_criacao,
                ];
            });
    
            // Responder no formato esperado pelo DataTables
            return response()->json($result, 200);
    
        } catch (Exception $e) {
            return response()->json(['error' => 'Houve um erro ao tentar coletar os dados das máquinas.'], 500);
        }
    }
}
