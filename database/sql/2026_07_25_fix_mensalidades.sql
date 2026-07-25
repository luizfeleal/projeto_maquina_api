-- ========================================================
-- Script idempotente: garante schema completo de `mensalidades`
-- + permissao da tela financeiro-inadimplencia
-- Rode com: mysql -u root -p projetomaquina_hml < 2026_07_25_fix_mensalidades.sql
-- ========================================================

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
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @columnname = 'boleto_barcode';
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@dbname AND table_name=@tablename AND column_name=@columnname) = 0, 'ALTER TABLE `mensalidades` ADD COLUMN `boleto_barcode` TEXT NULL AFTER `efi_charge_id`', 'SELECT 1'));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @columnname = 'boleto_link';
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@dbname AND table_name=@tablename AND column_name=@columnname) = 0, 'ALTER TABLE `mensalidades` ADD COLUMN `boleto_link` TEXT NULL AFTER `boleto_barcode`', 'SELECT 1'));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @columnname = 'boleto_pdf';
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@dbname AND table_name=@tablename AND column_name=@columnname) = 0, 'ALTER TABLE `mensalidades` ADD COLUMN `boleto_pdf` TEXT NULL AFTER `boleto_link`', 'SELECT 1'));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @columnname = 'boleto_status';
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@dbname AND table_name=@tablename AND column_name=@columnname) = 0, 'ALTER TABLE `mensalidades` ADD COLUMN `boleto_status` VARCHAR(255) NULL AFTER `boleto_pdf`', 'SELECT 1'));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @columnname = 'alerta_5_enviado_em';
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@dbname AND table_name=@tablename AND column_name=@columnname) = 0, 'ALTER TABLE `mensalidades` ADD COLUMN `alerta_5_enviado_em` DATE NULL AFTER `boleto_status`', 'SELECT 1'));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @columnname = 'alerta_3_enviado_em';
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@dbname AND table_name=@tablename AND column_name=@columnname) = 0, 'ALTER TABLE `mensalidades` ADD COLUMN `alerta_3_enviado_em` DATE NULL AFTER `alerta_5_enviado_em`', 'SELECT 1'));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @columnname = 'alerta_0_enviado_em';
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@dbname AND table_name=@tablename AND column_name=@columnname) = 0, 'ALTER TABLE `mensalidades` ADD COLUMN `alerta_0_enviado_em` DATE NULL AFTER `alerta_3_enviado_em`', 'SELECT 1'));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Registra as migrations como aplicadas (evita o Laravel tentar rodar de novo via artisan migrate)
SET @nextbatch = (SELECT COALESCE(MAX(batch), 0) + 1 FROM `migrations`);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_06_22_000006_add_boleto_fields_to_mensalidades_table', @nextbatch
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_06_22_000006_add_boleto_fields_to_mensalidades_table');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_07_25_000001_add_alerta_enviado_fields_to_mensalidades_table', @nextbatch
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_07_25_000001_add_alerta_enviado_fields_to_mensalidades_table');

-- Permissao da tela financeiro-inadimplencia, copiando de quem ja ve financeiro-home
INSERT INTO `acessos_tela` (`id_grupo_acesso`, `acesso_tela_viewname`, `acesso_tela_nome`, `ativo`, `data_criacao`, `data_alteracao`)
SELECT DISTINCT id_grupo_acesso, 'financeiro-inadimplencia', 'Financeiro - Inadimplencia', 1, NOW(), NOW()
FROM `acessos_tela`
WHERE acesso_tela_viewname = 'financeiro-home'
  AND id_grupo_acesso NOT IN (
      SELECT id_grupo_acesso FROM `acessos_tela` WHERE acesso_tela_viewname = 'financeiro-inadimplencia'
  );

-- Conferencia final
SHOW COLUMNS FROM `mensalidades`;
SELECT id_grupo_acesso, acesso_tela_viewname, acesso_tela_nome, ativo
FROM `acessos_tela`
WHERE acesso_tela_viewname IN ('financeiro-home', 'financeiro-inadimplencia');
