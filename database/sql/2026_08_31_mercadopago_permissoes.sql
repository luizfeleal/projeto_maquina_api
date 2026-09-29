-- ============================================================
-- Mercado Pago: schema (tipo_cred) e permissões de tela
-- Banco: o mesmo usado pela API (projeto_maquina_api) para
--        acessos_tela / grupos_acesso / cred_api_pix
-- Data: 2026-08-31
--
-- Contexto:
--   O painel Blade (projeto_maquina) já foi atualizado para oferecer
--   Mercado Pago como terceiro gateway de credencial (ao lado de EFI e
--   PagBank) e para gerar QR/POS via Mercado Pago. Este script cobre o
--   que falta no banco de dados:
--
--     1) garantir que a coluna `cred_api_pix.tipo_cred` aceite o valor
--        'mercadopago'. Um dump de referência mais antigo
--        (projeto_maquina_completo.sql) mostra essa coluna como
--        ENUM('efi','pagbank') — se essa restrição ainda existir na
--        base real, salvar uma credencial mercadopago falha (ou trunca)
--        mesmo com o código da API já pronto para esse valor
--        (ver app/Services/Mercadopago/NotificacaoService.php,
--        app/Http/Controllers/Mercadopago/{LojaController,QrController}.php).
--        Rode a query de diagnóstico (passo 1) antes de aplicar o ALTER
--        (passo 2) para confirmar o tipo atual.
--
--     2) conceder as 4 permissões de tela novas em `acessos_tela` para
--        os mesmos grupos que já têm as equivalentes do PagBank —
--        mesmo padrão usado em insert_permissoes_credencial.sql quando
--        o PagBank foi adicionado.
--
--   Fora do escopo (não incluído aqui):
--     - Tabelas mercadopago_loja / mercadopago_pos: não têm tela no
--       painel (decisão de produto: loja é provisionada só no backend,
--       sem UI), então não há rota nova para liberar em acessos_tela
--       além das 4 abaixo.
--     - As rotas genéricas de QR (qr-criar, qr-registrar, cliente-qr-*)
--       não mudaram de nome — o gateway agora é um campo do formulário,
--       não uma rota nova — então não precisam de permissão nova.
--     - As rotas genéricas de credencial (credencial-listar,
--       credencial-registrar, credencial-atualizar, credencial-excluir
--       e equivalentes cliente-*) já são compartilhadas entre
--       EFI/PagBank/Mercado Pago e já devem estar liberadas.
--
-- Idempotente: pode ser executado mais de uma vez sem duplicar linhas
-- em acessos_tela nem falhar se já tiver sido aplicado parcialmente.
-- ============================================================

-- ------------------------------------------------------------
-- 1) DIAGNÓSTICO — confira o tipo atual da coluna antes de seguir
-- ------------------------------------------------------------
SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'cred_api_pix'
  AND COLUMN_NAME = 'tipo_cred';

-- ------------------------------------------------------------
-- 2) Libera 'mercadopago' em cred_api_pix.tipo_cred
--    Seguro mesmo se a coluna já for VARCHAR (apenas reaplica o tipo,
--    sem perda de dados). Se for ENUM('efi','pagbank'), converte para
--    VARCHAR preservando os valores já gravados.
-- ------------------------------------------------------------
ALTER TABLE `cred_api_pix`
  MODIFY COLUMN `tipo_cred` VARCHAR(20) NOT NULL DEFAULT 'efi';

-- ------------------------------------------------------------
-- 3) Permissões de tela (acessos_tela) — rotas novas do Mercado Pago
--    Concede a permissão a todo grupo que já tem a equivalente do
--    PagBank, evitando fixar IDs de grupo manualmente.
-- ------------------------------------------------------------

-- Lado Admin: credencial-criar-mercadopago / credencial-editar-mercadopago
INSERT INTO `acessos_tela`
    (`id_grupo_acesso`, `acesso_tela_viewname`, `acesso_tela_nome`, `ativo`, `data_criacao`, `data_alteracao`)
SELECT existente.id_grupo_acesso, novas.viewname, novas.nome, 1, NOW(), NOW()
FROM `acessos_tela` existente
CROSS JOIN (
    SELECT 'credencial-criar-mercadopago'  AS viewname, 'Credenciais - Criar Mercado Pago'  AS nome
    UNION ALL
    SELECT 'credencial-editar-mercadopago', 'Credenciais - Editar Mercado Pago'
) novas
WHERE existente.acesso_tela_viewname = 'credencial-criar-pagbank'
  AND NOT EXISTS (
      SELECT 1 FROM `acessos_tela` a2
      WHERE a2.id_grupo_acesso = existente.id_grupo_acesso
        AND a2.acesso_tela_viewname = novas.viewname
  );

-- Lado Cliente: cliente-credencial-criar-mercadopago / cliente-credencial-editar-mercadopago
INSERT INTO `acessos_tela`
    (`id_grupo_acesso`, `acesso_tela_viewname`, `acesso_tela_nome`, `ativo`, `data_criacao`, `data_alteracao`)
SELECT existente.id_grupo_acesso, novas.viewname, novas.nome, 1, NOW(), NOW()
FROM `acessos_tela` existente
CROSS JOIN (
    SELECT 'cliente-credencial-criar-mercadopago'  AS viewname, 'Credenciais - Criar Mercado Pago'  AS nome
    UNION ALL
    SELECT 'cliente-credencial-editar-mercadopago', 'Credenciais - Editar Mercado Pago'
) novas
WHERE existente.acesso_tela_viewname = 'cliente-credencial-criar-pagbank'
  AND NOT EXISTS (
      SELECT 1 FROM `acessos_tela` a2
      WHERE a2.id_grupo_acesso = existente.id_grupo_acesso
        AND a2.acesso_tela_viewname = novas.viewname
  );

-- ------------------------------------------------------------
-- 4) VERIFICAÇÃO
-- ------------------------------------------------------------
SELECT g.id_grupo_acesso, g.grupo_acesso_nome, a.acesso_tela_viewname, a.acesso_tela_nome, a.ativo
FROM `acessos_tela` a
INNER JOIN `grupos_acesso` g ON g.id_grupo_acesso = a.id_grupo_acesso
WHERE a.acesso_tela_viewname LIKE '%mercadopago%'
ORDER BY g.id_grupo_acesso, a.acesso_tela_viewname;
