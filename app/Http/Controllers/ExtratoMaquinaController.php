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
    try {
        // Número de registros por página
        $perPage = $request->get('length', 10); 
        // Página atual
        $page = $request->get('start', 0) / $perPage + 1;

        // Query base com joins
        $query = DB::table('extrato_maquina')
            ->join('maquinas', 'extrato_maquina.id_maquina', '=', 'maquinas.id_maquina')
            ->join('locais', 'maquinas.id_local', '=', 'locais.id_local')
            ->select(
                'locais.local_nome',
                'maquinas.maquina_nome',
                'extrato_maquina.extrato_operacao',
                'extrato_maquina.extrato_operacao_valor',
                'extrato_maquina.extrato_operacao_tipo',
                'extrato_maquina.data_criacao'
            );

        // Filtro de pesquisa
        $search = $request->get('search'); // Valor da pesquisa do DataTables
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                // Adicione aqui as colunas que podem ser pesquisadas
                $q->where('locais.local_nome', 'like', "%$search%")
                  ->orWhere('maquinas.maquina_nome', 'like', "%$search%")
                  ->orWhere('extrato_maquina.extrato_operacao', 'like', "%$search%")
                  ->orWhere('extrato_maquina.extrato_operacao_tipo', 'like', "%$search%")
                  ->orWhere('extrato_maquina.extrato_operacao_valor', 'like', "%$search%")
                  ->orWhere('extrato_maquina.data_criacao', 'like', "%$search%");
            });
        }

        // Total de registros (sem filtro)
        $totalRecords = DB::table('extrato_maquina')
            ->join('maquinas', 'extrato_maquina.id_maquina', '=', 'maquinas.id_maquina')
            ->join('locais', 'maquinas.id_local', '=', 'locais.id_local')
            ->count();

        // Total de registros filtrados
        $totalFiltered = $query->count();

        // Paginar os dados
        $extrato = $query->offset($request->get('start', 0))
                         ->limit($perPage)
                         ->get();

        // Responder no formato esperado pelo DataTables
        return response()->json([
            'data' => $extrato,
            'recordsTotal' => $totalRecords, // Total de registros sem filtro
            'recordsFiltered' => $totalFiltered // Total de registros após o filtro
        ], 200);
    } catch (Exception $e) {
        return response()->json([
            'error' => 'Houve um erro ao tentar coletar o extrato.',
            'message' => $e->getMessage()
        ], 500);
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

    public static function acumulatedPerMachineOfClient(Request $request){
        try {
            // Ajusta a consulta para incluir todas as máquinas, mesmo sem registros de extrato
            $id_cliente = $request->input('id_cliente');
            $query = DB::table('maquinas')
                ->leftJoin('extrato_maquina', 'maquinas.id_maquina', '=', 'extrato_maquina.id_maquina')
                ->leftJoin('locais', 'maquinas.id_local', '=', 'locais.id_local')
                ->join('cliente_local', 'locais.id_local', '=', 'cliente_local.id_local') // Juntando locais com cliente_local
                ->where('cliente_local.id_cliente', $id_cliente)
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
    public function getTheLastTransactionPerMachineOfClient(Request $request)
    {
        try {
            $id_cliente = $request->input('id_cliente');
            //return $id_cliente;
            // 1. Recuperar todas as máquinas
            $machines = DB::table('maquinas')
            ->join('locais', 'maquinas.id_local', '=', 'locais.id_local') // Juntando máquinas com locais
            ->join('cliente_local', 'locais.id_local', '=', 'cliente_local.id_local') // Juntando locais com cliente_local
            ->where('cliente_local.id_cliente', $id_cliente) 
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

    public function indexClient(Request $request)
    {
        try{

            $id_cliente = $request->input('id_cliente');
            //$extrato = ExtratoMaquina::paginate(1000);
            // Pegando os parâmetros de paginação
            $perPage = $request->get('length', 10); // Número de registros por página
            $page = $request->get('start', 0) / $perPage + 1; // Página atual
        
            $query = DB::table('extrato_maquina')
                ->join('maquinas', 'extrato_maquina.id_maquina', '=', 'maquinas.id_maquina')
                ->join('locais', 'maquinas.id_local', '=', 'locais.id_local') // Relaciona a tabela locais com a tabela maquinas
                ->join('cliente_local', 'locais.id_local', '=', 'cliente_local.id_local') // Juntando locais com cliente_local
                ->where('cliente_local.id_cliente', $id_cliente) 
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
            $extrato = $query->get();
        
            // Responder no formato esperado pelo DataTables
            return response()->json([
                'data' => $extrato,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $totalRecords
            ], 200);
        }catch(Exception $e){
            return response()->json(500, 'Houve um erro ao tentar coletar o extrato.');
        }
    }

    public function generateReportAllTransactions(Request $request)
    {
        try {
            // Pegando os parâmetros de paginação
            $perPage = $request->get('length', 25200); // Número de registros por página
            $page = $request->get('start', 0) / $perPage + 1; // Página atual
        
            // Pegando os parâmetros de filtro
            $clientes = $request->input('id_cliente', []); // array de IDs de clientes
            $maquinas = $request->input('id_maquina', []); // array de IDs de máquinas
            $locais = $request->input('id_local', []); // array de IDs de locais
            $tipoTransacao = $request->input('tipo_transacao');
            $dataInicio = $request->input('data_inicio');
            $dataFim = $request->input('data_fim');
        
            // Iniciando a query
            $query = DB::table('extrato_maquina')
                ->join('maquinas', 'extrato_maquina.id_maquina', '=', 'maquinas.id_maquina')
                ->join('locais', 'maquinas.id_local', '=', 'locais.id_local')
                ->join('cliente_local', 'cliente_local.id_local', '=', 'locais.id_local')
                ->select(
                    'locais.local_nome',
                    'cliente_local.id_cliente',
                    'maquinas.maquina_nome',
                    'extrato_maquina.extrato_operacao',
                    'extrato_maquina.extrato_operacao_valor',
                    'extrato_maquina.extrato_operacao_tipo',
                    'extrato_maquina.data_criacao'
                );
        
            // Aplicando filtros para clientes (se múltiplos IDs foram passados)
            if (!empty($clientes)) {
                $query->whereIn('cliente_local.id_cliente', $clientes);
            }
        
            // Aplicando filtros para máquinas (se múltiplos IDs foram passados)
            if (!empty($maquinas)) {
                $query->whereIn('maquinas.id_maquina', $maquinas);
            }
        
            // Aplicando filtros para locais (se múltiplos IDs foram passados)
            if (!empty($locais)) {
                $query->whereIn('locais.id_local', $locais);
            }
        
            // Aplicando filtro de tipo de transação
            if ($tipoTransacao) {
                $query->where('extrato_maquina.extrato_operacao_tipo', $tipoTransacao);
            }
        
            // Aplicando filtro de data de início
            if ($dataInicio) {
                $query->where('extrato_maquina.data_criacao', '>=', $dataInicio . ' 00:00:00');
            }
        
            // Aplicando filtro de data de fim
            if ($dataFim) {
                $query->where('extrato_maquina.data_criacao', '<=', $dataFim . ' 23:59:59');
            }
        
            // Total de registros após aplicar os filtros
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
        
        } catch (Exception $e) {
            return response()->json(['error' => 'Houve um erro ao tentar coletar o extrato.'], 500);
        }
    }

    
    

    public function generateReportAllTransactionsGetTotal(Request $request)
    {
        try {
            // Pegando os parâmetros de filtro
            $perPage = $request->get('length', 10); // Número de registros por página
            $page = $request->get('start', 0) / $perPage + 1; // Página atual
        
            // Pegando os parâmetros de filtro
            $clientes = $request->input('id_cliente', []); // array de IDs de clientes
            $maquinas = $request->input('id_maquina', []); // array de IDs de máquinas
            $locais = $request->input('id_local', []); // array de IDs de locais
            $tipoTransacao = $request->input('tipo_transacao');
            $dataInicio = $request->input('data_inicio');
            $dataFim = $request->input('data_fim');
        
            // Iniciando a query
            $query = DB::table('extrato_maquina')
                ->join('maquinas', 'extrato_maquina.id_maquina', '=', 'maquinas.id_maquina')
                ->join('locais', 'maquinas.id_local', '=', 'locais.id_local')
                ->join('cliente_local', 'cliente_local.id_local', '=', 'locais.id_local')
                ->select(
                    'locais.local_nome',
                    'cliente_local.id_cliente',
                    'maquinas.maquina_nome',
                    'extrato_maquina.extrato_operacao',
                    'extrato_maquina.extrato_operacao_valor',
                    'extrato_maquina.extrato_operacao_tipo',
                    'extrato_maquina.data_criacao'
                );
        
            // Aplicando filtros para clientes (se múltiplos IDs foram passados)
            if (!empty($clientes)) {
                $query->whereIn('cliente_local.id_cliente', $clientes);
            }
        
            // Aplicando filtros para máquinas (se múltiplos IDs foram passados)
            if (!empty($maquinas)) {
                $query->whereIn('maquinas.id_maquina', $maquinas);
            }
        
            // Aplicando filtros para locais (se múltiplos IDs foram passados)
            if (!empty($locais)) {
                $query->whereIn('locais.id_local', $locais);
            }
        
            // Aplicando filtro de tipo de transação
            if ($tipoTransacao) {
                $query->where('extrato_maquina.extrato_operacao_tipo', $tipoTransacao);
            }
        
            // Aplicando filtro de data de início
            if ($dataInicio) {
                $query->where('extrato_maquina.data_criacao', '>=', $dataInicio . ' 00:00:00');
            }
        
            // Aplicando filtro de data de fim
            if ($dataFim) {
                $query->where('extrato_maquina.data_criacao', '<=', $dataFim . ' 23:59:59');
            }
        
            // Executando a query para obter os dados
            $resultados = $query->get();
        
            // Tipos de transação definidos (padronizados para minúsculas)
            $tiposDefinidos = ['estorno', 'pix', 'cartão', 'dinheiro'];
        
            // Calculando o total de extrato_operacao_valor por categoria de extrato_operacao_tipo
            $totaisPorTipo = $resultados->groupBy(function ($item) {
                // Padronizando o tipo de operação para minúsculas
                return strtolower($item->extrato_operacao_tipo);
            })->map(function ($items, $tipo) {
                // Somando os valores para o tipo específico, ignorando valores nulos
                $total = $items->sum(function ($item) {
                    return $item->extrato_operacao_valor ?? 0; // Caso seja nulo, considerar 0
                });
        
                return [
                    'tipo' => ucfirst($tipo), // Retorna o tipo com a primeira letra maiúscula para consistência
                    'total' => $total,
                ];
            })->values();
        
            // Garantindo que todos os tipos definidos estejam no resultado com zero se não existirem
            foreach ($tiposDefinidos as $tipo) {
                if (!$totaisPorTipo->contains('tipo', ucfirst($tipo))) {
                    $totaisPorTipo->push([
                        'tipo' => ucfirst($tipo),
                        'total' => 0,
                    ]);
                }
            }
        
            // Ordenando os tipos definidos na ordem desejada
            $totaisPorTipo = $totaisPorTipo->sortBy(function ($item) use ($tiposDefinidos) {
                return array_search(strtolower($item['tipo']), $tiposDefinidos);
            })->values();
        
            // Retorno dos resultados
            return response()->json($totaisPorTipo, 200);
        
        } catch (Exception $e) {
            return response()->json(['error' => 'Houve um erro ao tentar coletar o extrato.'], 500);
        }
        
    }

    public function generateReportAllTransactionsTax(Request $request)
    {
        try {
        
            // Pegando os parâmetros de filtro
            $clientes = $request->input('id_cliente', []); // array de IDs de clientes
            $maquinas = $request->input('id_maquina', []); // array de IDs de máquinas
            $locais = $request->input('id_local', []); // array de IDs de locais
            $tipoTransacao = "Taxa";
            $dataInicio = $request->input('data_inicio');
            $dataFim = $request->input('data_fim');
        
            // Iniciando a query
            $query = DB::table('extrato_maquina')
                ->join('maquinas', 'extrato_maquina.id_maquina', '=', 'maquinas.id_maquina')
                ->join('locais', 'maquinas.id_local', '=', 'locais.id_local')
                ->join('cliente_local', 'cliente_local.id_local', '=', 'locais.id_local')
                ->select(
                    'locais.local_nome',
                    'cliente_local.id_cliente',
                    'maquinas.maquina_nome',
                    'extrato_maquina.extrato_operacao',
                    'extrato_maquina.extrato_operacao_valor',
                    'extrato_maquina.extrato_operacao_tipo',
                    'extrato_maquina.data_criacao'
                );
        
            // Aplicando filtros para clientes (se múltiplos IDs foram passados)
            if (!empty($clientes)) {
                $query->whereIn('cliente_local.id_cliente', $clientes);
            }
        
            // Aplicando filtros para máquinas (se múltiplos IDs foram passados)
            if (!empty($maquinas)) {
                $query->whereIn('maquinas.id_maquina', $maquinas);
            }
        
            // Aplicando filtros para locais (se múltiplos IDs foram passados)
            if (!empty($locais)) {
                $query->whereIn('locais.id_local', $locais);
            }
        
            // Aplicando filtro de tipo de transação
            if ($tipoTransacao) {
                $query->where('extrato_maquina.extrato_operacao_tipo', $tipoTransacao);
            }
        
            // Aplicando filtro de data de início
            if ($dataInicio) {
                $query->where('extrato_maquina.data_criacao', '>=', $dataInicio . ' 00:00:00');
            }
        
            // Aplicando filtro de data de fim
            if ($dataFim) {
                $query->where('extrato_maquina.data_criacao', '<=', $dataFim . ' 23:59:59');
            }
        
            // Total de registros após aplicar os filtros
            $totalRecords = $query->count();
        
            // Paginar os dados
            $extrato = $query->get();
        
            // Responder no formato esperado pelo DataTables
            return response()->json([
                'data' => $extrato,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $totalRecords // Se houver filtros, você deve atualizar isso para refletir o número filtrado
            ], 200);
        
        } catch (Exception $e) {
            return response()->json(['error' => 'Houve um erro ao tentar coletar o extrato.'], 500);
        }
    }

    public function generateReportTaxTransactionsGetTotal(Request $request)
    {
        try {
            // Pegando os parâmetros de paginação
            $perPage = $request->get('length', 10); // Número de registros por página
            $page = $request->get('start', 0) / $perPage + 1; // Página atual
        
            // Pegando os parâmetros de filtro
            $clientes = $request->input('id_cliente', []); // array de IDs de clientes
            $maquinas = $request->input('id_maquina', []); // array de IDs de máquinas
            $locais = $request->input('id_local', []); // array de IDs de locais
            $tipoTransacao = "Taxa";
            $dataInicio = $request->input('data_inicio');
            $dataFim = $request->input('data_fim');
        
            // Iniciando a query
            $query = DB::table('extrato_maquina')
                ->join('maquinas', 'extrato_maquina.id_maquina', '=', 'maquinas.id_maquina')
                ->join('locais', 'maquinas.id_local', '=', 'locais.id_local')
                ->join('cliente_local', 'cliente_local.id_local', '=', 'locais.id_local')
                ->select(
                    'locais.local_nome',
                    'cliente_local.id_cliente',
                    'maquinas.maquina_nome',
                    'extrato_maquina.extrato_operacao',
                    'extrato_maquina.extrato_operacao_valor',
                    'extrato_maquina.extrato_operacao_tipo',
                    'extrato_maquina.data_criacao'
                );
        
            // Aplicando filtros para clientes (se múltiplos IDs foram passados)
            if (!empty($clientes)) {
                $query->whereIn('cliente_local.id_cliente', $clientes);
            }
        
            // Aplicando filtros para máquinas (se múltiplos IDs foram passados)
            if (!empty($maquinas)) {
                $query->whereIn('maquinas.id_maquina', $maquinas);
            }
        
            // Aplicando filtros para locais (se múltiplos IDs foram passados)
            if (!empty($locais)) {
                $query->whereIn('locais.id_local', $locais);
            }
        
            // Aplicando filtro de tipo de transação
            if ($tipoTransacao) {
                $query->where('extrato_maquina.extrato_operacao_tipo', $tipoTransacao);
            }
        
            // Aplicando filtro de data de início
            if ($dataInicio) {
                $query->where('extrato_maquina.data_criacao', '>=', $dataInicio . ' 00:00:00');
            }
        
            // Aplicando filtro de data de fim
            if ($dataFim) {
                $query->where('extrato_maquina.data_criacao', '<=', $dataFim . ' 23:59:59');
            }
        
            // Total de registros após aplicar os filtros
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
        
        } catch (Exception $e) {
            return response()->json(['error' => 'Houve um erro ao tentar coletar o extrato.'], 500);
        }
    }
}
