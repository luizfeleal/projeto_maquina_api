<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Lembrete de Mensalidade</title>
</head>
<body>
    <p>Olá, {{ $cliente->cliente_nome }}!</p>

    <p>Gostaríamos de lembrá-lo(a) que sua mensalidade no valor de
    <strong>R$ {{ number_format($mensalidade->valor, 2, ',', '.') }}</strong>
    vence em <strong>5 dias</strong> ({{ \Carbon\Carbon::parse($mensalidade->vencimento)->format('d/m/Y') }}).</p>

    <p>Por favor, efetue o pagamento até a data de vencimento para evitar bloqueios.</p>

    <p>Em caso de dúvidas, entre em contato conosco.</p>

    <p>Atenciosamente,<br>Equipe ProjetoMáquina</p>
</body>
</html>
