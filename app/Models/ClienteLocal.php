<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClienteLocal extends Model
{
    use HasFactory;
    protected $table = "cliente_local";
    protected $primaryKey = 'id_cliente_local';
    public $timestamps = false;
    protected $fillable = [
        "id_cliente",
        "id_local",
    ];
     public static function rules($id = null)
     {
        return [
            "id_cliente" => "required|max:20",
            "id_local"=> "required|max:20",
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
