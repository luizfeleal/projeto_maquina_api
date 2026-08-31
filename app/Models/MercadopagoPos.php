<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MercadopagoPos extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'mercadopago_pos';
    protected $fillable = [
        'id_cliente',
        'id_local',
        'id_maquina',
        'id_mercadopago_loja',
        'mp_pos_id',
        'external_pos_id',
        'qr_image',
        'qr_data',
        'ativo',
    ];

    public static function rules($id = null)
    {
        return [
            'id_cliente' => 'required',
            'id_maquina' => 'required',
            'id_mercadopago_loja' => 'required',
            'mp_pos_id' => 'required|string|max:40',
            'external_pos_id' => 'required|string|max:40',
            'qr_image' => 'required',
            'ativo' => 'required',
        ];
    }

    public static function feedback($id = null)
    {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'max' => 'O campo :attribute não pode ter mais de :max caracteres.',
        ];
    }
}
