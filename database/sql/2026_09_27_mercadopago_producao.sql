-- ============================================================
-- Mercado Pago: setup completo do schema em produção
-- Banco: o mesmo usado pela API (projeto_maquina_api)
-- Data: 2026-09-27
--
-- Contexto:
--   mercadopago_loja e mercadopago_pos nunca existiram em produção — foram
--   criadas manualmente só no ambiente de homologação, sem migration (por
--   isso não existe nenhum arquivo em database/migrations/ para elas).
--   Este script cria as duas do zero, já com o tipo correto em
--   qr_image/qr_data (LONGTEXT — a versão de homologação foi criada com um
--   tipo pequeno demais e precisou de correção depois, ao tentar salvar o
--   PNG em base64 do QR: ver migration
--   2026_09_27_000001_widen_mercadopago_pos_qr_columns.php).
--
--   Também inclui o que já estava pendente para produção em
--   2026_08_31_mercadopago_permissoes.sql (tipo_cred + permissões de tela)
--   e a coluna mp_ultima_consulta (migration
--   2026_09_26_000001_add_mp_ultima_consulta_to_cred_api_pix_table.php,
--   usada pelo polling de reconciliação para saber a partir de quando
--   buscar pagamentos novos por credencial).
--
-- Idempotente: pode ser executado mais de uma vez sem duplicar dados nem
-- falhar se alguma parte já tiver sido aplicada.
-- ============================================================

-- ------------------------------------------------------------
-- 1) cred_api_pix.tipo_cred aceitar 'mercadopago'
--    Seguro mesmo se já for VARCHAR (só reaplica o tipo, sem perda de
--    dados). Se for ENUM('efi','pagbank'), converte para VARCHAR
--    preservando os valores já gravados.
-- ------------------------------------------------------------
ALTER TABLE `cred_api_pix`
  MODIFY COLUMN `tipo_cred` VARCHAR(20) NOT NULL DEFAULT 'efi';

-- ------------------------------------------------------------
-- 2) cred_api_pix.mp_ultima_consulta — checkpoint do polling de
--    reconciliação (App\Console\Commands\PollMercadopagoTransactions),
--    uma linha por credencial.
-- ------------------------------------------------------------
SET @coluna_existe = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'cred_api_pix'
    AND COLUMN_NAME = 'mp_ultima_consulta'
);

SET @sql_mp_ultima_consulta = IF(
  @coluna_existe = 0,
  'ALTER TABLE `cred_api_pix` ADD COLUMN `mp_ultima_consulta` TIMESTAMP NULL DEFAULT NULL AFTER `tipo_cred`',
  'SELECT 1'
);

PREPARE stmt FROM @sql_mp_ultima_consulta;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- 3) mercadopago_loja
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mercadopago_loja` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_cliente` INT NOT NULL,
  `mp_user_id` VARCHAR(50) NOT NULL,
  `mp_store_id` VARCHAR(50) NOT NULL,
  `external_store_id` VARCHAR(60) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mercadopago_loja_id_cliente_index` (`id_cliente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4) mercadopago_pos
--    qr_image/qr_data já como LONGTEXT desde a criação (motivo do erro
--    corrigido em homologação — ver comentário no topo do arquivo).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mercadopago_pos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_cliente` INT NOT NULL,
  `id_local` INT NOT NULL,
  `id_maquina` INT NOT NULL,
  `id_mercadopago_loja` BIGINT UNSIGNED NOT NULL,
  `mp_pos_id` VARCHAR(40) NOT NULL,
  `external_pos_id` VARCHAR(40) NOT NULL,
  `qr_image` LONGTEXT NOT NULL,
  `qr_data` LONGTEXT NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mercadopago_pos_id_cliente_index` (`id_cliente`),
  KEY `mercadopago_pos_id_maquina_index` (`id_maquina`),
  KEY `mercadopago_pos_id_mercadopago_loja_index` (`id_mercadopago_loja`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5) Permissões de tela (acessos_tela) — rotas do Mercado Pago no painel.
--    Concede a permissão a todo grupo que já tem a equivalente do PagBank,
--    evitando fixar IDs de grupo manualmente.
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
-- 6) VERIFICAÇÃO
-- ------------------------------------------------------------
SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'cred_api_pix'
  AND COLUMN_NAME IN ('tipo_cred', 'mp_ultima_consulta');

SHOW CREATE TABLE `mercadopago_loja`;
SHOW CREATE TABLE `mercadopago_pos`;

SELECT g.id_grupo_acesso, g.grupo_acesso_nome, a.acesso_tela_viewname, a.acesso_tela_nome, a.ativo
FROM `acessos_tela` a
INNER JOIN `grupos_acesso` g ON g.id_grupo_acesso = a.id_grupo_acesso
WHERE a.acesso_tela_viewname LIKE '%mercadopago%'
ORDER BY g.id_grupo_acesso, a.acesso_tela_viewname;
