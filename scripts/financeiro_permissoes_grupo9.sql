-- ============================================================
-- Permissões do grupo "financeiro" (id_grupo_acesso = 9)
-- Execute este script na base da API (mesma base usada por acessos_tela)
-- Idempotente: só cria o grupo/permissões que ainda não existirem.
-- ============================================================

-- 1) Garantir que o grupo de acesso "financeiro" (id 9) existe
INSERT INTO `grupos_acesso` (`id_grupo_acesso`, `grupo_acesso_nome`)
SELECT 9, 'financeiro'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `grupos_acesso` WHERE `id_grupo_acesso` = 9
);

-- 2) Garantir as permissões das rotas do módulo Financeiro para o grupo 9
INSERT INTO `acessos_tela`
    (`id_grupo_acesso`, `acesso_tela_viewname`, `acesso_tela_nome`, `data_criacao`, `data_alteracao`, `ativo`)
SELECT * FROM (
    SELECT 9 AS id_grupo_acesso, 'financeiro-home'               AS acesso_tela_viewname, 'Financeiro - Início'             AS acesso_tela_nome, NOW() AS data_criacao, NOW() AS data_alteracao, 1 AS ativo UNION ALL
    SELECT 9, 'financeiro-despesas',           'Financeiro - Despesas',           NOW(), NOW(), 1 UNION ALL
    SELECT 9, 'financeiro-despesas-criar',     'Financeiro - Criar despesa',      NOW(), NOW(), 1 UNION ALL
    SELECT 9, 'financeiro-despesas-registrar', 'Financeiro - Registrar despesa',  NOW(), NOW(), 1 UNION ALL
    SELECT 9, 'financeiro-despesas-excluir',   'Financeiro - Excluir despesa',    NOW(), NOW(), 1 UNION ALL
    SELECT 9, 'financeiro-despesas-detalhar',  'Financeiro - Detalhar despesa',   NOW(), NOW(), 1 UNION ALL
    SELECT 9, 'financeiro-estoque',            'Financeiro - Estoque',            NOW(), NOW(), 1 UNION ALL
    SELECT 9, 'financeiro-estoque-criar',      'Financeiro - Criar produto',      NOW(), NOW(), 1 UNION ALL
    SELECT 9, 'financeiro-estoque-registrar',  'Financeiro - Registrar produto',  NOW(), NOW(), 1 UNION ALL
    SELECT 9, 'financeiro-estoque-excluir',    'Financeiro - Excluir produto',    NOW(), NOW(), 1 UNION ALL
    SELECT 9, 'financeiro-estoque-detalhar',   'Financeiro - Detalhar produto',   NOW(), NOW(), 1 UNION ALL
    SELECT 9, 'financeiro-estoque-editar',     'Financeiro - Editar produto',     NOW(), NOW(), 1 UNION ALL
    SELECT 9, 'financeiro-estoque-atualizar',  'Financeiro - Atualizar produto',  NOW(), NOW(), 1
) AS novas
WHERE NOT EXISTS (
    SELECT 1 FROM `acessos_tela` existentes
    WHERE existentes.id_grupo_acesso = novas.id_grupo_acesso
      AND existentes.acesso_tela_viewname = novas.acesso_tela_viewname
);

-- 3) Conferir
SELECT id_grupo_acesso, acesso_tela_viewname, acesso_tela_nome, ativo
FROM acessos_tela
WHERE id_grupo_acesso = 9
ORDER BY acesso_tela_viewname;
