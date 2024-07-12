<?php

namespace App\Http\Controllers\Efi;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\Efi\QrCodeService;

class QrController extends Controller
{

    public function auth(Request $request)
    {
        $payload = (new QrCodeService)->setChavePix('12312312333')
                                      ->setDescricao('')
                                      ->setNomeTitularConta('Luiz Felipe')
                                      ->setNomeCidadeTitularConta('SAO PAULO')
                                      ->setTxid('12')
                                      ->setValorTransacao(10.00);

        return $payload;

    }
}
