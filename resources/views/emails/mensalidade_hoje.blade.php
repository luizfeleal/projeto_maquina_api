<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Mensalidade Vence Hoje</title>
</head>
<body>
    <p>Olá, {{ $cliente->cliente_nome }}!</p>

    <p><strong>Atenção:</strong> sua mensalidade no valor de
    <strong>R$ {{ number_format($mensalidade->valor, 2, ',', '.') }}</strong>
    vence <strong>hoje</strong> ({{ \Carbon\Carbon::parse($mensalidade->vencimento)->format('d/m/Y') }}).</p>

    <p>Para evitar a interrupção do serviço, efetue o pagamento ainda hoje.</p>

    <p>Em caso de dúvidas, entre em contato conosco.</p>

    <p>Atenciosamente,<br>Equipe ProjetoMáquina</p>
</body>
</html>
