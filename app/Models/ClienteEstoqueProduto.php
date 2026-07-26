<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClienteEstoqueProduto extends Model
{
    protected $table = 'cliente_estoque_produto';

    protected $fillable = [
        'id_cliente',
        'id_estoque_produto',
        'quantidade',
    ];

    protected $casts = [
        'quantidade' => 'integer',
    ];

    public function produto()
    {
        return $this->belongsTo(EstoqueProduto::class, 'id_estoque_produto');
    }
}
