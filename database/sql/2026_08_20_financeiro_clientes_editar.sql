-- ============================================================
-- Libera acesso às novas telas de edição de cliente dentro do
-- módulo Financeiro (financeiro-clientes-editar/atualizar) para
-- o grupo Financeiro (id 9). Substitui a dependência das telas
-- antigas usuario-editar/usuario-atualizar (Admin/Usuarios).
-- ============================================================

INSERT INTO `acessos_tela`
    (`id_grupo_acesso`, `acesso_tela_viewname`, `acesso_tela_nome`, `data_criacao`, `data_alteracao`, `ativo`)
SELECT * FROM (
    SELECT 9 AS id_grupo_acesso, 'financeiro-clientes-editar'    AS acesso_tela_viewname, 'Financeiro - Editar cliente'    AS acesso_tela_nome, NOW() AS data_criacao, NOW() AS data_alteracao, 1 AS ativo UNION ALL
    SELECT 9, 'financeiro-clientes-atualizar', 'Financeiro - Atualizar cliente', NOW(), NOW(), 1
) AS novas
WHERE NOT EXISTS (
    SELECT 1 FROM `acessos_tela` existentes
    WHERE existentes.id_grupo_acesso = novas.id_grupo_acesso
      AND existentes.acesso_tela_viewname = novas.acesso_tela_viewname
);
