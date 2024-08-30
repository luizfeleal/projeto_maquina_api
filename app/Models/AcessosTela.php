<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcessosTela extends Model
{
    protected $table = 'acessos_tela';
    public $timestamps = false;
    protected $fillable = [
        'id_grupo_acesso',
        'acesso_tela_viewname',
        'acesso_tela_nome'
    ];

    public static function rules($id = null)
     {
        return [
            "id_grupo_acesso" => "required",
            "acesso_tela_viewname" => "required|string|max:100",
            "acesso_tela_nome" => "required|string|max:100",
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
