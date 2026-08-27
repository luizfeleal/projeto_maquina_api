<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MercadopagoLoja extends Model
{
    use HasFactory;
    protected $table = 'mercadopago_loja';
    protected $fillable = [
        'id_cliente',
        'mp_user_id',
        'mp_store_id',
        'external_store_id',
    ];
}
