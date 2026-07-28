-- ============================================================
-- Módulo Financeiro – Grupo de Acesso e Permissões de Tela
-- Executar uma única vez no ambiente desejado (dev/hml/prod)
-- ============================================================

-- 1. Novo grupo de acesso: Financeiro
INSERT INTO `grupos_acesso` (`id_grupo_acesso`, `grupo_acesso_nome`, `data_criacao`, `data_alteracao`)
VALUES (2, 'Financeiro', NOW(), NOW());

-- 2. Permissões de tela vinculadas ao grupo Financeiro (id_grupo_acesso = 2)
INSERT INTO `acessos_tela`
    (`id_grupo_acesso`, `acesso_tela_viewname`, `acesso_tela_nome`, `data_criacao`, `data_alteracao`)
VALUES
    (2, 'FinanceiroDashboard',     'Dashboard Financeiro',   NOW(), NOW()),
    (2, 'FinanceiroDespesas',      'Despesas',               NOW(), NOW()),
    (2, 'FinanceiroMensalidades',  'Mensalidades',           NOW(), NOW()),
    (2, 'FinanceiroEstoquePlacas', 'Estoque de Placas',      NOW(), NOW()),
    (2, 'FinanceiroResets',        'Histórico de Resets',    NOW(), NOW());
