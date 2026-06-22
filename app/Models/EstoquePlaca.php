<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstoquePlaca extends Model
{
    protected $table = 'estoque_placas';

    protected $fillable = [
        'serial',
        'status',
        'id_cliente_associado',
    ];

    protected $appends = ['serial_curto'];

    public function getSerialCurtoAttribute(): string
    {
        return substr($this->serial, -4);
    }

    public static function rules(): array
    {
        return [
            'serial'               => 'required|string|max:100|unique:estoque_placas,serial',
            'status'               => 'required|string|max:50',
            'id_cliente_associado' => 'nullable|integer',
        ];
    }

    public static function feedback(): array
    {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'max'      => 'O campo :attribute não pode ter mais de :max caracteres.',
            'unique'   => 'O serial informado já está cadastrado.',
        ];
    }
}
