<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Lembrete de Mensalidade</title>
</head>
<body>
    <p>Olá, {{ $cliente->cliente_nome }}!</p>

    <p>Sua mensalidade no valor de
    <strong>R$ {{ number_format($mensalidade->valor, 2, ',', '.') }}</strong>
    vence em <strong>3 dias</strong> ({{ \Carbon\Carbon::parse($mensalidade->vencimento)->format('d/m/Y') }}).</p>

    <p>Realize o pagamento o quanto antes para evitar bloqueios no serviço.</p>

    <p>Em caso de dúvidas, entre em contato conosco.</p>

    <p>Atenciosamente,<br>Equipe ProjetoMáquina</p>
</body>
</html>
