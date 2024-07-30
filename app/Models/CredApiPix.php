<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CredApiPix extends Model
{
    protected $table = 'cred_api_pix';
    public $timestamps = false;
    protected $fillable = [
        'id_cliente',
        'client_id',
        'client_secret',
        'caminho_certificado'
    ];

    public static function rules($id = null)
     {
        return [
            "id_cliente" => "required",
            "client_id" => "required|string|max:200",
            "client_secret" => "required|string|max:200",
            "caminho_certificado" => "required|string|max:200",
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
