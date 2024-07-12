<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QrCode extends Model
{
    protected $table = 'qr_code';
    protected $primaryKey = 'id_qr';
    public $timestamps = false;
    protected $fillable = [
        'id_chave_pix',
        'id_maquina',
        'id_local',
        'qr_image',
        'ativo'
    ];

    public static function rules($id = null)
     {
        return [
            "id_chave_pix" => "required",
            "id_maquina"=> "required",
            "id_local" => "required",
            "qr_image"=> "required",
            "ativo" => "required"
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
