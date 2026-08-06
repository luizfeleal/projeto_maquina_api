<?php

namespace App\Http\Controllers;

use App\Models\ExtratoMaquina;
use App\Models\Maquinas;
use App\Models\Locais;
use App\Models\Clientes;
use App\Models\QrCode;
use App\Services\MaquinaResetParcialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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
            'maquinas.id_maquina',
            'locais.local_nome',
            'maquinas.maquina_nome',
            'extrato_maquina.extrato_operacao',
            'extrato_maquina.extrato_operacao_valor',
            'extrato_maquina.extrato_operacao_tipo',
            DB::raw("DATE_FORMAT(extrato_maquina.data_criacao, '%d/%m/%Y %H:%i') as data_criacao")
        );

        $this->aplicarFiltroDataExtrato($query, $request);
        $this->aplicarFiltroTipoOperacao($query, $request);
        $this->aplicarFiltroMaquinaLocalCliente($query, $request);
        $this->aplicarFiltroTaxa($query, $request);

        // Filtro de pesquisa
        $search = $request->input('search.value') ?? $request->input('search');
        if (is_array($search)) {
            $search = $search['value'] ?? null;
        }
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('locais.local_nome', 'like', "%$search%")
                  ->orWhere('maquinas.maquina_nome', 'like', "%$search%")
                  ->orWhere('extrato_maquina.extrato_operacao', 'like', "%$search%")
                  ->orWhere('extrato_maquina.extrato_operacao_tipo', 'like', "%$search%")
                  ->orWhere('extrato_maquina.extrato_operacao_valor', 'like', "%$search%")
                  ->orWhere('extrato_maquina.data_criacao', 'like', "%$search%");
            });
        }

        // Total de registros (sem filtro). O DataTables só usa esse número para
        // exibir "de X registros"; ele varre a tabela inteira e não muda entre a
        // digitação de uma busca e a troca de página, então um cache curto tira
        // uma varredura completa de cada requisição sem defasagem perceptível.
        $totalRecords = Cache::remember('extrato_maquina_total_registros', now()->addSeconds(60), function () {
            return DB::table('extrato_maquina')
                ->join('maquinas', 'extrato_maquina.id_maquina', '=', 'maquinas.id_maquina')
                ->join('locais', 'maquinas.id_local', '=', 'locais.id_local')
                ->count();
        });

        // Total de registros filtrados
        $totalFiltered = (clone $query)->count();

        // Obter os parâmetros de ordenação
        $order = $request->get('order', [['column' => 4, 'dir' => 'desc']]);
        $orderColumn = $order[0]['column'] ?? 4;
        $orderDirection = $order[0]['dir'] ?? 'desc';

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
        $perPage = max((int) $request->get('length', 10), 1);

        $query = DB::table('maquinas')
            ->leftJoin('extrato_maquina', 'maquinas.id_maquina', '=', 'extrato_maquina.id_maquina')
            ->leftJoin('locais', 'maquinas.id_local', '=', 'locais.id_local')
            ->leftJoinSub(
                MaquinaResetParcialService::subqueryUltimoReset(),
                'ultimo_reset_por_maquina',
                fn ($join) => $join->on('ultimo_reset_por_maquina.id_maquina', '=', 'maquinas.id_maquina')
            )
            ->whereNull('maquinas.deleted_at')
            ->select(
                'maquinas.id_maquina',
                'locais.local_nome',
                'maquinas.maquina_nome',
                'maquinas.id_placa',
                'maquinas.maquina_status',
                'maquinas.maquina_ultima_coleta',
                'maquinas.maquina_ultimo_contato',
                'maquinas.bloqueio_jogada_efi',
                'maquinas.bloqueio_jogada_pagbank',
                DB::raw('ultimo_reset_por_maquina.ultimo_reset as data_ultimo_reset'),
                DB::raw(MaquinaResetParcialService::exprSaldoPeriodo() . ' as saldo_periodo_calc'),
                DB::raw('COALESCE(SUM(extrato_maquina.extrato_operacao_valor), 0) as total_maquina'),
                DB::raw('COALESCE(SUM(CASE WHEN extrato_maquina.extrato_operacao_tipo = "PIX" THEN extrato_maquina.extrato_operacao_valor ELSE 0 END), 0) as total_pix'),
                DB::raw('COALESCE(SUM(CASE WHEN extrato_maquina.extrato_operacao_tipo = "Cartão" THEN extrato_maquina.extrato_operacao_valor ELSE 0 END), 0) as total_cartao'),
                DB::raw('COALESCE(SUM(CASE WHEN extrato_maquina.extrato_operacao_tipo = "Dinheiro" THEN extrato_maquina.extrato_operacao_valor ELSE 0 END), 0) as total_dinheiro')
            )
            ->groupBy(
                'maquinas.id_maquina',
                'locais.local_nome',
                'maquinas.maquina_nome',
                'maquinas.id_placa',
                'maquinas.maquina_status',
                'maquinas.maquina_ultima_coleta',
                'maquinas.maquina_ultimo_contato',
                'maquinas.bloqueio_jogada_efi',
                'maquinas.bloqueio_jogada_pagbank',
                'ultimo_reset_por_maquina.ultimo_reset'
            );

        if ($idCliente = $request->input('id_cliente')) {
            $query->join('cliente_local', 'locais.id_local', '=', 'cliente_local.id_local')
                ->where('cliente_local.id_cliente', $idCliente);
        }

        $search = $request->input('search.value') ?? $request->input('search_value');
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('locais.local_nome', 'like', "%{$search}%")
                  ->orWhere('maquinas.maquina_nome', 'like', "%{$search}%")
                  ->orWhere('maquinas.id_placa', 'like', "%{$search}%")
                  ->orWhere('maquinas.maquina_status', 'like', "%{$search}%");
            });
        }

        $order = $request->get('order', [['column' => 4, 'dir' => 'desc']]);
        $orderColumn = $order[0]['column'] ?? 4;
        $orderDirection = $order[0]['dir'] ?? 'desc';

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

        if (isset($columns[$orderColumn])) {
            $query->orderBy($columns[$orderColumn], $orderDirection);
        }

        $totalRecords = DB::table('maquinas')->whereNull('deleted_at')->count();
        $totalFiltered = DB::query()->fromSub($query, 'acumulado')->count();

        $extrato = $query->offset((int) $request->get('start', 0))
                         ->limit($perPage)
                         ->get();

        $extrato = MaquinaResetParcialService::enrichAcumuladoCollection($extrato);

        $extrato = $extrato->map(fn ($item) => $this->appendStatusComunicacao($item));

        return response()->json([
            'data' => $extrato,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalFiltered,
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
            ->leftJoinSub(
                MaquinaResetParcialService::subqueryUltimoReset(),
                'ultimo_reset_por_maquina',
                fn ($join) => $join->on('ultimo_reset_por_maquina.id_maquina', '=', 'maquinas.id_maquina')
            )
            ->where('cliente_local.id_cliente', $id_cliente)
            ->select(
                'maquinas.id_maquina',
                'locais.local_nome',
                'maquinas.maquina_nome',
                'maquinas.id_placa',
                'maquinas.maquina_status',
                'maquinas.maquina_ultima_coleta',
                'maquinas.maquina_ultimo_contato',
                'maquinas.bloqueio_jogada_efi',
                'maquinas.bloqueio_jogada_pagbank',
                DB::raw('ultimo_reset_por_maquina.ultimo_reset as data_ultimo_reset'),
                DB::raw(MaquinaResetParcialService::exprSaldoPeriodo() . ' as saldo_periodo_calc'),
                DB::raw('COALESCE(SUM(extrato_maquina.extrato_operacao_valor), 0) as total_maquina'),
                DB::raw('COALESCE(SUM(CASE WHEN extrato_maquina.extrato_operacao_tipo = "PIX" THEN extrato_maquina.extrato_operacao_valor ELSE 0 END), 0) as total_pix'),
                DB::raw('COALESCE(SUM(CASE WHEN extrato_maquina.extrato_operacao_tipo = "Cartão" THEN extrato_maquina.extrato_operacao_valor ELSE 0 END), 0) as total_cartao'),
                DB::raw('COALESCE(SUM(CASE WHEN extrato_maquina.extrato_operacao_tipo = "Dinheiro" THEN extrato_maquina.extrato_operacao_valor ELSE 0 END), 0) as total_dinheiro')
            )
            ->groupBy(
                'maquinas.id_maquina',
                'locais.local_nome',
                'maquinas.maquina_nome',
                'maquinas.id_placa',
                'maquinas.maquina_status',
                'maquinas.maquina_ultima_coleta',
                'maquinas.maquina_ultimo_contato',
                'maquinas.bloqueio_jogada_efi',
                'maquinas.bloqueio_jogada_pagbank',
                'ultimo_reset_por_maquina.ultimo_reset'
            );

        // Filtro de pesquisa
        $search = $request->input('search.value') ?? $request->input('search_value');
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('locais.local_nome', 'like', "%{$search}%")
                  ->orWhere('maquinas.maquina_nome', 'like', "%{$search}%")
                  ->orWhere('maquinas.id_placa', 'like', "%{$search}%")
                  ->orWhere('maquinas.maquina_status', 'like', "%{$search}%");
            });
        }

        $order = $request->get('order', [['column' => 4, 'dir' => 'desc']]);
        $orderColumn = $order[0]['column'] ?? 4;
        $orderDirection = $order[0]['dir'] ?? 'desc';

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

        if (isset($columns[$orderColumn])) {
            $query->orderBy($columns[$orderColumn], $orderDirection);
        }

        // Total de registros para a contagem
        $totalRecords = DB::table('maquinas')->count();

        // Paginar os dados
        $extrato = $query->offset($request->get('start', 0))
                         ->limit($request->get('length', 10))
                         ->get();

        $extrato = MaquinaResetParcialService::enrichAcumuladoCollection($extrato);

        $extrato = $extrato->map(fn ($item) => $this->appendStatusComunicacao($item));

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
            $result = $this->queryMachinesWithLastTransaction()
                ->get()
                ->map(fn ($row) => $this->formatMachineLastTransactionRow($row));

            return response()->json($result->values(), 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Houve um erro ao tentar coletar os dados das máquinas.'], 500);
        }
    }

    public function getTheLastTransactionPerMachineOfClient(Request $request)
    {
        try {
            $id_cliente = $request->input('id_cliente');

            $result = $this->queryMachinesWithLastTransaction($id_cliente)
                ->get()
                ->map(fn ($row) => $this->formatMachineLastTransactionRow($row, useMachineCreationDate: true));

            return response()->json($result->values(), 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Houve um erro ao tentar coletar os dados das máquinas.'], 500);
        }
    }

    private function queryMachinesWithLastTransaction(?int $idCliente = null)
    {
        $lastTransactionsSub = DB::table('extrato_maquina as em')
            ->joinSub(
                DB::table('extrato_maquina')
                    ->select('id_maquina', DB::raw('MAX(id_extrato_maquina) as max_id'))
                    ->groupBy('id_maquina'),
                'latest',
                function ($join) {
                    $join->on('em.id_extrato_maquina', '=', 'latest.max_id');
                }
            )
            ->select(
                'em.id_maquina',
                'em.extrato_operacao',
                'em.extrato_operacao_valor',
                'em.extrato_operacao_tipo',
                'em.data_criacao as extrato_data_criacao'
            );

        $query = DB::table('maquinas as m')
            ->join('locais as l', 'm.id_local', '=', 'l.id_local')
            ->leftJoinSub($lastTransactionsSub, 'last_em', function ($join) {
                $join->on('m.id_maquina', '=', 'last_em.id_maquina');
            })
            ->whereNull('m.deleted_at')
            ->select(
                'm.id_maquina',
                'm.id_local',
                'm.maquina_nome',
                'm.maquina_status',
                'm.data_criacao as maquina_data_criacao',
                'l.local_nome',
                'last_em.extrato_operacao',
                'last_em.extrato_operacao_valor',
                'last_em.extrato_operacao_tipo',
                'last_em.extrato_data_criacao'
            )
            ->orderBy('l.local_nome')
            ->orderBy('m.maquina_nome');

        if ($idCliente !== null) {
            $query->join('cliente_local as cl', 'l.id_local', '=', 'cl.id_local')
                ->where('cl.id_cliente', $idCliente);
        }

        return $query;
    }

    private function formatMachineLastTransactionRow(object $row, bool $useMachineCreationDate = false): array
    {
        return [
            'id_local' => $row->id_local,
            'id_maquina' => $row->id_maquina,
            'local_nome' => $row->local_nome,
            'maquina_nome' => $row->maquina_nome,
            'maquina_status' => $row->maquina_status,
            'extrato_operacao' => $row->extrato_operacao ?? 'N/A',
            'extrato_operacao_valor' => $row->extrato_operacao_valor ?? 0,
            'extrato_operacao_tipo' => $row->extrato_operacao_tipo ?? 'N/A',
            'data_criacao' => $useMachineCreationDate
                ? ($row->maquina_data_criacao ?? null)
                : ($row->extrato_data_criacao ?? null),
        ];
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
        $tipoTransacao = $request->input('tipo_transacao'); // tipo de transação
        $dataInicio = $request->input('data_inicio');
        $dataFim = $request->input('data_fim');
    
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
            $dataInicioFormatada = Carbon::createFromFormat('Y-m-d', $dataInicio)->startOfDay()->format('Y-m-d H:i:s');
            $query->where('extrato_maquina.data_criacao', '>=', $dataInicioFormatada);
        }
        
        if ($dataFim) {
            // Converte para o formato 'Y-m-d 23:59:59' para comparar com a data do banco
            $dataFimFormatada = Carbon::createFromFormat('Y-m-d', $dataFim)->endOfDay()->format('Y-m-d H:i:s');
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
            ->select(DB::raw('
                COALESCE(SUM(
                    CASE
                        WHEN extrato_maquina.extrato_operacao = "C" THEN extrato_maquina.extrato_operacao_valor
                        WHEN extrato_maquina.extrato_operacao = "D" THEN -extrato_maquina.extrato_operacao_valor
                        ELSE 0
                    END
                ), 0) as saldo_final
            '));

        // Join com cliente_local só entra quando filtramos por cliente: um local pode ter mais
        // de um cliente associado, e um JOIN incondicional duplica cada transação por cliente
        // vinculado ao local, inflando o SUM (contagem em dobro/triplo).
        if (!is_null($id)) {
            $query->join('cliente_local', 'locais.id_local', '=', 'cliente_local.id_local')
                ->where('cliente_local.id_cliente', $id);
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
            ->where('extrato_maquina.extrato_operacao_tipo', 'Estorno');  // Condição para "Estorno"

        // Join com cliente_local só entra quando filtramos por cliente (ver getTotalSaldo acima
        // para o motivo: JOIN incondicional duplica transações de locais com múltiplos clientes).
        if (!is_null($id)) {
            $query->join('cliente_local', 'locais.id_local', '=', 'cliente_local.id_local')
                ->where('cliente_local.id_cliente', $id);
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
            ->where('extrato_maquina.extrato_operacao', 'C');  // Condição para "Estorno"

        // Join com cliente_local só entra quando filtramos por cliente: um local pode ter mais
        // de um cliente associado, e um JOIN incondicional duplica cada transação por cliente
        // vinculado ao local, inflando o SUM (contagem em dobro/triplo). Era isso que fazia o
        // saldo do período aparecer contado duas vezes no dashboard.
        if (!is_null($id)) {
            $query->join('cliente_local', 'locais.id_local', '=', 'cliente_local.id_local')
                ->where('cliente_local.id_cliente', $id);
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

    /**
     * Resumo consolidado da Home do admin: reúne numa única resposta tudo que
     * antes exigia 7 chamadas HTTP sequenciais do front (saldo, devoluções,
     * máquinas, locais, clientes, qr codes, acumulado por máquina) mais um
     * loop paginado baixando TODAS as transações pra somar em PHP.
     *
     * Reaproveita as mesmas queries/endpoints já usados individualmente
     * (getTotal, getTotalDevolucao, acumulatedPerMachine, Model::all()) pra
     * garantir os mesmos números de sempre. A única coisa nova é substituir
     * o download de todas as transações por agregações feitas no banco
     * (últimas 15 transações + totais por tipo/mês), preservando exatamente
     * as mesmas regras de classificação (PIX/Cartão/Dinheiro/Devolução) que
     * o front já aplicava.
     */
    public function resumoHome(Request $request)
    {
        try {
            $idMaquina = $request->input('id_maquina');

            // O front usa /extrato/saldo (getTotalSaldo), NÃO /extrato/total (getTotal) —
            // são cálculos diferentes (saldo por período vs. saldo líquido C-D acumulado).
            $saldo = json_decode($this->getTotalSaldo()->getContent(), true);
            $devolucoes = json_decode($this->getTotalDevolucao()->getContent(), true);

            $acumuladoRequest = Request::create('/extrato/acumulado', 'GET', [
                'length' => 5000,
                'start' => 0,
                'order' => [['column' => 4, 'dir' => 'desc']],
            ]);
            $acumuladoData = json_decode($this->acumulatedPerMachine($acumuladoRequest)->getContent(), true)['data'] ?? [];

            // Só as colunas que a Home realmente usa. Em especial qr_code.qr_image
            // é um longtext com o PNG do QR em base64, e a Home só precisa saber
            // se existe QR ativo — trazer a imagem de todas as máquinas enchia a
            // resposta com megabytes de payload inútil.
            $maquinas = DB::table('maquinas')
                ->whereNull('deleted_at')
                ->select('id_maquina', 'id_local', 'maquina_nome', 'id_placa', 'maquina_status')
                ->get();

            $locais = DB::table('locais')
                ->whereNull('deleted_at')
                ->select('id_local', 'local_nome')
                ->get();

            // Clientes não usa SoftDeletes no model, então segue sem filtro de
            // deleted_at para manter exatamente a mesma lista de antes.
            $clientes = DB::table('clientes')
                ->select('id_cliente', 'cliente_nome')
                ->get();

            $qrCodes = DB::table('qr_code')
                ->whereNull('deleted_at')
                ->where('ativo', 1)
                ->select('id_maquina', 'ativo')
                ->get();

            $baseQuery = DB::table('extrato_maquina')
                ->join('maquinas', 'extrato_maquina.id_maquina', '=', 'maquinas.id_maquina')
                ->join('locais', 'maquinas.id_local', '=', 'locais.id_local');

            if (!empty($idMaquina)) {
                $baseQuery->where('extrato_maquina.id_maquina', $idMaquina);
            }

            // Últimas 15 transações (mesmas colunas/formato do index()), pra exibição.
            $ultimasTransacoes = (clone $baseQuery)
                ->select(
                    'maquinas.id_maquina',
                    'locais.local_nome',
                    'maquinas.maquina_nome',
                    'extrato_maquina.extrato_operacao',
                    'extrato_maquina.extrato_operacao_valor',
                    'extrato_maquina.extrato_operacao_tipo',
                    DB::raw("DATE_FORMAT(extrato_maquina.data_criacao, '%d/%m/%Y %H:%i') as data_criacao")
                )
                ->orderBy('extrato_maquina.data_criacao', 'desc')
                ->limit(15)
                ->get();

            // Totais por tipo/mês, no banco. extrato_operacao NULL é tratado como "C"
            // (não-devolução), igual ao `$tx['extrato_operacao'] ?? 'C'` do front.
            $totaisPorTipoMes = (clone $baseQuery)
                ->whereRaw("COALESCE(extrato_maquina.extrato_operacao, 'C') != 'D'")
                ->select(
                    DB::raw('YEAR(extrato_maquina.data_criacao) as ano'),
                    DB::raw('MONTH(extrato_maquina.data_criacao) as mes'),
                    DB::raw('LOWER(extrato_maquina.extrato_operacao_tipo) as tipo'),
                    DB::raw('SUM(extrato_maquina.extrato_operacao_valor) as total')
                )
                ->groupBy('ano', 'mes', 'tipo')
                ->get();

            $totalDevolucaoFiltro = (float) (clone $baseQuery)
                ->where('extrato_maquina.extrato_operacao', 'D')
                ->sum('extrato_maquina.extrato_operacao_valor');

            return response()->json([
                'saldo' => $saldo,
                'devolucoes' => $devolucoes,
                'maquinas' => $maquinas,
                'locais' => $locais,
                'clientes' => $clientes,
                'qr_codes' => $qrCodes,
                'acumulado' => $acumuladoData,
                'ultimas_transacoes' => $ultimasTransacoes,
                'totais_por_tipo_mes' => $totaisPorTipoMes,
                'total_devolucao_filtro' => $totalDevolucaoFiltro,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Houve um erro ao tentar coletar o resumo da home.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resumo consolidado da Home do cliente.
     *
     * Mesma ideia do resumoHome do admin: junta numa resposta só o que o painel
     * buscava em 7 chamadas HTTP sequenciais (saldo, devoluções, cliente_local,
     * máquinas, locais, acumulado, QR codes) mais a lista de transações.
     *
     * O conteúdo de cada bloco é idêntico ao dos endpoints individuais — inclusive
     * `transacoes`, que reusa queryMachinesWithLastTransaction() e portanto segue
     * trazendo a última transação de cada máquina, como antes. A montagem dos
     * totais e do gráfico continua no painel, sobre exatamente os mesmos dados.
     */
    public function resumoHomeCliente(Request $request)
    {
        try {
            $idCliente = $request->input('id_cliente');

            if (empty($idCliente)) {
                return response()->json(['error' => 'id_cliente é obrigatório.'], 400);
            }

            $saldo = json_decode($this->getTotalSaldo($idCliente)->getContent(), true);
            $devolucoes = json_decode($this->getTotalDevolucao($idCliente)->getContent(), true);

            $acumuladoRequest = Request::create('/extrato/acumulado', 'GET', [
                'id_cliente' => $idCliente,
                'length' => 5000,
                'start' => 0,
                'order' => [['column' => 4, 'dir' => 'desc']],
            ]);
            $acumuladoData = json_decode($this->acumulatedPerMachine($acumuladoRequest)->getContent(), true)['data'] ?? [];

            // Locais do cliente — base para filtrar máquinas e locais.
            $idsLocais = DB::table('cliente_local')
                ->where('id_cliente', $idCliente)
                ->pluck('id_local')
                ->all();

            $maquinas = DB::table('maquinas')
                ->whereNull('deleted_at')
                ->whereIn('id_local', $idsLocais ?: [0])
                ->select('id_maquina', 'id_local', 'maquina_nome', 'id_placa', 'maquina_status')
                ->get();

            $locais = DB::table('locais')
                ->whereNull('deleted_at')
                ->whereIn('id_local', $idsLocais ?: [0])
                ->select('id_local', 'local_nome')
                ->get();

            // Só o suficiente para saber se a máquina tem QR ativo: qr_image é um
            // longtext com o PNG em base64 e não é usado nesta tela.
            $qrCodes = DB::table('qr_code')
                ->whereNull('deleted_at')
                ->where('ativo', 1)
                ->select('id_maquina', 'ativo')
                ->get();

            $transacoes = $this->queryMachinesWithLastTransaction((int) $idCliente)
                ->get()
                ->map(fn ($row) => $this->formatMachineLastTransactionRow($row, useMachineCreationDate: true))
                ->values();

            return response()->json([
                'saldo' => $saldo,
                'devolucoes' => $devolucoes,
                'maquinas' => $maquinas,
                'locais' => $locais,
                'qr_codes' => $qrCodes,
                'acumulado' => $acumuladoData,
                'transacoes' => $transacoes,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Houve um erro ao tentar coletar o resumo da home do cliente.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Totais da tela de Extrato (admin e cliente), agregados no banco.
     *
     * Antes o painel baixava TODAS as transações que batiam no filtro — em lotes
     * paginados de 2000, até 200 mil registros — só para somar seis números em
     * PHP. Aqui é um SELECT com agregação: um roundtrip, resposta de bytes.
     *
     * Usa exatamente os mesmos helpers de filtro do index(), então os totais
     * passam a bater sempre com o que a tabela lista (antes o filtro do topo era
     * feito em PHP e o da tabela em SQL, o que podia divergir).
     *
     * A classificação PIX/Cartão/Dinheiro replica a cadeia if/elseif do front:
     * devolução ganha de tudo, depois PIX, depois Cartão, depois Dinheiro.
     */
    public function resumoTransacoes(Request $request)
    {
        try {
            $naoDevolucao = "COALESCE(extrato_maquina.extrato_operacao, 'C') <> 'D'";
            $ehPix        = "LOWER(extrato_maquina.extrato_operacao_tipo) LIKE '%pix%'";
            $ehCartao     = "LOWER(extrato_maquina.extrato_operacao_tipo) LIKE '%cart%'";
            $ehDinheiro   = "(LOWER(extrato_maquina.extrato_operacao_tipo) LIKE '%dinheir%'
                           OR LOWER(extrato_maquina.extrato_operacao_tipo) LIKE '%fisic%'
                           OR LOWER(extrato_maquina.extrato_operacao_tipo) LIKE '%físic%')";
            $valor        = 'extrato_maquina.extrato_operacao_valor';

            $query = DB::table('extrato_maquina')
                ->join('maquinas', 'extrato_maquina.id_maquina', '=', 'maquinas.id_maquina')
                ->join('locais', 'maquinas.id_local', '=', 'locais.id_local');

            $this->aplicarFiltroDataExtrato($query, $request);
            $this->aplicarFiltroTipoOperacao($query, $request);
            $this->aplicarFiltroMaquinaLocalCliente($query, $request);
            $this->aplicarFiltroTaxa($query, $request);

            $row = $query->selectRaw("
                COUNT(*) as total_registros,
                COALESCE(SUM(CASE WHEN {$naoDevolucao} THEN {$valor} ELSE -{$valor} END), 0) as total_acumulado,
                COALESCE(SUM(CASE WHEN {$naoDevolucao} THEN 0 ELSE {$valor} END), 0) as total_devolucao,
                COALESCE(SUM(CASE WHEN {$naoDevolucao} AND {$ehPix} THEN {$valor} ELSE 0 END), 0) as total_pix,
                COALESCE(SUM(CASE WHEN {$naoDevolucao} AND NOT {$ehPix} AND {$ehCartao} THEN {$valor} ELSE 0 END), 0) as total_cartao,
                COALESCE(SUM(CASE WHEN {$naoDevolucao} AND NOT {$ehPix} AND NOT {$ehCartao} AND {$ehDinheiro} THEN {$valor} ELSE 0 END), 0) as total_dinheiro
            ")->first();

            $totalAcumulado = round((float) ($row->total_acumulado ?? 0), 2);
            $totalDevolucao = round((float) ($row->total_devolucao ?? 0), 2);

            return response()->json([
                'total_registros' => (int) ($row->total_registros ?? 0),
                'total_acumulado' => $totalAcumulado,
                'total_saldo'     => round($totalAcumulado - $totalDevolucao, 2),
                'total_pix'       => round((float) ($row->total_pix ?? 0), 2),
                'total_cartao'    => round((float) ($row->total_cartao ?? 0), 2),
                'total_dinheiro'  => round((float) ($row->total_dinheiro ?? 0), 2),
                'total_devolucao' => $totalDevolucao,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Houve um erro ao tentar coletar o resumo das transações.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Totais por mês e totais gerais para o dashboard financeiro, agregados no
     * banco. Substitui o download de todas as transações que a tela fazia só
     * para montar os gráficos de receita por mês/trimestre.
     *
     * Mantém a mesma classificação de antes: receita é `extrato_operacao = 'C'`
     * e despesa é `'D'`, comparação estrita (nada de tratar NULL como 'C').
     */
    public function resumoFinanceiro(Request $request)
    {
        try {
            $porMes = DB::table('extrato_maquina')
                ->join('maquinas', 'extrato_maquina.id_maquina', '=', 'maquinas.id_maquina')
                ->where('extrato_maquina.extrato_operacao', 'C')
                ->select(
                    DB::raw("DATE_FORMAT(extrato_maquina.data_criacao, '%Y-%m') as mes"),
                    DB::raw('SUM(extrato_maquina.extrato_operacao_valor) as total')
                )
                ->groupBy('mes')
                ->orderBy('mes')
                ->get();

            $totais = DB::table('extrato_maquina')
                ->join('maquinas', 'extrato_maquina.id_maquina', '=', 'maquinas.id_maquina')
                ->selectRaw("
                    COALESCE(SUM(CASE WHEN extrato_maquina.extrato_operacao = 'C' THEN extrato_maquina.extrato_operacao_valor ELSE 0 END), 0) as total_receitas,
                    COALESCE(SUM(CASE WHEN extrato_maquina.extrato_operacao = 'D' THEN extrato_maquina.extrato_operacao_valor ELSE 0 END), 0) as total_despesas
                ")
                ->first();

            return response()->json([
                'por_mes'        => $porMes,
                'total_receitas' => round((float) ($totais->total_receitas ?? 0), 2),
                'total_despesas' => round((float) ($totais->total_despesas ?? 0), 2),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Houve um erro ao tentar coletar o resumo financeiro.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Aplica filtro de intervalo de datas em consultas de extrato_maquina.
     */
    private function aplicarFiltroDataExtrato($query, Request $request): void
    {
        $dataInicio = $request->input('data_inicio');
        $dataFim    = $request->input('data_fim');

        if ($dataInicio) {
            $inicio = Carbon::createFromFormat('Y-m-d', $dataInicio)->startOfDay()->format('Y-m-d H:i:s');
            $query->where('extrato_maquina.data_criacao', '>=', $inicio);
        }

        if ($dataFim) {
            $fim = Carbon::createFromFormat('Y-m-d', $dataFim)->endOfDay()->format('Y-m-d H:i:s');
            $query->where('extrato_maquina.data_criacao', '<=', $fim);
        }
    }

    /**
     * Adiciona status_comunicacao, status_pix e status_cartao ao item do acumulado.
     * - status_comunicacao: true se a máquina se conectou nos últimos 15 minutos
     * - status_pix:         true se bloqueio_jogada_efi == false
     * - status_cartao:      true se bloqueio_jogada_pagbank == false
     */
    private function appendStatusComunicacao(mixed $item): mixed
    {
        $arr = (array) $item;

        $ultimoContato = $arr['maquina_ultimo_contato'] ?? null;
        $arr['status_comunicacao'] = $ultimoContato
            ? Carbon::parse($ultimoContato)->diffInMinutes(Carbon::now()) <= 15
            : false;

        $arr['status_pix']    = !(bool) ($arr['bloqueio_jogada_efi']     ?? false);
        $arr['status_cartao'] = !(bool) ($arr['bloqueio_jogada_pagbank'] ?? false);

        return $arr;
    }

    /**
     * Filtra extrato pelo tipo de operação (PIX, Cartão, Dinheiro).
     */
    private function aplicarFiltroTipoOperacao($query, Request $request): void
    {
        $tipo = strtolower(trim((string) (
            $request->input('tipo_operacao')
            ?? $request->input('tipo_transacao')
            ?? ''
        )));

        if ($tipo === '') {
            return;
        }

        match ($tipo) {
            'pix' => $query->whereRaw('LOWER(extrato_maquina.extrato_operacao_tipo) LIKE ?', ['%pix%']),
            'cartao', 'cartão' => $query->whereRaw('LOWER(extrato_maquina.extrato_operacao_tipo) LIKE ?', ['%cart%']),
            'dinheiro' => $query->where(function ($q) {
                $q->whereRaw('LOWER(extrato_maquina.extrato_operacao_tipo) LIKE ?', ['%dinheir%'])
                  ->orWhereRaw('LOWER(extrato_maquina.extrato_operacao_tipo) LIKE ?', ['%fisic%']);
            }),
            default => $query->where(
                'extrato_maquina.extrato_operacao_tipo',
                $request->input('tipo_operacao') ?? $request->input('tipo_transacao')
            ),
        };
    }

    /**
     * Filtra extrato por máquina, local ou cliente — usado pela paginação
     * server-side das telas de extrato (admin e cliente), evitando que o
     * front precise buscar todos os registros para depois filtrar em PHP.
     */
    private function aplicarFiltroMaquinaLocalCliente($query, Request $request): void
    {
        $idMaquina = $this->paraArrayDeIds($request->input('id_maquina'));
        if (!empty($idMaquina)) {
            $query->whereIn('extrato_maquina.id_maquina', $idMaquina);
        }

        $idLocal = $this->paraArrayDeIds($request->input('id_local'));
        if (!empty($idLocal)) {
            $query->whereIn('maquinas.id_local', $idLocal);
        }

        $idCliente = $this->paraArrayDeIds($request->input('id_cliente'));
        if (!empty($idCliente)) {
            $query->join('cliente_local', 'locais.id_local', '=', 'cliente_local.id_local')
                  ->whereIn('cliente_local.id_cliente', $idCliente);
        }
    }

    /**
     * Exclui transações do tipo "Taxa" apenas quando mostrar_taxas=0 é
     * informado explicitamente. A ausência do parâmetro mantém o
     * comportamento padrão (sem filtro), para não quebrar chamadas
     * existentes que esperam o extrato completo (dashboards, relatórios).
     */
    private function aplicarFiltroTaxa($query, Request $request): void
    {
        if ($request->has('mostrar_taxas') && !$request->boolean('mostrar_taxas')) {
            $query->whereRaw('LOWER(extrato_maquina.extrato_operacao_tipo) NOT LIKE ?', ['%taxa%']);
        }
    }

    private function paraArrayDeIds($valor): array
    {
        if (empty($valor)) {
            return [];
        }

        if (is_array($valor)) {
            return array_values(array_filter($valor, fn($v) => $v !== null && $v !== ''));
        }

        return array_values(array_filter(explode(',', (string) $valor), fn($v) => $v !== ''));
    }
}
