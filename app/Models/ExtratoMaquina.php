<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExtratoMaquina extends Model
{
    protected $table = 'extrato_maquina';
    protected $primaryKey = 'id_extrato_maquina';
    public $timestamps = false;
    protected $fillable = [
        'id_maquina',
        'id_end_to_end',
        'extrato_operacao',
        'extrato_operacao_tipo',
        'extrato_operacao_valor',
        'extrato_operacao_status',
        'extrato_operacao_saldo',
    ];

    public static function rules($id = null)
     {
        return [
            "extrato_operacao_tipo" => "required|string|max:10",
            "extrato_operacao_valor"=> "required|integer|max:10",
            "extrato_operacao_status"=> "required|boolean",
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
