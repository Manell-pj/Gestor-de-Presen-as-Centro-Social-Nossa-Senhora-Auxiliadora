-- Schema inicial para sistema de assiduidade e RH
-- PHP procedural + MySQLi
-- MySQL 8+ / MariaDB compativel

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `gestor_assiduidade`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `gestor_assiduidade`;

-- --------------------------------------------------------
-- Papeis de utilizador
-- --------------------------------------------------------

CREATE TABLE `papeis` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(80) NOT NULL,
  `slug` VARCHAR(80) NOT NULL,
  `descricao` VARCHAR(255) DEFAULT NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_papeis_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Departamentos
-- --------------------------------------------------------

CREATE TABLE `departamentos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(120) NOT NULL,
  `codigo` VARCHAR(30) DEFAULT NULL,
  `responsavel_id` INT UNSIGNED DEFAULT NULL,
  `descricao` VARCHAR(255) DEFAULT NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_departamentos_codigo` (`codigo`),
  KEY `idx_departamentos_responsavel` (`responsavel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Utilizadores / colaboradores
-- --------------------------------------------------------

CREATE TABLE `utilizadores` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `departamento_id` INT UNSIGNED DEFAULT NULL,
  `numero_mecanografico` VARCHAR(50) DEFAULT NULL,
  `nome` VARCHAR(160) NOT NULL,
  `email` VARCHAR(160) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `telefone` VARCHAR(40) DEFAULT NULL,
  `cargo` VARCHAR(120) DEFAULT NULL,
  `data_nascimento` DATE DEFAULT NULL,
  `data_admissao` DATE DEFAULT NULL,
  `tipo_contrato` VARCHAR(80) DEFAULT NULL,
  `foto` VARCHAR(255) DEFAULT NULL,
  `pin_ponto` VARCHAR(20) DEFAULT NULL,
  `codigo_cartao` VARCHAR(80) DEFAULT NULL,
  `codigo_biometrico` VARCHAR(80) DEFAULT NULL,
  `ultimo_login_at` DATETIME DEFAULT NULL,
  `estado` ENUM('ativo','suspenso','inativo') NOT NULL DEFAULT 'ativo',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_utilizadores_email` (`email`),
  UNIQUE KEY `uk_utilizadores_numero_mecanografico` (`numero_mecanografico`),
  UNIQUE KEY `uk_utilizadores_pin_ponto` (`pin_ponto`),
  UNIQUE KEY `uk_utilizadores_codigo_cartao` (`codigo_cartao`),
  UNIQUE KEY `uk_utilizadores_codigo_biometrico` (`codigo_biometrico`),
  KEY `idx_utilizadores_departamento` (`departamento_id`),
  KEY `idx_utilizadores_estado` (`estado`),
  CONSTRAINT `fk_utilizadores_departamento`
    FOREIGN KEY (`departamento_id`) REFERENCES `departamentos` (`id`)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `departamentos`
  ADD CONSTRAINT `fk_departamentos_responsavel`
  FOREIGN KEY (`responsavel_id`) REFERENCES `utilizadores` (`id`)
  ON UPDATE CASCADE
  ON DELETE SET NULL;

-- --------------------------------------------------------
-- Relacao utilizadores <-> papeis
-- --------------------------------------------------------

CREATE TABLE `utilizador_papeis` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `utilizador_id` INT UNSIGNED NOT NULL,
  `papel_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_utilizador_papel` (`utilizador_id`, `papel_id`),
  KEY `idx_utilizador_papeis_papel` (`papel_id`),
  CONSTRAINT `fk_utilizador_papeis_utilizador`
    FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT `fk_utilizador_papeis_papel`
    FOREIGN KEY (`papel_id`) REFERENCES `papeis` (`id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Turnos
-- --------------------------------------------------------

CREATE TABLE `turnos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(120) NOT NULL,
  `codigo` VARCHAR(40) DEFAULT NULL,
  `hora_entrada` TIME NOT NULL,
  `hora_saida` TIME NOT NULL,
  `inicio_pausa` TIME DEFAULT NULL,
  `fim_pausa` TIME DEFAULT NULL,
  `tolerancia_entrada_min` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `tolerancia_saida_min` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `horas_previstas` DECIMAL(5,2) NOT NULL DEFAULT 8.00,
  `turno_noturno` TINYINT(1) NOT NULL DEFAULT 0,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_turnos_codigo` (`codigo`),
  KEY `idx_turnos_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Horarios atribuidos aos utilizadores
-- --------------------------------------------------------

CREATE TABLE `horarios_turno` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `utilizador_id` INT UNSIGNED NOT NULL,
  `turno_id` INT UNSIGNED NOT NULL,
  `data_inicio` DATE NOT NULL,
  `data_fim` DATE DEFAULT NULL,
  `dia_semana` TINYINT UNSIGNED DEFAULT NULL COMMENT '1=segunda, 7=domingo; NULL=todos os dias no periodo',
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_horarios_utilizador` (`utilizador_id`),
  KEY `idx_horarios_turno` (`turno_id`),
  KEY `idx_horarios_periodo` (`data_inicio`, `data_fim`),
  CONSTRAINT `fk_horarios_turno_utilizador`
    FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT `fk_horarios_turno_turno`
    FOREIGN KEY (`turno_id`) REFERENCES `turnos` (`id`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT `chk_horarios_turno_dia_semana`
    CHECK (`dia_semana` IS NULL OR `dia_semana` BETWEEN 1 AND 7)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Dispositivos biometricos / relogios de ponto
-- --------------------------------------------------------

CREATE TABLE `dispositivos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(120) NOT NULL,
  `marca` VARCHAR(80) DEFAULT NULL,
  `modelo` VARCHAR(80) DEFAULT NULL,
  `numero_serie` VARCHAR(120) DEFAULT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `porta` INT UNSIGNED DEFAULT NULL,
  `localizacao` VARCHAR(160) DEFAULT NULL,
  `tipo` ENUM('biometrico','rfid','facial','manual','outro') NOT NULL DEFAULT 'biometrico',
  `estado` ENUM('ativo','inativo','offline','manutencao') NOT NULL DEFAULT 'ativo',
  `ultima_sincronizacao_at` DATETIME DEFAULT NULL,
  `observacoes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dispositivos_numero_serie` (`numero_serie`),
  KEY `idx_dispositivos_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Registos de ponto
-- --------------------------------------------------------

CREATE TABLE `registos_ponto` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `utilizador_id` INT UNSIGNED NOT NULL,
  `dispositivo_id` INT UNSIGNED DEFAULT NULL,
  `tipo` ENUM('entrada','saida','inicio_pausa','fim_pausa') NOT NULL,
  `data_hora` DATETIME NOT NULL,
  `origem` ENUM('manual','dispositivo','importacao','api') NOT NULL DEFAULT 'manual',
  `estado` ENUM('valido','pendente','corrigido','rejeitado','duplicado','anulado') NOT NULL DEFAULT 'valido',
  `observacoes` VARCHAR(255) DEFAULT NULL,
  `criado_por` INT UNSIGNED DEFAULT NULL,
  `atualizado_por` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_registos_utilizador_data` (`utilizador_id`, `data_hora`),
  KEY `idx_registos_dispositivo` (`dispositivo_id`),
  KEY `idx_registos_tipo` (`tipo`),
  KEY `idx_registos_estado` (`estado`),
  KEY `idx_registos_criado_por` (`criado_por`),
  KEY `idx_registos_atualizado_por` (`atualizado_por`),
  CONSTRAINT `fk_registos_ponto_utilizador`
    FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT `fk_registos_ponto_dispositivo`
    FOREIGN KEY (`dispositivo_id`) REFERENCES `dispositivos` (`id`)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT `fk_registos_ponto_criado_por`
    FOREIGN KEY (`criado_por`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT `fk_registos_ponto_atualizado_por`
    FOREIGN KEY (`atualizado_por`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tipos de ausencia
-- --------------------------------------------------------

CREATE TABLE `tipos_ausencia` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(120) NOT NULL,
  `descricao` VARCHAR(255) DEFAULT NULL,
  `remunerada` TINYINT(1) NOT NULL DEFAULT 1,
  `desconta_ferias` TINYINT(1) NOT NULL DEFAULT 0,
  `exige_justificativo` TINYINT(1) NOT NULL DEFAULT 0,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tipos_ausencia_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Pedidos de ausencia / ferias / faltas
-- --------------------------------------------------------

CREATE TABLE `pedidos_ausencia` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `utilizador_id` INT UNSIGNED NOT NULL,
  `tipo_ausencia_id` INT UNSIGNED NOT NULL,
  `data_inicio` DATE NOT NULL,
  `data_fim` DATE NOT NULL,
  `hora_inicio` TIME DEFAULT NULL,
  `hora_fim` TIME DEFAULT NULL,
  `total_dias` DECIMAL(6,2) DEFAULT NULL,
  `total_horas` DECIMAL(6,2) DEFAULT NULL,
  `motivo` TEXT DEFAULT NULL,
  `ficheiro_justificativo` VARCHAR(255) DEFAULT NULL,
  `estado` ENUM('pendente','aprovado','rejeitado','cancelado') NOT NULL DEFAULT 'pendente',
  `aprovado_por` INT UNSIGNED DEFAULT NULL,
  `aprovado_at` DATETIME DEFAULT NULL,
  `observacoes_aprovacao` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pedidos_utilizador` (`utilizador_id`),
  KEY `idx_pedidos_tipo` (`tipo_ausencia_id`),
  KEY `idx_pedidos_estado` (`estado`),
  KEY `idx_pedidos_periodo` (`data_inicio`, `data_fim`),
  KEY `idx_pedidos_aprovado_por` (`aprovado_por`),
  CONSTRAINT `fk_pedidos_ausencia_utilizador`
    FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT `fk_pedidos_ausencia_tipo`
    FOREIGN KEY (`tipo_ausencia_id`) REFERENCES `tipos_ausencia` (`id`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT `fk_pedidos_ausencia_aprovado_por`
    FOREIGN KEY (`aprovado_por`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT `chk_pedidos_ausencia_datas`
    CHECK (`data_fim` >= `data_inicio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Banco de horas
-- --------------------------------------------------------

CREATE TABLE `banco_horas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `utilizador_id` INT UNSIGNED NOT NULL,
  `data_movimento` DATE NOT NULL,
  `tipo_movimento` ENUM('credito','debito','ajuste','compensacao') NOT NULL,
  `minutos` INT NOT NULL,
  `origem` ENUM('ponto','ausencia','manual','importacao') NOT NULL DEFAULT 'manual',
  `registo_ponto_id` BIGINT UNSIGNED DEFAULT NULL,
  `pedido_ausencia_id` INT UNSIGNED DEFAULT NULL,
  `descricao` VARCHAR(255) DEFAULT NULL,
  `criado_por` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_banco_horas_utilizador_data` (`utilizador_id`, `data_movimento`),
  KEY `idx_banco_horas_tipo` (`tipo_movimento`),
  KEY `idx_banco_horas_registo` (`registo_ponto_id`),
  KEY `idx_banco_horas_pedido` (`pedido_ausencia_id`),
  KEY `idx_banco_horas_criado_por` (`criado_por`),
  CONSTRAINT `fk_banco_horas_utilizador`
    FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT `fk_banco_horas_registo_ponto`
    FOREIGN KEY (`registo_ponto_id`) REFERENCES `registos_ponto` (`id`)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT `fk_banco_horas_pedido_ausencia`
    FOREIGN KEY (`pedido_ausencia_id`) REFERENCES `pedidos_ausencia` (`id`)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT `fk_banco_horas_criado_por`
    FOREIGN KEY (`criado_por`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Logs do sistema
-- --------------------------------------------------------

CREATE TABLE `logs_sistema` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `utilizador_id` INT UNSIGNED DEFAULT NULL,
  `acao` VARCHAR(120) NOT NULL,
  `modulo` VARCHAR(80) DEFAULT NULL,
  `tabela` VARCHAR(80) DEFAULT NULL,
  `registo_id` BIGINT UNSIGNED DEFAULT NULL,
  `descricao` TEXT DEFAULT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `dados_anteriores` JSON DEFAULT NULL,
  `dados_novos` JSON DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_logs_utilizador` (`utilizador_id`),
  KEY `idx_logs_acao` (`acao`),
  KEY `idx_logs_modulo` (`modulo`),
  KEY `idx_logs_created_at` (`created_at`),
  CONSTRAINT `fk_logs_sistema_utilizador`
    FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Dados iniciais
-- --------------------------------------------------------

INSERT INTO `papeis` (`nome`, `slug`, `descricao`) VALUES
('Administrador', 'administrador', 'Acesso total ao sistema'),
('Recursos Humanos', 'recursos-humanos', 'Gestao de colaboradores, assiduidade e ausencias'),
('Chefia', 'chefia', 'Consulta e aprovacao da propria equipa'),
('Colaborador', 'colaborador', 'Acesso ao proprio perfil, ponto e pedidos');

INSERT INTO `tipos_ausencia`
(`nome`, `slug`, `descricao`, `remunerada`, `desconta_ferias`, `exige_justificativo`) VALUES
('Ferias', 'ferias', 'Periodo de ferias aprovado', 1, 1, 0),
('Falta Justificada', 'falta-justificada', 'Ausencia com justificacao aceite', 1, 0, 1),
('Falta Injustificada', 'falta-injustificada', 'Ausencia sem justificacao aceite', 0, 0, 0),
('Baixa Medica', 'baixa-medica', 'Ausencia por motivo de saude', 1, 0, 1),
('Licenca', 'licenca', 'Licenca autorizada', 1, 0, 1),
('Formacao', 'formacao', 'Ausencia por formacao', 1, 0, 0);

COMMIT;
