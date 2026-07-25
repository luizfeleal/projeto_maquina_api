-- ============================================================
-- Correção: usuário financeiro no grupo Cliente (id 2)
-- Execute este script na base da API
-- ============================================================

-- 1) Criar grupo financeiro (se não existir)
INSERT INTO `grupos_acesso` (`grupo_acesso_nome`, `data_criacao`, `data_alteracao`)
SELECT 'financeiro', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `grupos_acesso` WHERE `grupo_acesso_nome` = 'financeiro'
);

-- 2) Mover usuário financeiro para o grupo correto
UPDATE `usuarios` u
INNER JOIN `grupos_acesso` g ON g.grupo_acesso_nome = 'financeiro'
SET u.id_grupo_acesso = g.id_grupo_acesso
WHERE u.usuario_login = 'financeiro';

-- 3) Remover permissões financeiras do grupo Cliente (se foram criadas no id errado)
DELETE FROM `acessos_tela`
WHERE `id_grupo_acesso` IN (
    SELECT `id_grupo_acesso` FROM `grupos_acesso` WHERE `grupo_acesso_nome` = 'Cliente'
)
AND `acesso_tela_viewname` LIKE 'financeiro-%';

-- 4) Garantir permissão da home (rode financeiro_acesso.sql completo depois, ou só esta linha):
INSERT INTO `acessos_tela`
    (`id_grupo_acesso`, `acesso_tela_viewname`, `acesso_tela_nome`, `data_criacao`, `data_alteracao`, `ativo`)
SELECT g.id_grupo_acesso, 'financeiro-home', 'Dashboard Financeiro', NOW(), NOW(), 1
FROM `grupos_acesso` g
WHERE g.grupo_acesso_nome = 'financeiro'
  AND NOT EXISTS (
    SELECT 1 FROM `acessos_tela` x
    WHERE x.id_grupo_acesso = g.id_grupo_acesso AND x.acesso_tela_viewname = 'financeiro-home'
  );

-- Conferir
SELECT u.usuario_login, g.grupo_acesso_nome, g.id_grupo_acesso
FROM usuarios u
JOIN grupos_acesso g ON g.id_grupo_acesso = u.id_grupo_acesso
WHERE u.usuario_login = 'financeiro';
