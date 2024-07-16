<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChavePix extends Model
{
    use HasFactory;
    protected $table = "chave_pix";
    protected $primaryKey = "id_chave_pix";
    public $timestamps = false;
    protected $fillable = [
        "id_cliente",
        "chave",
        "status"
    ];
     public static function rules($id = null)
     {
        return [
            "id_cliente" => "required|max:20",
            "chave"=> "required|string|max:25",
            "status"=> "required|boolean",
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
