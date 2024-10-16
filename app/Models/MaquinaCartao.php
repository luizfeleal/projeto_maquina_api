<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaquinaCartao extends Model
{
    use HasFactory;
    protected $table = "maquina_cartao";
    protected $primaryKey = "id";
    public $timestamps = false;
    protected $fillable = [
        "id_maquina",
        "device",
        "status"
    ];
     public static function rules($id = null)
     {
        return [
            "id_maquina" => "required|max:20",
            "device"=> "required|string|max:25",
            "status"=> "required",
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
