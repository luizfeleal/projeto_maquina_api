<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstoqueProduto extends Model
{
    protected $table = 'estoque_produtos';

    protected $fillable = [
        'lote',
        'nome_produto',
        'descricao',
        'quantidade',
        'valor',
        'cobrar_mensal',
    ];

    protected $casts = [
        'quantidade'    => 'integer',
        'valor'         => 'decimal:2',
        'cobrar_mensal' => 'boolean',
    ];

    public static function rules(): array
    {
        return [
            'lote'          => 'nullable|string|max:100',
            'nome_produto'  => 'required|string|max:150',
            'descricao'     => 'nullable|string',
            'quantidade'    => 'required|integer|min:0',
            'valor'         => 'required|numeric|min:0',
            'cobrar_mensal' => 'boolean',
        ];
    }

    public static function feedback(): array
    {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'max'      => 'O campo :attribute não pode ter mais de :max caracteres.',
            'integer'  => 'O campo :attribute deve ser um número inteiro.',
            'numeric'  => 'O campo :attribute deve ser um número.',
            'min'      => 'O campo :attribute não pode ser negativo.',
        ];
    }
}
