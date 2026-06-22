<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoricoReset extends Model
{
    protected $table = 'historico_resets';

    protected $fillable = [
        'id_maquina',
        'valor_total',
        'valor_reset',
        'data',
    ];

    protected $casts = [
        'valor_total' => 'decimal:2',
        'valor_reset' => 'decimal:2',
        'data'        => 'date',
    ];

    public static function rules(): array
    {
        return [
            'id_maquina'  => 'required|integer',
            'valor_total' => 'required|numeric|min:0',
            'valor_reset' => 'required|numeric|min:0',
            'data'        => 'required|date',
        ];
    }

    public static function feedback(): array
    {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'numeric'  => 'O campo :attribute deve ser numérico.',
            'min'      => 'O campo :attribute deve ser no mínimo :min.',
            'date'     => 'O campo :attribute deve ser uma data válida.',
        ];
    }
}
