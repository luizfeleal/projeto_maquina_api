-- ========================================================
-- Script idempotente: campos de endereço em `clientes` +
-- tabela `cliente_estoque_produto` (vínculo cliente x estoque)
-- Rode com: mysql -u root -p projetomaquina_hml < 2026_07_25_cadastro_cliente.sql
-- ========================================================

SET @dbname = DATABASE();
SET @tablename = 'clientes';

SET @columnname = 'cliente_cep';
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@dbname AND table_name=@tablename AND column_name=@columnname) = 0, 'ALTER TABLE `clientes` ADD COLUMN `cliente_cep` VARCHAR(10) NULL AFTER `cliente_cpf_cnpj`', 'SELECT 1'));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @columnname = 'cliente_logradouro';
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@dbname AND table_name=@tablename AND column_name=@columnname) = 0, 'ALTER TABLE `clientes` ADD COLUMN `cliente_logradouro` VARCHAR(150) NULL AFTER `cliente_cep`', 'SELECT 1'));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @columnname = 'cliente_numero';
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@dbname AND table_name=@tablename AND column_name=@columnname) = 0, 'ALTER TABLE `clientes` ADD COLUMN `cliente_numero` VARCHAR(20) NULL AFTER `cliente_logradouro`', 'SELECT 1'));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @columnname = 'cliente_bairro';
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@dbname AND table_name=@tablename AND column_name=@columnname) = 0, 'ALTER TABLE `clientes` ADD COLUMN `cliente_bairro` VARCHAR(100) NULL AFTER `cliente_numero`', 'SELECT 1'));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @columnname = 'cliente_cidade';
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@dbname AND table_name=@tablename AND column_name=@columnname) = 0, 'ALTER TABLE `clientes` ADD COLUMN `cliente_cidade` VARCHAR(100) NULL AFTER `cliente_bairro`', 'SELECT 1'));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @columnname = 'cliente_uf';
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@dbname AND table_name=@tablename AND column_name=@columnname) = 0, 'ALTER TABLE `clientes` ADD COLUMN `cliente_uf` VARCHAR(2) NULL AFTER `cliente_cidade`', 'SELECT 1'));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

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

-- Registra as migrations como aplicadas (evita o Laravel tentar rodar de novo via artisan migrate)
SET @nextbatch = (SELECT COALESCE(MAX(batch), 0) + 1 FROM `migrations`);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_07_25_000002_add_endereco_fields_to_clientes_table', @nextbatch
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_07_25_000002_add_endereco_fields_to_clientes_table');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_07_25_000003_create_cliente_estoque_produto_table', @nextbatch
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_07_25_000003_create_cliente_estoque_produto_table');

-- Conferência final
SHOW COLUMNS FROM `clientes`;
SHOW COLUMNS FROM `cliente_estoque_produto`;
