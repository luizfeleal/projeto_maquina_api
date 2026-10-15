<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Devolução Pix bloqueada</title>
</head>
<body>
    <p><strong>Atenção:</strong> uma devolução de Pix foi rejeitada pela Efí por limite transacional insuficiente e
    as novas tentativas automáticas foram interrompidas.</p>

    <ul>
        <li><strong>Máquina:</strong> {{ $idMaquina ?? 'não identificada' }}</li>
        <li><strong>End-to-end ID:</strong> {{ $idE2E }}</li>
        <li><strong>Txid:</strong> {{ $txid ?? 'não informado' }}</li>
        <li><strong>Valor:</strong> R$ {{ number_format($valor, 2, ',', '.') }}</li>
        <li><strong>Motivo informado pela Efí:</strong> {{ $motivo }}</li>
        <li><strong>Horário:</strong> {{ now()->format('d/m/Y H:i:s') }}</li>
    </ul>

    <p>Essa devolução só será reprocessada automaticamente após a virada do dia (renovação do limite diário),
    ou manualmente, quando a equipe operacional confirmar o ajuste do limite na conta Efí.</p>

    <p>Atenciosamente,<br>Equipe ProjetoMáquina</p>
</body>
</html>
