<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mensalidade extends Model
{
    protected $table = 'mensalidades';

    protected $fillable = [
        'id_cliente',
        'valor',
        'vencimento',
        'status',
        'efi_charge_id',
        'boleto_barcode',
        'boleto_link',
        'boleto_pdf',
        'boleto_status',
    ];

    protected $casts = [
        'valor'      => 'decimal:2',
        'vencimento' => 'date',
    ];

    public static function rules(): array
    {
        return [
            'id_cliente' => 'required|integer',
            'valor'      => 'required|numeric|min:0',
            'vencimento' => 'required|date',
            'status'     => 'required|in:pago,pendente,atrasado',
        ];
    }

    public static function feedback(): array
    {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'numeric'  => 'O campo :attribute deve ser numérico.',
            'min'      => 'O campo :attribute deve ser no mínimo :min.',
            'date'     => 'O campo :attribute deve ser uma data válida.',
            'in'       => 'O campo :attribute deve ser pago, pendente ou atrasado.',
        ];
    }
}
