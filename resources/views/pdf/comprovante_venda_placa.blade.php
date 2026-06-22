<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprovante de Venda - Placa #{{ $placa->id }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #2d2d2d; background: #fff; }

        .page { padding: 40px 50px; }

        .header { border-bottom: 3px solid #1a56db; padding-bottom: 20px; margin-bottom: 28px; display: flex; justify-content: space-between; align-items: flex-start; }
        .header .logo-area h1 { font-size: 22px; font-weight: 700; color: #1a56db; letter-spacing: 0.5px; }
        .header .logo-area p { font-size: 11px; color: #6b7280; margin-top: 3px; }
        .header .doc-info { text-align: right; }
        .header .doc-info .doc-title { font-size: 16px; font-weight: 700; color: #1a56db; text-transform: uppercase; letter-spacing: 1px; }
        .header .doc-info .doc-number { font-size: 11px; color: #6b7280; margin-top: 4px; }
        .header .doc-info .doc-date { font-size: 11px; color: #6b7280; margin-top: 2px; }

        .badge { display: inline-block; padding: 4px 12px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-disponivel { background: #d1fae5; color: #065f46; }
        .badge-vendida { background: #dbeafe; color: #1e40af; }
        .badge-inativa { background: #fee2e2; color: #991b1b; }

        .section { margin-bottom: 24px; }
        .section-title { font-size: 12px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.8px; border-bottom: 1px solid #e5e7eb; padding-bottom: 6px; margin-bottom: 14px; }

        .grid-2 { display: flex; gap: 24px; }
        .grid-2 > div { flex: 1; }

        .field { margin-bottom: 12px; }
        .field label { display: block; font-size: 10px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }
        .field span { font-size: 13px; color: #111827; font-weight: 500; }

        .highlight-box { background: #f0f7ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 16px 20px; margin-bottom: 20px; }
        .highlight-box .serial-label { font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .highlight-box .serial-value { font-size: 20px; font-weight: 700; color: #1e40af; letter-spacing: 2px; font-family: Courier, monospace; }
        .highlight-box .serial-curto { font-size: 12px; color: #6b7280; margin-top: 2px; }

        .info-table { width: 100%; border-collapse: collapse; }
        .info-table td { padding: 10px 14px; font-size: 12px; border-bottom: 1px solid #f3f4f6; }
        .info-table tr:last-child td { border-bottom: none; }
        .info-table td:first-child { color: #6b7280; font-weight: 600; width: 40%; }
        .info-table td:last-child { color: #111827; font-weight: 500; }
        .info-table tr:nth-child(even) td { background: #f9fafb; }

        .footer { border-top: 1px solid #e5e7eb; padding-top: 18px; margin-top: 32px; }
        .footer p { font-size: 10px; color: #9ca3af; text-align: center; line-height: 1.6; }

        .stamp-area { text-align: center; margin-top: 32px; padding: 20px; border: 2px dashed #d1d5db; border-radius: 6px; }
        .stamp-area p { font-size: 11px; color: #9ca3af; margin-bottom: 40px; }
        .stamp-area .line { border-top: 1px solid #374151; width: 220px; margin: 0 auto; }
        .stamp-area .line-label { font-size: 10px; color: #6b7280; margin-top: 4px; }
    </style>
</head>
<body>
<div class="page">

    <div class="header">
        <div class="logo-area">
            <h1>SwiftPay Soluções</h1>
            <p>Sistema de Gerenciamento de Máquinas</p>
        </div>
        <div class="doc-info">
            <div class="doc-title">Comprovante de Venda</div>
            <div class="doc-number">Placa nº {{ str_pad($placa->id, 6, '0', STR_PAD_LEFT) }}</div>
            <div class="doc-date">Emitido em: {{ $dataEmissao }}</div>
        </div>
    </div>

    <div class="highlight-box">
        <div class="serial-label">Serial da Placa</div>
        <div class="serial-value">{{ $placa->serial }}</div>
        <div class="serial-curto">Últimos 4 dígitos: <strong>{{ $placa->serial_curto }}</strong></div>
    </div>

    <div class="section">
        <div class="section-title">Detalhes da Placa</div>
        <table class="info-table">
            <tr>
                <td>ID do Registro</td>
                <td>#{{ $placa->id }}</td>
            </tr>
            <tr>
                <td>Serial Completo</td>
                <td style="font-family: Courier, monospace; font-size: 12px;">{{ $placa->serial }}</td>
            </tr>
            <tr>
                <td>Status</td>
                <td>
                    @php
                        $badgeClass = match(strtolower($placa->status)) {
                            'disponivel', 'disponível' => 'badge-disponivel',
                            'vendida'                   => 'badge-vendida',
                            default                     => 'badge-inativa',
                        };
                    @endphp
                    <span class="badge {{ $badgeClass }}">{{ ucfirst($placa->status) }}</span>
                </td>
            </tr>
            @if ($placa->id_cliente_associado)
            <tr>
                <td>Cliente Associado (ID)</td>
                <td>{{ $placa->id_cliente_associado }}</td>
            </tr>
            @endif
            <tr>
                <td>Data de Cadastro</td>
                <td>{{ \Carbon\Carbon::parse($placa->created_at)->format('d/m/Y H:i') }}</td>
            </tr>
            @if ($placa->updated_at && $placa->updated_at != $placa->created_at)
            <tr>
                <td>Última Atualização</td>
                <td>{{ \Carbon\Carbon::parse($placa->updated_at)->format('d/m/Y H:i') }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="stamp-area">
        <p>Assinatura e Carimbo do Responsável</p>
        <div class="line"></div>
        <div class="line-label">Responsável pela Venda</div>
    </div>

    <div class="footer">
        <p>
            Este documento é um comprovante de venda gerado automaticamente pelo sistema.<br>
            SwiftPay Soluções &mdash; Documento gerado em {{ $dataEmissao }} &mdash; Uso interno
        </p>
    </div>

</div>
</body>
</html>
