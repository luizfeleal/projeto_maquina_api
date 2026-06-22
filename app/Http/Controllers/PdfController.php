<?php

namespace App\Http\Controllers;

use App\Models\EstoquePlaca;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class PdfController extends Controller
{
    public function comprovanteVendaPlaca($id)
    {
        $placa = EstoquePlaca::find($id);

        if (!$placa) {
            return response()->json(['message' => 'Placa não encontrada.'], 404);
        }

        $dataEmissao = Carbon::now('America/Sao_Paulo')->format('d/m/Y H:i');

        $pdf = Pdf::loadView('pdf.comprovante_venda_placa', compact('placa', 'dataEmissao'))
            ->setPaper('a4', 'portrait');

        $nomeArquivo = 'comprovante_placa_' . str_pad($id, 6, '0', STR_PAD_LEFT) . '.pdf';

        return $pdf->download($nomeArquivo);
    }
}
