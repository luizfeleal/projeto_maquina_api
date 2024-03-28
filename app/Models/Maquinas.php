<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Maquinas extends Model
{
    protected $table = 'maquinas';
    protected $primaryKey = 'id_maquina';
    public $timestamps = false;
    protected $fillable = [
        'id_local',
        'maquina_referencia',
        'maquina_nome',
        'maquina_status',
        'maquina_ultimo_contato',
    ];

    public static function rules($id = null)
     {
        return [
            "maquina_referencia" => "required|string|max:255",
            "maquina_nome"=> "required|string|max:255",
            "maquina_status"=> "required|boolean",
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
