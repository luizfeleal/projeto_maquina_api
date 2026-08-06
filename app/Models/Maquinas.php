<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Maquinas extends Model
{

    use HasFactory, SoftDeletes;
    protected $table = 'maquinas';
    protected $primaryKey = 'id_maquina';
    public $timestamps = false;
    protected $dates = ['deleted_at'];
    protected $fillable = [
        'id_local',
        'id_placa',
        'maquina_referencia',
        'maquina_nome',
        'maquina_status',
        'maquina_ultimo_contato',
        'maquina_ultima_coleta',
        'ultimo_valor_reset',
        'bloqueio_jogada_pagbank',
        'bloqueio_jogada_efi'
    ];

    protected $casts = [
        'maquina_ultima_coleta' => 'decimal:2',
        'ultimo_valor_reset'    => 'decimal:2',
        'bloqueio_jogada_efi'   => 'boolean',
        'bloqueio_jogada_pagbank' => 'boolean',
    ];

    /**
     * Saldo desde o último reset de aferição.
     *
     * NÃO fica em $appends de propósito: o cálculo faz um SUM na extrato_maquina
     * por máquina, então serializar uma lista de N máquinas custava N consultas
     * (uma varredura da maior tabela do banco cada). Como o banco é remoto, isso
     * dominava o tempo de qualquer endpoint que devolvesse máquinas — inclusive
     * GET /maquinas, chamado em quase toda tela do painel.
     *
     * Quem realmente precisar do valor deve pedir explicitamente:
     * `$maquina->append('saldo_afericao')` ou `$maquina->saldo_afericao`.
     */
    public function getSaldoAfericaoAttribute(): float
    {
        $totalAcumulado = \Illuminate\Support\Facades\DB::table('extrato_maquina')
            ->where('id_maquina', $this->id_maquina)
            ->sum('extrato_operacao_valor');

        return round((float) $totalAcumulado - (float) $this->ultimo_valor_reset, 2);
    }

    public static function rules($id = null)
     {
        return [
            "maquina_nome"=> "required|string|max:255",
            "maquina_status"=> "required|boolean",
            "id_placa"=> "required",
        ];
     }

     public static function feedback($id = null)
     {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'max' => 'O campo :attribute não pode ter mais de :max caracteres.',
            'unique' => 'O valor informado para o campo :attribute já está em uso.',
            'min' => 'O campo :attribute deve ter no mínimo :min caracteres.',
        ];
     }
}
