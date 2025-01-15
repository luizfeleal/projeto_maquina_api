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
            DB::raw("DATE_FORMAT(extrato_maquina.data_criacao, '%d/%m/%Y %H:%i') as data_criacao") // Formatando a data
        )
        ->orderBy('extrato_maquina.data_criacao', 'desc'); // Ordenação padrão

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

        // Obter os parâmetros de ordenação
        $orderColumn = $request->get('order')[0]['column']; // Índice da coluna
        $orderDirection = $request->get('order')[0]['dir']; // Direção da ordenação (asc ou desc)

        // Definir as colunas para ordenar
        $columns = [
            'locais.local_nome',      // Coluna 0
            'maquinas.maquina_nome',   // Coluna 1
            'extrato_maquina.extrato_operacao',  // Coluna 2
            'extrato_maquina.extrato_operacao_tipo', // Coluna 3
            'extrato_maquina.data_criacao'  // Coluna 4
        ];

        // Ordenar a consulta
        $query->orderBy($columns[$orderColumn], $orderDirection);

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
        // Número de registros por página
        $perPage = $request->get('length', 10); 
        // Página atual
        $page = $request->get('start', 0) / $perPage + 1;
    
        // Query base com joins e COALESCE para totalizadores
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
    
        // Filtro de pesquisa
        $search = $request->get('search')['value']; // Valor da pesquisa do DataTables
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                // Adicione aqui as colunas que podem ser pesquisadas
                $q->where('locais.local_nome', 'like', "%$search%")
                  ->orWhere('maquinas.maquina_nome', 'like', "%$search%")
                  ->orWhere('maquinas.id_placa', 'like', "%$search%")
                  ->orWhere('maquinas.maquina_status', 'like', "%$search%");
            });
        }
    
        // Obter os parâmetros de ordenação
        $orderColumn = $request->get('order')[0]['column']; // Índice da coluna
        $orderDirection = $request->get('order')[0]['dir']; // Direção da ordenação (asc ou desc)

        // Definir as colunas para ordenar
        $columns = [
            'locais.local_nome',            // Coluna 0
            'maquinas.maquina_nome',         // Coluna 1
            'maquinas.id_placa',             // Coluna 2
            'maquinas.maquina_status',       // Coluna 3
            'total_maquina',                 // Coluna 4
            'total_pix',                     // Coluna 5
            'total_cartao',                  // Coluna 6
            'total_dinheiro'                 // Coluna 7
        ];

        // Aplicar ordenação na consulta
        $query->orderBy($columns[$orderColumn], $orderDirection);
    
        // Total de registros sem filtro
        $totalRecords = DB::table('maquinas')->count();
    
        // Total de registros filtrados
        $totalFiltered = $query->count();
    
        // Paginar os dados
        $extrato = $query->offset($request->get('start', 0))
                         ->limit($perPage)
                         ->get();
    
        // Responder no formato esperado pelo DataTables
        return response()->json([
            'data' => $extrato,
            'recordsTotal' => count($extrato), // Total de registros sem filtro
            'recordsFiltered' => count($extrato) // Total de registros após o filtro
        ], 200);
    } catch (Exception $e) {
        return response()->json([
            'error' => 'Houve um erro ao tentar coletar o extrato.',
            'message' => $e->getMessage()
        ], 500);
    }
}

public function acumulatedPerMachineFromLocal(Request $request)
{
    try {
        $idLocal = $request->id_local;

        // Base da consulta
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
            ->where('maquinas.id_local', $idLocal)
            ->whereNull('maquinas.deleted_at')
            ->groupBy('locais.local_nome', 'maquinas.maquina_nome', 'maquinas.id_placa', 'maquinas.maquina_status');

        // Pesquisa
        if ($search = $request->input('search.value')) {
            $query->where(function ($subQuery) use ($search) {
                $subQuery->orWhere('locais.local_nome', 'like', "%{$search}%")
                    ->orWhere('maquinas.maquina_nome', 'like', "%{$search}%")
                    ->orWhere('maquinas.id_placa', 'like', "%{$search}%");
            });
        }

        // Ordenação
        $columns = [
            'locais.local_nome',
            'maquinas.maquina_nome',
            'maquinas.id_placa',
            'maquinas.maquina_status',
            'total_maquina',
            'total_pix',
            'total_cartao',
            'total_dinheiro',
        ];

        if ($order = $request->input('order.0')) {
            $columnIndex = $order['column'];
            $direction = $order['dir']; // asc ou desc
            if (isset($columns[$columnIndex])) {
                $query->orderBy($columns[$columnIndex], $direction);
            }
        }

        // Total de registros para a contagem
        $totalRecords = $query->count();

        // Paginação
        $extrato = $query->offset($request->get('start', 0))
            ->limit($request->get('length', 10))
            ->get();

        // Responder no formato esperado pelo DataTables
        return response()->json([
            'data' => $extrato,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalRecords,
        ], 200);
    } catch (Exception $e) {
        return response()->json(['error' => 'Houve um erro ao tentar coletar o extrato.'], 500);
    }
}


public static function acumulatedPerMachineOfClient(Request $request)
{
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

        // Filtro de pesquisa
        $search = $request->get('search')['value']; // Valor da pesquisa do DataTables
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                // Adicione aqui as colunas que podem ser pesquisadas
                $q->where('locais.local_nome', 'like', "%$search%")
                  ->orWhere('maquinas.maquina_nome', 'like', "%$search%")
                  ->orWhere('maquinas.id_placa', 'like', "%$search%")
                  ->orWhere('maquinas.maquina_status', 'like', "%$search%");
            });
        }

        // Obter os parâmetros de ordenação
        $orderColumn = $request->get('order')[0]['column']; // Índice da coluna
        $orderDirection = $request->get('order')[0]['dir']; // Direção da ordenação (asc ou desc)

        // Definir as colunas para ordenar
        $columns = [
            'locais.local_nome',            // Coluna 0
            'maquinas.maquina_nome',         // Coluna 1
            'maquinas.id_placa',             // Coluna 2
            'maquinas.maquina_status',       // Coluna 3
            'total_maquina',                 // Coluna 4
            'total_pix',                     // Coluna 5
            'total_cartao',                  // Coluna 6
            'total_dinheiro'                 // Coluna 7
        ];

        // Aplicar ordenação na consulta
        $query->orderBy($columns[$orderColumn], $orderDirection);

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
            'recordsFiltered' => $totalRecords // Total de registros filtrados será igual ao total de registros
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
                ->where('maquinas.deleted_at', NULL)
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
                ->where('maquinas.deleted_at', NULL)
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

                // Filtro de pesquisa
        $search = $request->get('search')['value']; // Valor da pesquisa do DataTables
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
        $tipoTransacao = $request->input('tipo_transacao');
        $dataInicio = $request->input('data_inicio');
        $dataFim = $request->input('data_fim');
    
        return $request;
        // Iniciando a query
        $query = DB::table('extrato_maquina')
            ->join('maquinas', 'extrato_maquina.id_maquina', '=', 'maquinas.id_maquina')
            ->join('locais', 'maquinas.id_local', '=', 'locais.id_local')
            ->join('cliente_local', 'cliente_local.id_local', '=', 'locais.id_local')
            ->select(
                'cliente_local.id_cliente',
                'maquinas.maquina_nome',
                'extrato_maquina.extrato_operacao',
                'extrato_maquina.extrato_operacao_valor',
                'extrato_maquina.extrato_operacao_tipo',
                DB::raw("DATE_FORMAT(extrato_maquina.data_criacao, '%d/%m/%Y %H:%i') as data_criacao")
            )
            ->orderBy('extrato_maquina.data_criacao', 'desc');
    
        // Aplicando filtros para clientes
        if (!empty($clientes)) {
            $query->whereIn('cliente_local.id_cliente', $clientes);
        }
    
        // Aplicando filtros para máquinas
        if (!empty($maquinas)) {
            $query->whereIn('maquinas.id_maquina', $maquinas);
        }
    
        // Aplicando filtro de tipo de transação
        if ($tipoTransacao) {
            $query->where('extrato_maquina.extrato_operacao_tipo', $tipoTransacao);
        }
        if ($dataInicio) {

            // Converte para o formato 'Y-m-d 00:00:00' para comparar com a data do banco
            $dataInicioFormatada = \Carbon::createFromFormat('Y-m-d', $dataInicio)->startOfDay()->format('Y-m-d H:i:s');
            $query->where('extrato_maquina.data_criacao', '>=', $dataInicioFormatada);
        }
        
        if ($dataFim) {
            // Converte para o formato 'Y-m-d 23:59:59' para comparar com a data do banco
            $dataFimFormatada = \Carbon::createFromFormat('Y-m-d', $dataFim)->endOfDay()->format('Y-m-d H:i:s');
            $query->where('extrato_maquina.data_criacao', '<=', $dataFimFormatada);
        }
    
        // Adicionar busca global
        $search = $request->input('search.value', null); // Busca global
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->orWhere('cliente_local.id_cliente', 'LIKE', "%$search%")
                  ->orWhere('maquinas.maquina_nome', 'LIKE', "%$search%")
                  ->orWhere('extrato_maquina.extrato_operacao', 'LIKE', "%$search%")
                  ->orWhere('extrato_maquina.extrato_operacao_valor', 'LIKE', "%$search%")
                  ->orWhere('extrato_maquina.extrato_operacao_tipo', 'LIKE', "%$search%")
                  ->orWhere(DB::raw("DATE_FORMAT(extrato_maquina.data_criacao, '%d/%m/%Y %H:%i:%s')"), 'LIKE', "%$search%");
            });
        }
    
        // Obter os parâmetros de ordenação
        $order = $request->get('order', []);
        $orderColumn = isset($order[0]['column']) ? (int) $order[0]['column'] : 0;
        $orderDirection = isset($order[0]['dir']) ? $order[0]['dir'] : 'asc';

        // Definir as colunas para ordenar
        $columns = [
            'cliente_local.id_cliente',
            'maquinas.maquina_nome',
            'extrato_maquina.extrato_operacao',
            'extrato_maquina.extrato_operacao_valor',
            'extrato_maquina.extrato_operacao_tipo',
            DB::raw("DATE_FORMAT(extrato_maquina.data_criacao, '%Y-%m-%d %H:%i:%s')")
        ];

        $orderColumn = isset($columns[$orderColumn]) ? $orderColumn : 0;
        $query->orderBy($columns[$orderColumn], $orderDirection);
    
        // Total de registros após aplicar filtros e busca
        $totalRecords = $query->count();
    
        // Paginar os dados
        $extrato = $query->offset($request->get('start', 0))
                         ->limit($perPage)
                         ->get();
    
        // Responder no formato DataTables
        return response()->json([
            'data' => $extrato,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalRecords
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

    public function getTotal($id = null) {
        $query = DB::table('maquinas')
            ->leftJoin('extrato_maquina', 'maquinas.id_maquina', '=', 'extrato_maquina.id_maquina')
            ->leftJoin('locais', 'maquinas.id_local', '=', 'locais.id_local')
            ->leftJoin('cliente_local', 'locais.id_local', '=', 'cliente_local.id_local')
            ->select(DB::raw('
                COALESCE(SUM(
                    CASE 
                        WHEN extrato_maquina.extrato_operacao = "C" THEN extrato_maquina.extrato_operacao_valor
                        WHEN extrato_maquina.extrato_operacao = "D" THEN -extrato_maquina.extrato_operacao_valor
                        ELSE 0
                    END
                ), 0) as saldo_final
            '));
    
        // Aplica o filtro por cliente, se o $id for fornecido
        if (!is_null($id)) {
            $query->where('cliente_local.id_cliente', $id);
        }
    
        // Retorna o valor final diretamente
        $result = $query->first();
    
        return response()->json(["data" => $result->saldo_final], 200);
    }
    
    public function getTotalDevolucao($id = null) {
        $dataHoje = date('Y-m-d');
        $mesAtual = date('Y-m');
        $mesPassado = date('Y-m', strtotime('first day of last month'));
    
        // Define a base da consulta
        $query = DB::table('maquinas')
            ->leftJoin('extrato_maquina', 'maquinas.id_maquina', '=', 'extrato_maquina.id_maquina')
            ->leftJoin('locais', 'maquinas.id_local', '=', 'locais.id_local')
            ->leftJoin('cliente_local', 'locais.id_local', '=', 'cliente_local.id_local')
            ->where('extrato_maquina.extrato_operacao_tipo', 'Estorno');  // Condição para "Estorno"
    
        // Aplica o filtro por cliente, se o $id for fornecido
        if (!is_null($id)) {
            $query->where('cliente_local.id_cliente', $id);
        }
    
        // Calcula a soma para cada período
        $result = [
            'hoje' => (clone $query)->whereDate('extrato_maquina.data_criacao', $dataHoje)->sum('extrato_maquina.extrato_operacao_valor'),
            'mes_atual' => (clone $query)->where('extrato_maquina.data_criacao', '>=', $mesAtual . '-01')
                                         ->where('extrato_maquina.data_criacao', '<=', $mesAtual . '-31')
                                         ->sum('extrato_maquina.extrato_operacao_valor'),
            'mes_passado' => (clone $query)->where('extrato_maquina.data_criacao', '>=', $mesPassado . '-01')
                                           ->where('extrato_maquina.data_criacao', '<=', $mesPassado . '-31')
                                           ->sum('extrato_maquina.extrato_operacao_valor'),
        ];
    
        return response()->json($result, 200);
    }

    public function getTotalSaldo($id = null) {
        $dataHoje = date('Y-m-d');
        $mesAtual = date('Y-m');
        $mesPassado = date('Y-m', strtotime('first day of last month'));
    
        // Define a base da consulta
        $query = DB::table('maquinas')
            ->leftJoin('extrato_maquina', 'maquinas.id_maquina', '=', 'extrato_maquina.id_maquina')
            ->leftJoin('locais', 'maquinas.id_local', '=', 'locais.id_local')
            ->leftJoin('cliente_local', 'locais.id_local', '=', 'cliente_local.id_local')
            ->where('extrato_maquina.extrato_operacao', 'C');  // Condição para "Estorno"
    
        // Aplica o filtro por cliente, se o $id for fornecido
        if (!is_null($id)) {
            $query->where('cliente_local.id_cliente', $id);
        }
    
        // Calcula a soma para cada período
        $result = [
            'hoje' => (clone $query)->whereDate('extrato_maquina.data_criacao', $dataHoje)->sum('extrato_maquina.extrato_operacao_valor'),
            'mes_atual' => (clone $query)->where('extrato_maquina.data_criacao', '>=', $mesAtual . '-01')
                                         ->where('extrato_maquina.data_criacao', '<=', $mesAtual . '-31')
                                         ->sum('extrato_maquina.extrato_operacao_valor'),
            'mes_passado' => (clone $query)->where('extrato_maquina.data_criacao', '>=', $mesPassado . '-01')
                                           ->where('extrato_maquina.data_criacao', '<=', $mesPassado . '-31')
                                           ->sum('extrato_maquina.extrato_operacao_valor'),
        ];
    
        return response()->json($result, 200);
    }
}
