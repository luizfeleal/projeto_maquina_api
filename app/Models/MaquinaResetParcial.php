<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaquinaResetParcial extends Model
{
    protected $table = 'maquina_resets_parciais';

    public $timestamps = false;

    protected $fillable = [
        'id_maquina',
        'valor_ultima_coleta',
        'valor_acumulado_total',
        'realizado_por',
        'observacao',
        'created_at',
    ];

    protected $casts = [
        'valor_ultima_coleta' => 'decimal:2',
        'valor_acumulado_total' => 'decimal:2',
        'created_at' => 'datetime',
    ];
}
