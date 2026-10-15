# Diagnóstico Técnico e Ações Corretivas — Rejeição de Devolução Pix por Limite Transacional Insuficiente

**Referência:** Parecer Técnico Efí Bank — rejeições de devolução de Pix com a mensagem
"Limite transacional insuficiente. Você está tentando transferir R$ XX,XX. No momento, seu
limite disponível para o dia de hoje é R$ 0,00..."

## 1. Diagnóstico de causa raiz

O endpoint que recebe os webhooks de Pix da Efí
(`POST /webhook/efi/pix`, implementado em
`app/Http/Controllers/Efi/Webhooks/WebhookController@processamentoRequisicaoInicial`)
processava cada notificação recebida sem duas proteções essenciais:

1. **Ausência de checagem de idempotência.** O handler não verificava se a transação
   identificada pelo `id_end_to_end` (e2e) já havia sido processada antes de executar a
   liberação de jogada na máquina e, em caso de falha, solicitar a devolução do Pix junto
   à Efí. Quando a Efí reentrega o mesmo webhook — o que ocorre normalmente por timeout
   de resposta, instabilidade de rede ou qualquer falha HTTP no processamento original —,
   o fluxo inteiro era reexecutado do zero, incluindo uma **nova** chamada síncrona de
   devolução de Pix à API da Efí.
2. **Ausência de estado definitivo para rejeições.** Quando a Efí notificava a rejeição de
   uma devolução (status diferente de `DEVOLVIDO`/`EM_PROCESSAMENTO`, ex.: `NAO_REALIZADO`
   por limite insuficiente), o código apenas retornava (`return;`) sem registrar o motivo
   nem marcar a transação como bloqueada. Não havia nenhum sinal, em banco de dados, de que
   aquela transação já havia sido rejeitada por falta de limite — então a próxima
   reentrega do webhook original repetia a tentativa de devolução, gerando rejeições
   repetidas e tráfego desnecessário na API da Efí, exatamente como relatado no Parecer
   Técnico.

Essa mesma classe de problema (reprocessamento de webhook duplicado) já havia sido
identificada e corrigida anteriormente no fluxo equivalente do PagBank
(`app/Http/Controllers/Pagbank/Webhooks/WebhookController`, que já implementa checagem de
idempotência via `ExtratoMaquina::where('id_end_to_end', ...)->exists()` e um lock
distribuído por transação via `Cache::lock`), mas a correção não havia sido replicada para
o fluxo da Efí.

## 2. Ações corretivas aplicadas

### 2.1. Idempotência e lock por transação no webhook da Efí
Arquivo: `app/Http/Controllers/Efi/Webhooks/WebhookController.php`

- Antes de processar uma notificação de crédito Pix, o handler agora verifica se já existe
  um registro de extrato para aquele `id_end_to_end`. Se existir, a notificação é tratada
  como duplicada, registrada em log e respondida com `200 OK` sem reexecutar a liberação de
  jogada nem a devolução.
- Foi adicionado um lock distribuído (`Cache::lock`) por `id_end_to_end`, para que duas
  entregas concorrentes do mesmo webhook não passem pela checagem de idempotência ao mesmo
  tempo — mesmo padrão já usado no webhook do PagBank.

### 2.2. Tratamento explícito do webhook de rejeição de devolução
Arquivo: `app/Http/Controllers/Efi/Webhooks/WebhookController.php`

- O branch que trata notificações de devolução (`webhook.pix[0].devolucoes`) agora
  diferencia três casos: `DEVOLVIDO` (sucesso, como antes), `EM_PROCESSAMENTO` (aguarda) e
  **qualquer outro status** (rejeição definitiva). No caso de rejeição, o motivo informado
  pela Efí é extraído do payload, a transação é marcada com status de falha definitiva, e a
  equipe operacional é alertada por e-mail. Nenhuma nova tentativa automática é disparada a
  partir desse ponto.

### 2.3. Detecção estruturada de rejeição por limite
Arquivo: `app/Services/Efi/GestaoPixService.php`

- `solicitarDevolucao()` passou a capturar o HTTP status code da resposta da Efí (antes
  descartado) e a classificar a resposta com um novo método
  `identificarRejeicaoPorLimite()`, que reconhece a rejeição por limite transacional
  insuficiente a partir do código HTTP e do conteúdo da mensagem retornada pela Efí.

### 2.4. Estado de bloqueio, motivo e timestamp
Arquivo: `app/Services/Efi/LimiteTransacionalGuardService.php` (novo)

- Toda rejeição por limite insuficiente é registrada na tabela `logs` já existente no
  sistema, com ação dedicada (`Devolução Pix - Bloqueio Limite Insuficiente`), status de
  erro e timestamp (`data_criacao`, preenchido automaticamente pelo banco). O payload bruto
  recebido da Efí é registrado no log de arquivo da aplicação
  (`storage/logs/laravel.log`) para auditoria completa, já que o campo `logs.descricao` é
  limitado a 200 caracteres.
- O mesmo serviço expõe `estaBloqueada()`, usado como trava antes de qualquer nova
  tentativa de devolução para o mesmo `id_end_to_end`: a trava só é liberada quando (a) o
  último bloqueio registrado não é do dia corrente (virada do dia / renovação do limite
  diário) ou (b) existe um registro de liberação manual posterior ao bloqueio.

### 2.5. Interrupção imediata de retentativas
Arquivo: `app/Http/Controllers/Efi/Webhooks/WebhookController.php`

- Antes de chamar `GestaoPixService::solicitarDevolucao()`, o handler agora consulta
  `LimiteTransacionalGuardService::estaBloqueada()`. Se a transação já estiver bloqueada
  por limite insuficiente dentro da mesma janela, nenhuma nova chamada é feita à API da
  Efí — apenas um log de aviso é registrado.

### 2.6. Regra de liberação de novas tentativas
Arquivos: `app/Services/Efi/LimiteTransacionalGuardService.php`,
`app/Http/Controllers/Efi/DevolucaoPixController.php` (novo), rota
`POST /efi/devolucoes/retentar` (autenticada, grupo `apiJwt`)

- Uma nova tentativa de devolução só é possível em duas situações: (a) automaticamente,
  quando a virada do dia já ocorreu desde o último bloqueio (nova chamada deixa de ser
  barrada pela trava); ou (b) manualmente, quando a equipe operacional, após confirmar o
  ajuste/renovação do limite diário na conta Efí, aciona a rota
  `POST /efi/devolucoes/retentar` informando o `id_end_to_end`. Essa ação registra a
  liberação manual (com usuário e timestamp) e reexecuta a devolução uma única vez.

### 2.7. Alertas à equipe operacional
Arquivos: `app/Mail/AlertaDevolucaoPixBloqueada.php` (novo),
`resources/views/emails/devolucao_pix_bloqueada.blade.php` (novo)

- Toda rejeição por limite insuficiente dispara um e-mail (classe
  `AlertaDevolucaoPixBloqueada`) para os destinatários configurados na variável de
  ambiente `ALERTA_PIX_EMAIL` (lista separada por vírgula), com `id_end_to_end`, txid,
  valor, motivo informado pela Efí e horário. Falha no envio do alerta é registrada em log
  e nunca interrompe o processamento do webhook.
- O valor é lido via `config('services.alerta_pix.email')` (novo valor em
  `config/services.php`), não via `env()` direto no controller, para continuar funcionando
  em produção depois de `php artisan config:cache`.

### 2.8. Correção correlata de tratamento de exceções
Arquivo: `app/Http/Controllers/Efi/Webhooks/WebhookController.php`

- Os blocos `catch (ValidationException $e)` e `catch (Exception $e)` referenciavam
  classes não importadas no namespace do controller, o que impedia esses `catch` de
  efetivamente capturarem exceções do tipo esperado. Foram adicionados os `use` corretos
  (`Illuminate\Validation\ValidationException` e `Exception` global), garantindo que erros
  durante o processamento do webhook sejam de fato capturados, revertam a transação de
  banco (`DB::rollBack()`) e gerem uma resposta HTTP 500 com log do erro, em vez de um erro
  fatal não tratado.

## 3. Controles de prevenção

- **Idempotência por `id_end_to_end`** em todas as notificações de crédito Pix recebidas
  via webhook da Efí, eliminando reprocessamento duplicado como causa de retentativas em
  ciclo.
- **Lock distribuído por transação**, prevenindo condição de corrida entre entregas
  concorrentes do mesmo webhook.
- **Estado definitivo de falha persistido** (tabela `logs`) para toda rejeição por limite
  insuficiente, com motivo, payload (em log de arquivo) e timestamp.
- **Trava de retentativa automática**, que impede novas chamadas à API de devolução da
  Efí enquanto o bloqueio por limite estiver vigente no mesmo dia.
- **Processo de retomada controlado** (rota manual + regra de virada de dia), em vez de
  reprocessamento automático e irrestrito.
- **Alerta por e-mail em tempo real** à equipe operacional a cada rejeição por limite
  insuficiente.

## 4. Limitações conhecidas e recomendações futuras

- O estado de bloqueio é persistido na tabela genérica `logs` (decisão tomada para não
  introduzir uma nova tabela nesta correção), identificando a transação por correspondência
  de texto no campo `descricao`. Para volumes maiores ou necessidade de relatórios
  estruturados, recomenda-se migrar esse controle para uma tabela dedicada
  (`id_end_to_end`, `status`, `motivo`, `payload`, `tentativas`, timestamps), o que também
  permitiria um relatório comando/painel de devoluções bloqueadas.
- A chamada de devolução à Efí continua síncrona dentro do próprio webhook. Mover esse
  processamento para uma fila (queue) traria tolerância a timeout e retries controlados
  pelo próprio worker, reduzindo ainda mais o risco de reentrega do webhook por lentidão no
  processamento.
- É necessário configurar a variável de ambiente `ALERTA_PIX_EMAIL` em produção (vazia por
  padrão) para que os alertas por e-mail sejam de fato entregues à equipe operacional.
