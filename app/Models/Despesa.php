<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Despesa extends Model
{
    protected $table = 'despesas';

    protected $fillable = [
        'titulo',
        'descricao',
        'valor',
        'data',
        'id_categoria',
        'anexo_path',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'data'  => 'date',
    ];

    public static function rules(): array
    {
        return [
            'titulo'       => 'required|string|max:150',
            'descricao'    => 'nullable|string',
            'valor'        => 'required|numeric|min:0',
            'data'         => 'required|date',
            'id_categoria' => 'nullable|integer',
            'anexo_path'   => 'nullable|string|max:255',
        ];
    }

    public static function feedback(): array
    {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'max'      => 'O campo :attribute não pode ter mais de :max caracteres.',
            'numeric'  => 'O campo :attribute deve ser numérico.',
            'min'      => 'O campo :attribute deve ser no mínimo :min.',
            'date'     => 'O campo :attribute deve ser uma data válida.',
        ];
    }
}
