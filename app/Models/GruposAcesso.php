<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GruposAcesso extends Model
{
    protected $table = 'grupos_acesso';
    protected $primaryKey = 'id_grupo_acesso';
    public $timestamps = false;
    protected $fillable = [
        'grupo_acesso_nome',
    ];

    public static function rules($id = null)
     {
        return [
            "grupo_acesso_nome" => "required|string|max:30",
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
