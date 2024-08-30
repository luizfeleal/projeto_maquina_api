<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Logs extends Model
{
    use HasFactory;

    protected $table = 'logs';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'id_usuario',
        'descricao',
        'status',
        'acao',
        'id_maquina',
        'id_local',
        'id_cliente'

    ];

}
