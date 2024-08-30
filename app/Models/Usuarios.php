<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Usuarios extends Model
{
    use HasFactory;

    protected $table = 'usuarios';
    protected $primaryKey = 'id_usuario';
    public $timestamps = false;
    protected $fillable = [
        'id_grupo_acesso',
        'id_cliente',
        'usuario_nome',
        'usuario_email',
        'usuario_ultimo_acesso',
        'usuario_login',
        'usuario_senha',
        'ativo'
    ];

    public static function rules($id = null)
    {

         return [
            'id_grupo_acesso' => 'required|integer',
            'id_cliente' => 'required|integer',
            'usuario_nome' => 'required|string|max:255',
            'usuario_email' => 'required|email|unique:usuarios',
            'usuario_login' => 'required|string|max:255|unique:usuarios',
            'usuario_senha' => 'required|string|min:6',
            'ativo' => 'required|min:1|max:1',
        ];
    }

    public static function feedback($id = null)
    {
        // Define as mensagens de erro personalizadas
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'max' => 'O campo :attribute não pode ter mais de :max caracteres.',
            'unique' => 'O valor informado para o campo :attribute já está em uso.',
            'min' => 'O campo :attribute deve ter no mínimo :min caracteres.',
        ];
    }
}
