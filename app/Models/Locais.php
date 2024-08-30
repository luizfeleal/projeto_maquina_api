<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Locais extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'locais';
    protected $primaryKey = 'id_local';
    public $timestamps = false;
    protected $dates = ['deleted_at'];
    protected $fillable = [
        'local_nome',
        'id_cliente'
    ];

    public static function rules($id = null)
    {

         return [
            'local_nome' => 'required|string|max:200',
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
