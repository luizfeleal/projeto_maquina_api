<?php

namespace App\Services\Efi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\QrCode;
use Illuminate\Support\Facades\Crypt;

class DescriptografaCredService
{
    public static function descriptografarCred($dadosCred)
    {
        $dadosCredDescriptografado = [
            "client_id" => Crypt::decryptString($dadosCred['client_id']),
            "client_secret" => Crypt::decryptString($dadosCred['client_secret']),
            "caminho_certificado" => Crypt::decryptString($dadosCred['caminho_certificado'])
        ];

        return $dadosCredDescriptografado;
    }
}
