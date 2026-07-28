-- ============================================================
-- Permissões das telas de Mensalidades para o grupo "financeiro" (id_grupo_acesso = 9)
-- Execute este script na base da API (mesma base usada por acessos_tela)
-- Idempotente: só cria as permissões que ainda não existirem.
-- ============================================================

INSERT INTO `acessos_tela`
    (`id_grupo_acesso`, `acesso_tela_viewname`, `acesso_tela_nome`, `data_criacao`, `data_alteracao`, `ativo`)
SELECT * FROM (
    SELECT 9 AS id_grupo_acesso, 'financeiro-mensalidades'               AS acesso_tela_viewname, 'Financeiro - Mensalidades'               AS acesso_tela_nome, NOW() AS data_criacao, NOW() AS data_alteracao, 1 AS ativo UNION ALL
    SELECT 9, 'financeiro-mensalidades-criar',        'Financeiro - Criar mensalidade',        NOW(), NOW(), 1 UNION ALL
    SELECT 9, 'financeiro-mensalidades-registrar',    'Financeiro - Registrar mensalidade',    NOW(), NOW(), 1 UNION ALL
    SELECT 9, 'financeiro-mensalidades-detalhar',     'Financeiro - Detalhar mensalidade',     NOW(), NOW(), 1 UNION ALL
    SELECT 9, 'financeiro-mensalidades-atualizar',    'Financeiro - Atualizar mensalidade',    NOW(), NOW(), 1 UNION ALL
    SELECT 9, 'financeiro-mensalidades-excluir',      'Financeiro - Excluir mensalidade',      NOW(), NOW(), 1 UNION ALL
    SELECT 9, 'financeiro-mensalidades-boleto-gerar',    'Financeiro - Gerar boleto',    NOW(), NOW(), 1 UNION ALL
    SELECT 9, 'financeiro-mensalidades-boleto-cancelar', 'Financeiro - Cancelar boleto', NOW(), NOW(), 1 UNION ALL
    SELECT 9, 'financeiro-mensalidades-boleto-reenviar', 'Financeiro - Reenviar boleto', NOW(), NOW(), 1
) AS novas
WHERE NOT EXISTS (
    SELECT 1 FROM `acessos_tela` existentes
    WHERE existentes.id_grupo_acesso = novas.id_grupo_acesso
      AND existentes.acesso_tela_viewname = novas.acesso_tela_viewname
);

-- Conferir
SELECT id_grupo_acesso, acesso_tela_viewname, acesso_tela_nome, ativo
FROM acessos_tela
WHERE id_grupo_acesso = 9 AND acesso_tela_viewname LIKE 'financeiro-mensalidades%'
ORDER BY acesso_tela_viewname;
