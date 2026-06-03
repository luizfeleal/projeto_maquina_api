# Documentação da Aplicação - Projeto Máquina

Data da documentação: 18/05/2026

## 1. Visão geral

O Projeto Máquina é uma aplicação backend desenvolvida em Laravel para gerenciamento de máquinas, clientes, locais de instalação, usuários administrativos, QR Codes PIX, credenciais de pagamento, extratos financeiros e integração com hardware.

A aplicação centraliza os processos de:

- cadastro e controle de clientes;
- cadastro de locais vinculados a clientes;
- cadastro, ativação, consulta e remoção de máquinas;
- geração de QR Code PIX por máquina;
- registro de transações por PIX, cartão e dinheiro;
- liberação de jogadas no hardware após pagamento aprovado;
- recebimento de webhooks da Efí e do PagBank;
- coleta periódica de status e transações das máquinas;
- consulta de extratos, saldos, totais e relatórios.

O sistema funciona como uma API REST consumida por um painel administrativo e também como ponto de integração entre provedores de pagamento e a API de hardware.

## 2. Tecnologias utilizadas

- PHP 8+
- Laravel 9
- MySQL ou banco compatível com a configuração do Laravel
- JWT para autenticação de API
- Laravel Sanctum disponível no projeto
- Guzzle/cURL para integrações HTTP
- Biblioteca `mpdf/qrcode` para geração de QR Codes
- Biblioteca `endroid/qr-code`
- PHPUnit para testes automatizados
- Laravel Scheduler para execução de tarefas recorrentes

Principais dependências PHP:

- `laravel/framework`
- `tymon/jwt-auth`
- `laravel/sanctum`
- `guzzlehttp/guzzle`
- `mpdf/qrcode`
- `endroid/qr-code`
- `fruitcake/laravel-cors`

## 3. Estrutura geral do projeto

```text
app/
  Console/Commands/        Comandos agendados
  Http/Controllers/        Controllers da API
  Http/Middleware/         Middlewares de autenticação e proteção
  Models/                  Models Eloquent
  Services/                Serviços de integração Efí, PagBank e Hardware
config/                    Configurações Laravel
database/                  Migrations, factories e seeders
routes/api.php             Rotas principais da API
tests/Feature/             Testes de endpoints da API
projeto_maquina.sql        Dump SQL de referência
```

## 4. Autenticação e segurança

A aplicação utiliza autenticação JWT para proteger a maior parte dos endpoints administrativos.

Endpoint de login:

```http
POST /api/auth/login
```

Payload esperado:

```json
{
  "email": "usuario@exemplo.com",
  "password": "senha"
}
```

Resposta de sucesso:

```json
{
  "access_token": "token_jwt",
  "token_type": "bearer",
  "expires_in": 3600
}
```

Os endpoints protegidos devem receber o token no cabeçalho:

```http
Authorization: Bearer token_jwt
```

O middleware `apiJwt` valida o token e retorna mensagens para token inválido, expirado ou ausente.

Também existe endpoint de logout:

```http
POST /api/auth/logout
```

## 5. Principais módulos funcionais

### 5.1 Clientes

Responsável pelo cadastro e manutenção dos clientes da plataforma.

Campos principais:

- `id_cliente`
- `cliente_nome`
- `cliente_celular`
- `cliente_email`
- `cliente_cpf_cnpj`
- `checkbox_efi`
- `checkbox_pagbank`

Regras principais:

- nome obrigatório;
- celular obrigatório;
- e-mail obrigatório;
- CPF/CNPJ obrigatório e único.

Antes de remover um cliente, a aplicação verifica se há locais vinculados. Caso existam vínculos, a remoção é bloqueada.

### 5.2 Locais

Representa os pontos físicos onde as máquinas são instaladas.

Campos principais:

- `id_local`
- `local_nome`
- `id_cliente`

Regras principais:

- nome do local obrigatório;
- vínculo com cliente quando informado pelo fluxo de cadastro.

Antes de remover um local, a aplicação verifica se existem máquinas vinculadas. Caso existam, a remoção é bloqueada.

### 5.3 Máquinas

Responsável pelo cadastro e controle das máquinas operadas pelo sistema.

Campos principais:

- `id_maquina`
- `id_local`
- `id_placa`
- `maquina_referencia`
- `maquina_nome`
- `maquina_status`
- `maquina_ultimo_contato`
- `bloqueio_jogada_pagbank`
- `bloqueio_jogada_efi`

Regras principais:

- nome obrigatório;
- status obrigatório;
- ID da placa obrigatório;
- não permite cadastrar uma máquina ativa com o mesmo `id_placa`.

Ao cadastrar uma máquina, a aplicação também tenta registrar o dispositivo na API de hardware. Se o registro no hardware falhar, o cadastro local não é concluído.

Ao remover uma máquina:

- a aplicação solicita a remoção do dispositivo na API de hardware;
- a máquina é removida logicamente;
- os QR Codes vinculados à máquina também são removidos logicamente.

### 5.4 Máquinas de cartão

Vincula uma máquina física a um dispositivo de cartão usado pelo PagBank.

Campos principais:

- `id`
- `id_maquina`
- `device`
- `status`

Esse vínculo é utilizado no webhook do PagBank para identificar qual máquina deve receber a liberação de jogada após uma transação de cartão.

### 5.5 Usuários e grupos de acesso

O sistema possui cadastro de usuários administrativos e grupos de acesso.

Usuários:

- `id_usuario`
- `id_grupo_acesso`
- `id_cliente`
- `usuario_nome`
- `usuario_email`
- `usuario_login`
- `usuario_senha`
- `ativo`

Grupos de acesso:

- `id_grupo_acesso`
- `grupo_acesso_nome`

Acessos de tela:

- `id_grupo_acesso`
- `acesso_tela_viewname`
- `acesso_tela_nome`

Essas tabelas permitem organizar permissões e acesso às telas do painel administrativo.

### 5.6 Credenciais de API PIX

Responsável por armazenar credenciais de integração com provedores de pagamento, principalmente Efí e PagBank.

Campos principais:

- `id_cred_api_pix`
- `id_cliente`
- `client_id`
- `client_secret`
- `caminho_certificado`
- `tipo_cred`

As credenciais são criptografadas antes de serem salvas. Para Efí, a aplicação também processa certificado enviado pelo painel, convertendo o arquivo quando necessário.

Tipos de credencial usados pelo sistema:

- `efi`
- `pagbank`

Endpoint especial para atualização com envio de arquivo:

```http
POST /api/credApiPix/{id}/atualizar
```

Esse endpoint existe porque requisições `PUT` com multipart/form-data podem não popular arquivos corretamente no PHP/Laravel.

### 5.7 QR Code PIX

O módulo de QR Code gera e armazena QR Codes PIX vinculados a uma máquina, local e chave PIX.

Fluxo de geração:

1. O painel informa cliente, local e máquina.
2. A aplicação busca a credencial Efí do cliente.
3. Caso o cliente ainda não tenha chave PIX cadastrada, a aplicação cria uma chave aleatória na Efí.
4. A aplicação configura webhook na Efí para recebimento dos eventos PIX.
5. A aplicação gera um TXID com base no `id_placa` da máquina.
6. A aplicação monta o payload PIX.
7. A imagem do QR Code é gerada em PNG e armazenada em base64.
8. O QR Code é salvo no banco.

Campos principais:

- `id_qr`
- `id_chave_pix`
- `id_maquina`
- `id_local`
- `qr_image`
- `ativo`

### 5.8 Extrato de máquina

Registra movimentações financeiras relacionadas às máquinas.

Campos principais:

- `id_extrato_maquina`
- `id_maquina`
- `id_end_to_end`
- `extrato_operacao`
- `extrato_operacao_tipo`
- `extrato_operacao_valor`
- `extrato_operacao_status`
- `extrato_operacao_saldo`
- `data_criacao`

Tipos de operação usados:

- `C`: crédito;
- `D`: débito.

Tipos de origem/operação:

- `PIX`;
- `Cartão`;
- `Dinheiro`;
- `Taxa`;
- `Estorno`.

O extrato é alimentado por:

- webhook da Efí;
- webhook do PagBank;
- rotina agendada de coleta de transações em dinheiro no hardware;
- endpoints administrativos.

### 5.9 Extrato de cliente

Registra movimentações consolidadas por cliente.

Campos principais:

- `id_extrato_cliente`
- `id_cliente`
- `extrato_operacao_tipo`
- `extrato_operacao_valor`
- `extrato_operacao_status`
- `extrato_operacao_saldo`

### 5.10 Logs

Registra eventos operacionais e erros de integração.

Campos principais:

- `id`
- `id_usuario`
- `descricao`
- `status`
- `acao`
- `id_maquina`
- `data_criacao`

Exemplos de eventos registrados:

- falha ao liberar jogada;
- máquina bloqueada para pagamento por PIX ou cartão;
- device de cartão não encontrado;
- número máximo de tentativas de comunicação excedido.

## 6. Integrações externas

### 6.1 API de Hardware

A API de hardware é usada para gerenciar dispositivos e liberar créditos/jogadas.

Variável base:

```env
URL_HARDWARE=
```

Principais chamadas realizadas:

- `GET /available-devices`: lista máquinas disponíveis para registro;
- `POST /register-devices`: registra máquinas no hardware;
- `POST /removed-devices`: remove máquina do hardware;
- `GET /validated-devices`: lista máquinas ativas/validadas;
- `GET /local-transaction-log`: coleta transações locais, normalmente dinheiro/moedeiro;
- `POST /confirm-transaction-log`: confirma limpeza das transações coletadas;
- `POST /publish-credits`: libera crédito/jogada em uma máquina.

### 6.2 Efí PIX

A integração Efí é usada para:

- autenticação;
- criação de chave PIX aleatória;
- configuração de webhook;
- geração de QR Code PIX;
- recebimento de pagamentos;
- solicitação de devolução quando a liberação da jogada falha.

Variáveis relacionadas:

```env
URL_EFI=
URL_RECEBIMENTO_WEBHOOK=
```

Endpoint público de webhook:

```http
POST /api/webhook/efi/pix
```

Fluxo do webhook Efí:

1. Recebe evento PIX.
2. Valida evento de teste de webhook quando aplicável.
3. Extrai `endToEndId`, `txid`, valor e tarifa.
4. Identifica a máquina pelos primeiros 18 caracteres do TXID, que correspondem ao `id_placa`.
5. Verifica se a máquina está bloqueada para liberação via Efí.
6. Tenta liberar a jogada no hardware.
7. Registra crédito PIX e débito de taxa no extrato da máquina.
8. Se a jogada não puder ser liberada, solicita devolução do PIX.

### 6.3 PagBank

A integração PagBank é usada para receber notificações de transações de cartão.

Variáveis relacionadas:

```env
URL_PAGBANK_NOTIFICACAO=
URL_PAGBANKEDI=
USERNAME_PAGBANK=
PASSWORD_PAGBANK=
```

Endpoint público de webhook:

```http
POST /api/webhook/pagbank
```

Fluxo do webhook PagBank:

1. Recebe `notificationType` e `notificationCode`.
2. Se a notificação for do tipo `transaction`, consulta os detalhes no PagBank.
3. Identifica o dispositivo pelo serial number retornado na transação.
4. Localiza a máquina vinculada ao device.
5. Verifica se a máquina de cartão está ativa.
6. Verifica se a máquina está bloqueada para liberação via PagBank.
7. Tenta liberar a jogada no hardware.
8. Registra no extrato o crédito da transação e o débito da taxa.

## 7. Tarefas agendadas

O Laravel Scheduler possui duas rotinas executadas a cada minuto.

### 7.1 Verificar máquinas ativas

Comando:

```bash
php artisan machines:check-active
```

Agendamento:

```text
A cada minuto
```

Responsabilidade:

- buscar máquinas ativas na API de hardware;
- marcar todas as máquinas como inativas;
- atualizar como ativas somente as máquinas retornadas pelo hardware;
- salvar o último contato da máquina.

### 7.2 Coletar transações das máquinas

Comando:

```bash
php artisan machines:get-transactions
```

Agendamento:

```text
A cada minuto
```

Responsabilidade:

- coletar transações locais na API de hardware;
- vincular cada transação à máquina correspondente pelo `id_placa`;
- inserir créditos do tipo `Dinheiro` no extrato da máquina;
- confirmar a coleta para limpar o log de transações no hardware.

Para funcionamento em produção, o servidor deve executar o scheduler do Laravel via cron:

```bash
* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

## 8. Endpoints principais da API

Todos os endpoints abaixo usam o prefixo `/api`.

### 8.1 Autenticação

| Método | Rota | Descrição |
| --- | --- | --- |
| POST | `/auth/login` | Autentica usuário e retorna JWT |
| POST | `/auth/logout` | Encerra sessão/token |
| POST | `/tokenefi` | Autenticação auxiliar Efí |

### 8.2 Cadastros administrativos

| Recurso | Rotas REST |
| --- | --- |
| Usuários | `/usuarios` |
| Clientes | `/clientes` |
| Locais | `/locais` |
| Máquinas | `/maquinas` |
| Máquinas de cartão | `/maquinasCartao` |
| Cliente x Local | `/clienteLocal` |
| Grupos de acesso | `/gruposAcesso` |
| Acessos de tela | `/acessosTela` |
| Logs | `/logs` |
| QR Code | `/QRCode` |
| Credenciais PIX | `/credApiPix` |
| Extrato de cliente | `/extratoCliente` |
| Extrato de máquina | `/extratoMaquina` |

As rotas REST seguem o padrão:

| Método | Ação |
| --- | --- |
| GET `/recurso` | Lista registros |
| POST `/recurso` | Cria registro |
| GET `/recurso/{id}` | Consulta registro |
| PUT/PATCH `/recurso/{id}` | Atualiza registro |
| DELETE `/recurso/{id}` | Remove registro |

### 8.3 Extratos e relatórios

| Método | Rota | Descrição |
| --- | --- | --- |
| GET | `/extrato/acumulado` | Total acumulado por máquina |
| GET | `/extrato/acumuladoLocal` | Total acumulado por local |
| GET | `/extrato/total/{id?}` | Total geral ou por identificador |
| GET | `/extrato/devolucao/{id?}` | Total de devoluções |
| GET | `/extrato/saldo/{id?}` | Saldo |
| GET | `/totalMaquinas` | Última transação por máquina |
| POST | `/relatorioTotalTransacoes` | Relatório de transações |
| POST | `/relatorioTotalTransacoesTotal` | Totalizador de transações |
| POST | `/relatorioTotalTransacoesTaxa` | Totalizador de taxas |
| POST | `/transacaoMaquinaCliente` | Últimas transações de máquinas do cliente |
| POST | `/totalTransacaoMaquinaCliente` | Total de transações por cliente |
| POST | `/totalTransacaoMaquinaAcumuladoCliente` | Acumulado por máquina do cliente |

Alguns endpoints de listagem foram implementados no formato esperado pelo DataTables, usando parâmetros como:

- `start`;
- `length`;
- `search`;
- `order`.

### 8.4 Hardware

| Método | Rota | Descrição |
| --- | --- | --- |
| POST | `/hardware/status` | Atualiza status e último contato da máquina |
| POST | `/hardware/liberarJogada` | Solicita liberação de jogada/crédito |
| POST | `/hardware/maquinasDisponiveis` | Lista máquinas disponíveis para registro |

Exemplo de payload para status:

```json
{
  "id_placa": 123456,
  "status": true
}
```

Exemplo de payload para liberação de jogada:

```json
{
  "id_placa": "123456789012345678",
  "valor": 5,
  "id_transacao": "identificador_transacao"
}
```

### 8.5 Webhooks públicos

| Método | Rota | Descrição |
| --- | --- | --- |
| POST | `/webhook/efi/pix` | Recebe eventos PIX da Efí |
| POST | `/webhook/pagbank` | Recebe notificações do PagBank |

Esses endpoints precisam estar acessíveis publicamente para os provedores de pagamento.

## 9. Variáveis de ambiente relevantes

Além das variáveis padrão do Laravel, a aplicação depende das seguintes configurações:

```env
APP_NAME=
APP_ENV=
APP_KEY=
APP_DEBUG=
APP_URL=

DB_CONNECTION=mysql
DB_HOST=
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

JWT_SECRET=
JWT_TTL=
JWT_REFRESH_TTL=

URL_HARDWARE=
URL_EFI=
URL_RECEBIMENTO_WEBHOOK=

URL_PAGBANK_NOTIFICACAO=
URL_PAGBANKEDI=
USERNAME_PAGBANK=
PASSWORD_PAGBANK=

TENTATIVAS_PERSISTENCIA_JOGADA=
```

## 10. Instalação e execução

### 10.1 Instalar dependências

```bash
composer install
npm install
```

### 10.2 Configurar ambiente

```bash
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

Depois, preencher no `.env` as credenciais de banco, URLs externas e chaves necessárias.

### 10.3 Banco de dados

O projeto possui migrations padrão do Laravel e um arquivo SQL de referência chamado:

```text
projeto_maquina.sql
```

Em uma instalação nova, validar qual estratégia será usada:

- importar o dump SQL fornecido;
- ou criar migrations equivalentes para todo o schema atual.

### 10.4 Executar aplicação

Ambiente de desenvolvimento:

```bash
php artisan serve
```

Build de assets, quando necessário:

```bash
npm run dev
```

Produção:

```bash
npm run prod
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 11. Testes

O projeto possui testes de feature para os principais recursos:

- `ApiUsuariosTest`
- `ApiClientesTest`
- `ApiMaquinasTest`
- `ApiAcessosTelaTest`
- `ApiGrupoAcessoTest`
- `ApiExtratoClienteTest`
- `ApiExtratoMaquinaTest`

Execução:

```bash
php artisan test
```

ou:

```bash
vendor/bin/phpunit
```

## 12. Regras operacionais importantes

- O cadastro de máquina depende da disponibilidade e resposta positiva da API de hardware.
- A exclusão de cliente é bloqueada quando existem locais vinculados.
- A exclusão de local é bloqueada quando existem máquinas vinculadas.
- A exclusão de máquina remove também QR Codes vinculados.
- A liberação de jogada é tentada mais de uma vez conforme `TENTATIVAS_PERSISTENCIA_JOGADA`.
- Se o pagamento PIX for recebido, mas a jogada não puder ser liberada, a aplicação tenta solicitar devolução via Efí.
- Máquinas podem ser bloqueadas separadamente para liberação por PIX/Efí e cartão/PagBank.
- Transações de dinheiro são coletadas periodicamente do hardware e registradas como crédito no extrato da máquina.
- Taxas de PIX e cartão são registradas como débito no extrato.

## 13. Pontos de atenção para operação

- Os webhooks da Efí e do PagBank devem estar liberados no firewall e configurados com URL pública HTTPS.
- O scheduler do Laravel precisa estar ativo para atualização de status e coleta de transações.
- Certificados Efí devem ser armazenados e protegidos corretamente.
- As credenciais de pagamento não devem ser compartilhadas fora do ambiente seguro.
- O `APP_KEY` e o `JWT_SECRET` são críticos para segurança e não devem ser alterados sem planejamento.
- Logs devem ser monitorados para identificar falhas recorrentes de comunicação com hardware ou provedores de pagamento.
- A API de hardware deve estar disponível para cadastro de máquinas, liberação de jogadas e coleta de transações.

## 14. Resumo dos fluxos de negócio

### Cadastro de cliente e máquina

1. Cadastrar cliente.
2. Cadastrar local.
3. Vincular cliente ao local.
4. Cadastrar credenciais Efí/PagBank quando aplicável.
5. Consultar máquinas disponíveis no hardware.
6. Cadastrar máquina com `id_placa`.
7. A aplicação registra a máquina na API de hardware.
8. Gerar QR Code PIX vinculado à máquina.

### Pagamento via PIX

1. Usuário final paga o QR Code PIX.
2. Efí chama o webhook da aplicação.
3. A aplicação identifica a máquina pelo TXID.
4. A aplicação verifica bloqueios.
5. A aplicação libera jogada no hardware.
6. A aplicação registra crédito PIX e taxa no extrato.
7. Se houver falha na liberação, a aplicação solicita devolução.

### Pagamento via cartão

1. PagBank envia notificação de transação.
2. A aplicação consulta detalhes da transação.
3. A aplicação identifica o device da máquina de cartão.
4. A aplicação localiza a máquina vinculada.
5. A aplicação verifica bloqueios.
6. A aplicação libera jogada no hardware.
7. A aplicação registra crédito de cartão e taxa no extrato.

### Pagamento em dinheiro

1. O hardware registra transações locais.
2. O scheduler executa a coleta a cada minuto.
3. A aplicação busca as transações no hardware.
4. A aplicação identifica a máquina pelo `id_placa`.
5. A aplicação registra crédito do tipo `Dinheiro`.
6. A aplicação confirma a coleta no hardware.

## 15. Contatos técnicos e manutenção

Para manutenção evolutiva, recomenda-se manter atualizados:

- documentação de endpoints;
- variáveis de ambiente;
- credenciais e certificados dos provedores;
- dump ou migrations do banco de dados;
- testes automatizados dos principais fluxos;
- monitoramento dos webhooks e tarefas agendadas.

Esta documentação descreve o comportamento identificado no código-fonte atual da aplicação e deve ser revisada sempre que houver alteração relevante em rotas, integrações, banco de dados ou regras de negócio.
