-- ============================================================
-- SCRIPT COMPLETO DE DEPLOY — módulos Financeiro (Mensalidades)
-- e Cadastro de Cliente (endereço + vínculo com estoque)
--
-- 100% idempotente: pode ser rodado quantas vezes precisar, em
-- qualquer ambiente (mesmo que parte dele já tenha sido aplicada),
-- sem duplicar dados nem quebrar.
--
-- Rode com:
--   mysql -u root -p projetomaquina_hml < 2026_07_26_deploy_completo.sql
-- (troque "projetomaquina_hml" pelo nome do banco do ambiente)
-- ============================================================


-- ============================================================
-- PARTE 1 — Grupo de acesso "financeiro" (id_grupo_acesso = 9)
-- + permissões de Início / Despesas / Estoque
-- ============================================================

INSERT INTO `grupos_acesso` (`id_grupo_acesso`, `grupo_acesso_nome`)
SELECT 9, 'financeiro'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `grupos_acesso` WHERE `id_grupo_acesso` = 9
);

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


-- ============================================================
-- PARTE 2 — Tabela `mensalidades` completa (base + boletos Efí
-- + alertas) e permissão de Inadimplência para quem já vê o
-- Início do Financeiro
-- ============================================================

CREATE TABLE IF NOT EXISTS `mensalidades` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_cliente` INT UNSIGNED NOT NULL,
    `valor` DECIMAL(10,2) NOT NULL,
    `vencimento` DATE NOT NULL,
    `status` ENUM('pago','pendente','atrasado') NOT NULL DEFAULT 'pendente',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_mensalidades_id_cliente` (`id_cliente`),
    KEY `idx_mensalidades_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @dbname = DATABASE();
SET @tablename = 'mensalidades';

SET @columnname = 'efi_charge_id';
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@dbname AND table_name=@tablename AND column_name=@columnname) = 0, 'ALTER TABLE `mensalidades` ADD COLUMN `efi_charge_id` VARCHAR(255) NULL AFTER `status`', 'SELECT 1'));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @columnname = 'boleto_barcode';
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@dbname AND table_name=@tablename AND column_name=@columnname) = 0, 'ALTER TABLE `mensalidades` ADD COLUMN `boleto_barcode` TEXT NULL AFTER `efi_charge_id`', 'SELECT 1'));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @columnname = 'boleto_link';
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@dbname AND table_name=@tablename AND column_name=@columnname) = 0, 'ALTER TABLE `mensalidades` ADD COLUMN `boleto_link` TEXT NULL AFTER `boleto_barcode`', 'SELECT 1'));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @columnname = 'boleto_pdf';
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@dbname AND table_name=@tablename AND column_name=@columnname) = 0, 'ALTER TABLE `mensalidades` ADD COLUMN `boleto_pdf` TEXT NULL AFTER `boleto_link`', 'SELECT 1'));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @columnname = 'boleto_status';
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@dbname AND table_name=@tablename AND column_name=@columnname) = 0, 'ALTER TABLE `mensalidades` ADD COLUMN `boleto_status` VARCHAR(255) NULL AFTER `boleto_pdf`', 'SELECT 1'));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @columnname = 'alerta_5_enviado_em';
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@dbname AND table_name=@tablename AND column_name=@columnname) = 0, 'ALTER TABLE `mensalidades` ADD COLUMN `alerta_5_enviado_em` DATE NULL AFTER `boleto_status`', 'SELECT 1'));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @columnname = 'alerta_3_enviado_em';
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@dbname AND table_name=@tablename AND column_name=@columnname) = 0, 'ALTER TABLE `mensalidades` ADD COLUMN `alerta_3_enviado_em` DATE NULL AFTER `alerta_5_enviado_em`', 'SELECT 1'));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @columnname = 'alerta_0_enviado_em';
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@dbname AND table_name=@tablename AND column_name=@columnname) = 0, 'ALTER TABLE `mensalidades` ADD COLUMN `alerta_0_enviado_em` DATE NULL AFTER `alerta_3_enviado_em`', 'SELECT 1'));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Permissão de Inadimplência para todo grupo que já vê o Início do Financeiro (inclui o grupo 9 criado na Parte 1)
INSERT INTO `acessos_tela` (`id_grupo_acesso`, `acesso_tela_viewname`, `acesso_tela_nome`, `ativo`, `data_criacao`, `data_alteracao`)
SELECT DISTINCT id_grupo_acesso, 'financeiro-inadimplencia', 'Financeiro - Inadimplencia', 1, NOW(), NOW()
FROM `acessos_tela`
WHERE acesso_tela_viewname = 'financeiro-home'
  AND id_grupo_acesso NOT IN (
      SELECT id_grupo_acesso FROM `acessos_tela` WHERE acesso_tela_viewname = 'financeiro-inadimplencia'
  );

-- Registra as migrations Laravel correspondentes como já aplicadas
SET @nextbatch = (SELECT COALESCE(MAX(batch), 0) + 1 FROM `migrations`);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_06_22_000006_add_boleto_fields_to_mensalidades_table', @nextbatch
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_06_22_000006_add_boleto_fields_to_mensalidades_table');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_07_25_000001_add_alerta_enviado_fields_to_mensalidades_table', @nextbatch
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_07_25_000001_add_alerta_enviado_fields_to_mensalidades_table');


-- ============================================================
-- PARTE 3 — Permissões das telas de gestão de Mensalidades
-- (listar, criar, detalhar, atualizar, excluir, boleto)
-- para o grupo financeiro (id 9)
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


-- ============================================================
-- PARTE 4 — Cadastro de Cliente: campos de endereço em `clientes`
-- + tabela `cliente_estoque_produto` (vínculo cliente x estoque)
-- ============================================================

SET @dbname = DATABASE();
SET @tablename = 'clientes';

SET @columnname = 'cliente_cep';
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@dbname AND table_name=@tablename AND column_name=@columnname) = 0, 'ALTER TABLE `clientes` ADD COLUMN `cliente_cep` VARCHAR(10) NULL AFTER `cliente_cpf_cnpj`', 'SELECT 1'));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @columnname = 'cliente_logradouro';
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@dbname AND table_name=@tablename AND column_name=@columnname) = 0, 'ALTER TABLE `clientes` ADD COLUMN `cliente_logradouro` VARCHAR(150) NULL AFTER `cliente_cep`', 'SELECT 1'));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @columnname = 'cliente_numero';
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@dbname AND table_name=@tablename AND column_name=@columnname) = 0, 'ALTER TABLE `clientes` ADD COLUMN `cliente_numero` VARCHAR(20) NULL AFTER `cliente_logradouro`', 'SELECT 1'));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @columnname = 'cliente_bairro';
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@dbname AND table_name=@tablename AND column_name=@columnname) = 0, 'ALTER TABLE `clientes` ADD COLUMN `cliente_bairro` VARCHAR(100) NULL AFTER `cliente_numero`', 'SELECT 1'));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @columnname = 'cliente_cidade';
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@dbname AND table_name=@tablename AND column_name=@columnname) = 0, 'ALTER TABLE `clientes` ADD COLUMN `cliente_cidade` VARCHAR(100) NULL AFTER `cliente_bairro`', 'SELECT 1'));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @columnname = 'cliente_uf';
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@dbname AND table_name=@tablename AND column_name=@columnname) = 0, 'ALTER TABLE `clientes` ADD COLUMN `cliente_uf` VARCHAR(2) NULL AFTER `cliente_cidade`', 'SELECT 1'));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `cliente_estoque_produto` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_cliente`          INT UNSIGNED NOT NULL,
    `id_estoque_produto`  BIGINT UNSIGNED NOT NULL,
    `quantidade`          INT UNSIGNED NOT NULL DEFAULT 1,
    `created_at`          TIMESTAMP NULL DEFAULT NULL,
    `updated_at`          TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_cliente_estoque_produto_cliente` (`id_cliente`),
    KEY `idx_cliente_estoque_produto_produto` (`id_estoque_produto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @nextbatch = (SELECT COALESCE(MAX(batch), 0) + 1 FROM `migrations`);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_07_25_000002_add_endereco_fields_to_clientes_table', @nextbatch
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_07_25_000002_add_endereco_fields_to_clientes_table');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_07_25_000003_create_cliente_estoque_produto_table', @nextbatch
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_07_25_000003_create_cliente_estoque_produto_table');


-- ============================================================
-- PARTE 5 — Cadastro de Cliente dentro do módulo Financeiro:
-- telas próprias (listar/novo) + acesso às telas compartilhadas
-- de detalhar/editar cliente (ainda no layout Admin) para o
-- grupo financeiro (id 9)
-- ============================================================

INSERT INTO `acessos_tela`
    (`id_grupo_acesso`, `acesso_tela_viewname`, `acesso_tela_nome`, `data_criacao`, `data_alteracao`, `ativo`)
SELECT * FROM (
    SELECT 9 AS id_grupo_acesso, 'financeiro-clientes'            AS acesso_tela_viewname, 'Financeiro - Clientes'            AS acesso_tela_nome, NOW() AS data_criacao, NOW() AS data_alteracao, 1 AS ativo UNION ALL
    SELECT 9, 'financeiro-clientes-criar',     'Financeiro - Novo cliente',       NOW(), NOW(), 1 UNION ALL
    SELECT 9, 'financeiro-clientes-registrar', 'Financeiro - Registrar cliente',  NOW(), NOW(), 1 UNION ALL
    SELECT 9, 'usuario-detalhar',              'Financeiro - Detalhar cliente',   NOW(), NOW(), 1 UNION ALL
    SELECT 9, 'usuario-editar',                'Financeiro - Editar cliente',     NOW(), NOW(), 1 UNION ALL
    SELECT 9, 'usuario-atualizar',             'Financeiro - Atualizar cliente',  NOW(), NOW(), 1
) AS novas
WHERE NOT EXISTS (
    SELECT 1 FROM `acessos_tela` existentes
    WHERE existentes.id_grupo_acesso = novas.id_grupo_acesso
      AND existentes.acesso_tela_viewname = novas.acesso_tela_viewname
);


-- ============================================================
-- CONFERÊNCIA FINAL
-- ============================================================

SHOW COLUMNS FROM `mensalidades`;
SHOW COLUMNS FROM `clientes`;
SHOW COLUMNS FROM `cliente_estoque_produto`;

SELECT id_grupo_acesso, acesso_tela_viewname, acesso_tela_nome, ativo
FROM `acessos_tela`
WHERE id_grupo_acesso = 9
ORDER BY acesso_tela_viewname;
