-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 22-Mar-2024 às 02:47
-- Versão do servidor: 10.4.24-MariaDB
-- versão do PHP: 7.4.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `projeto_maquina`
--

-- --------------------------------------------------------

--
-- Estrutura da tabela `acessos_tela`
--

CREATE TABLE `acessos_tela` (
  `id_grupo_acesso` int(11) NOT NULL,
  `acesso_tela_viewname` varchar(100) DEFAULT NULL,
  `acesso_tela_nome` varchar(100) DEFAULT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_alteracao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Extraindo dados da tabela `acessos_tela`
--

INSERT INTO `acessos_tela` (`id_grupo_acesso`, `acesso_tela_viewname`, `acesso_tela_nome`, `data_criacao`, `data_alteracao`) VALUES
(1, 'Dashboard', 'Dashboard', '2024-03-14 21:34:32', '2024-03-14 21:34:32');

-- --------------------------------------------------------

--
-- Estrutura da tabela `clientes`
--

CREATE TABLE `clientes` (
  `id_cliente` int(11) NOT NULL,
  `cliente_nome` varchar(100) NOT NULL,
  `cliente_celular` varchar(100) NOT NULL,
  `cliente_email` varchar(100) NOT NULL,
  `cliente_cpf_cnpj` varchar(100) NOT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_alteracao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Extraindo dados da tabela `clientes`
--

INSERT INTO `clientes` (`id_cliente`, `cliente_nome`, `cliente_celular`, `cliente_email`, `cliente_cpf_cnpj`, `data_criacao`, `data_alteracao`) VALUES
(1, 'Usuário Teste atualizar PHPUNIT API', '21964183013', 'testephpunit@gmail.com', '15798684792', '2024-03-14 21:34:34', '2024-03-14 21:34:34');

-- --------------------------------------------------------

--
-- Estrutura da tabela `extrato_cliente`
--

CREATE TABLE `extrato_cliente` (
  `id_extrato_cliente` int(11) NOT NULL,
  `id_cliente` int(11) DEFAULT NULL,
  `extrato_operacao_tipo` varchar(50) DEFAULT NULL,
  `extrato_operacao_valor` float DEFAULT NULL,
  `extrato_operacao_status` varchar(50) DEFAULT NULL,
  `extrato_operacao_saldo` float DEFAULT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_alteracao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estrutura da tabela `extrato_maquina`
--

CREATE TABLE `extrato_maquina` (
  `id_extrato_maquina` int(11) NOT NULL,
  `id_maquina` int(11) DEFAULT NULL,
  `extrato_operacao_tipo` varchar(50) DEFAULT NULL,
  `extrato_operacao_valor` float DEFAULT NULL,
  `extrato_operacao_status` varchar(50) DEFAULT NULL,
  `extrato_operacao_saldo` float DEFAULT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_alteracao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estrutura da tabela `grupos_acesso`
--

CREATE TABLE `grupos_acesso` (
  `id_grupo_acesso` int(11) NOT NULL,
  `grupo_acesso_nome` varchar(100) DEFAULT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_alteracao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Extraindo dados da tabela `grupos_acesso`
--

INSERT INTO `grupos_acesso` (`id_grupo_acesso`, `grupo_acesso_nome`, `data_criacao`, `data_alteracao`) VALUES
(1, 'Teste atualização grupo', '2024-03-14 21:34:41', '2024-03-14 21:34:41');

-- --------------------------------------------------------

--
-- Estrutura da tabela `logs`
--

CREATE TABLE `logs` (
  `id` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `descricao` varchar(200) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `acao` varchar(100) DEFAULT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estrutura da tabela `maquinas`
--

CREATE TABLE `maquinas` (
  `id_maquina` int(11) NOT NULL,
  `maquina_referencia` varchar(100) DEFAULT NULL,
  `maquina_nome` varchar(100) DEFAULT NULL,
  `maquina_status` varchar(50) DEFAULT NULL,
  `maquina_ultimo_contato` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_alteracao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Extraindo dados da tabela `maquinas`
--

INSERT INTO `maquinas` (`id_maquina`, `maquina_referencia`, `maquina_nome`, `maquina_status`, `maquina_ultimo_contato`, `data_criacao`, `data_alteracao`) VALUES
(1, 'Teste máquina referencia pHpunit', 'Máquina Teste atualizar PHPUNIT API', '0', '2024-03-14 21:34:43', '2024-03-14 21:34:43', '2024-03-14 21:34:43');

-- --------------------------------------------------------

--
-- Estrutura da tabela `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '2014_10_12_000000_create_users_table', 1),
(2, '2019_12_14_000001_create_personal_access_tokens_table', 1);

-- --------------------------------------------------------

--
-- Estrutura da tabela `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'Luiz Felipe', 'felipelearaujo@gmail.com', NULL, '$2a$12$ft9xYd4S16pE.rGqaddYdeeGJgT7SUgLT1ZWhH7I6l.GaKvPWfyh6', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura da tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `id_grupo_acesso` int(11) DEFAULT NULL,
  `id_cliente` int(11) DEFAULT NULL,
  `usuario_nome` varchar(100) NOT NULL,
  `usuario_email` varchar(100) NOT NULL,
  `usuario_ultimo_acesso` varchar(100) NOT NULL DEFAULT current_timestamp(),
  `usuario_login` varchar(100) NOT NULL,
  `usuario_senha` varchar(100) NOT NULL,
  `data_inclusao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_alteracao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Extraindo dados da tabela `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `id_grupo_acesso`, `id_cliente`, `usuario_nome`, `usuario_email`, `usuario_ultimo_acesso`, `usuario_login`, `usuario_senha`, `data_inclusao`, `data_alteracao`) VALUES
(1, 1, 1, 'Usuário Teste PHPUNIT API', 'usuario@gmail.com', '2024-03-14 18:34:44', 'usuario1', 'senha1', '2024-03-14 21:34:44', '2024-03-14 21:34:44');

--
-- Índices para tabelas despejadas
--

--
-- Índices para tabela `acessos_tela`
--
ALTER TABLE `acessos_tela`
  ADD PRIMARY KEY (`id_grupo_acesso`);

--
-- Índices para tabela `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id_cliente`);

--
-- Índices para tabela `extrato_cliente`
--
ALTER TABLE `extrato_cliente`
  ADD PRIMARY KEY (`id_extrato_cliente`);

--
-- Índices para tabela `extrato_maquina`
--
ALTER TABLE `extrato_maquina`
  ADD PRIMARY KEY (`id_extrato_maquina`);

--
-- Índices para tabela `grupos_acesso`
--
ALTER TABLE `grupos_acesso`
  ADD PRIMARY KEY (`id_grupo_acesso`);

--
-- Índices para tabela `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `maquinas`
--
ALTER TABLE `maquinas`
  ADD PRIMARY KEY (`id_maquina`);

--
-- Índices para tabela `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`);

--
-- Índices para tabela `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- Índices para tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`);

--
-- AUTO_INCREMENT de tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `acessos_tela`
--
ALTER TABLE `acessos_tela`
  MODIFY `id_grupo_acesso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id_cliente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `extrato_cliente`
--
ALTER TABLE `extrato_cliente`
  MODIFY `id_extrato_cliente` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `extrato_maquina`
--
ALTER TABLE `extrato_maquina`
  MODIFY `id_extrato_maquina` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `grupos_acesso`
--
ALTER TABLE `grupos_acesso`
  MODIFY `id_grupo_acesso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `maquinas`
--
ALTER TABLE `maquinas`
  MODIFY `id_maquina` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
