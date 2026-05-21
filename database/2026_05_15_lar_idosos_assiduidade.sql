-- Adaptacao incremental para lar de idosos
-- Nao apaga dados existentes. Mantem compatibilidade com o schema atual.
-- PHP procedural + MySQLi + prepared statements

USE `gestor_assiduidade`;

SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;

DELIMITER $$

DROP PROCEDURE IF EXISTS add_column_if_missing $$
CREATE PROCEDURE add_column_if_missing(
    IN p_table_name VARCHAR(64),
    IN p_column_name VARCHAR(64),
    IN p_column_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table_name
          AND COLUMN_NAME = p_column_name
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table_name, '` ADD COLUMN `', p_column_name, '` ', p_column_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DROP PROCEDURE IF EXISTS add_index_if_missing $$
CREATE PROCEDURE add_index_if_missing(
    IN p_table_name VARCHAR(64),
    IN p_index_name VARCHAR(64),
    IN p_index_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table_name
          AND INDEX_NAME = p_index_name
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table_name, '` ADD ', p_index_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DROP PROCEDURE IF EXISTS add_fk_if_missing $$
CREATE PROCEDURE add_fk_if_missing(
    IN p_table_name VARCHAR(64),
    IN p_constraint_name VARCHAR(64),
    IN p_constraint_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table_name
          AND CONSTRAINT_NAME = p_constraint_name
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table_name, '` ADD CONSTRAINT `', p_constraint_name, '` ', p_constraint_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DELIMITER ;

-- --------------------------------------------------------
-- Setores/funcoes da instituicao
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `setores` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(140) NOT NULL,
  `codigo` VARCHAR(40) NOT NULL,
  `descricao` VARCHAR(255) DEFAULT NULL,
  `responsavel_id` INT UNSIGNED DEFAULT NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_setores_codigo` (`codigo`),
  KEY `idx_setores_responsavel` (`responsavel_id`),
  KEY `idx_setores_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL add_fk_if_missing(
  'setores',
  'fk_setores_responsavel',
  'FOREIGN KEY (`responsavel_id`) REFERENCES `utilizadores` (`id`) ON UPDATE CASCADE ON DELETE SET NULL'
);

INSERT INTO `setores` (`nome`, `codigo`, `descricao`) VALUES
('Acao Direta', 'ACAO_DIRETA', 'Prestacao direta de cuidados aos utentes'),
('Servicos Gerais', 'SERVICOS_GERAIS', 'Servicos gerais de apoio'),
('Refeitorio/Copa', 'REFEITORIO_COPA', 'Refeitorio, copa e apoio a refeicoes'),
('Cozinha', 'COZINHA', 'Preparacao e confeccao alimentar'),
('Lavandaria', 'LAVANDARIA', 'Tratamento de roupa e lavandaria'),
('Servicos Tecnicos e Administrativos', 'TECNICOS_ADMIN', 'Secretaria, direcao tecnica e servicos administrativos'),
('Motoristas', 'MOTORISTAS', 'Transporte de utentes, pessoal e servicos externos')
ON DUPLICATE KEY UPDATE
  `nome` = VALUES(`nome`),
  `descricao` = VALUES(`descricao`),
  `ativo` = 1;

-- --------------------------------------------------------
-- Equipas
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `equipas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setor_id` INT UNSIGNED NOT NULL,
  `nome` VARCHAR(140) NOT NULL,
  `codigo` VARCHAR(40) NOT NULL,
  `responsavel_id` INT UNSIGNED DEFAULT NULL,
  `descricao` VARCHAR(255) DEFAULT NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_equipas_codigo` (`codigo`),
  KEY `idx_equipas_setor` (`setor_id`),
  KEY `idx_equipas_responsavel` (`responsavel_id`),
  KEY `idx_equipas_ativo` (`ativo`),
  CONSTRAINT `fk_equipas_setor`
    FOREIGN KEY (`setor_id`) REFERENCES `setores` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL add_fk_if_missing(
  'equipas',
  'fk_equipas_responsavel',
  'FOREIGN KEY (`responsavel_id`) REFERENCES `utilizadores` (`id`) ON UPDATE CASCADE ON DELETE SET NULL'
);

INSERT INTO `equipas` (`setor_id`, `nome`, `codigo`, `descricao`)
SELECT s.id, CONCAT(s.nome, ' - Equipa Geral'), CONCAT(s.codigo, '_GERAL'), 'Equipa geral do setor'
FROM `setores` s
ON DUPLICATE KEY UPDATE
  `nome` = VALUES(`nome`),
  `descricao` = VALUES(`descricao`),
  `ativo` = 1;

-- Mantem o modulo Departamentos funcional e permite mapear departamentos para setores.
CALL add_column_if_missing('departamentos', 'setor_id', 'INT UNSIGNED DEFAULT NULL AFTER `id`');
CALL add_column_if_missing('departamentos', 'equipa_id', 'INT UNSIGNED DEFAULT NULL AFTER `setor_id`');
CALL add_index_if_missing('departamentos', 'idx_departamentos_setor', 'INDEX `idx_departamentos_setor` (`setor_id`)');
CALL add_index_if_missing('departamentos', 'idx_departamentos_equipa', 'INDEX `idx_departamentos_equipa` (`equipa_id`)');
CALL add_fk_if_missing(
  'departamentos',
  'fk_departamentos_setor',
  'FOREIGN KEY (`setor_id`) REFERENCES `setores` (`id`) ON UPDATE CASCADE ON DELETE SET NULL'
);
CALL add_fk_if_missing(
  'departamentos',
  'fk_departamentos_equipa',
  'FOREIGN KEY (`equipa_id`) REFERENCES `equipas` (`id`) ON UPDATE CASCADE ON DELETE SET NULL'
);

-- --------------------------------------------------------
-- Funcionarios
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `funcionarios` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `utilizador_id` INT UNSIGNED DEFAULT NULL,
  `setor_id` INT UNSIGNED DEFAULT NULL,
  `equipa_id` INT UNSIGNED DEFAULT NULL,
  `numero_mecanografico` VARCHAR(50) DEFAULT NULL,
  `nome` VARCHAR(160) NOT NULL,
  `email` VARCHAR(160) DEFAULT NULL,
  `telefone` VARCHAR(40) DEFAULT NULL,
  `funcao` VARCHAR(120) DEFAULT NULL,
  `categoria_profissional` VARCHAR(120) DEFAULT NULL,
  `data_admissao` DATE DEFAULT NULL,
  `data_cessacao` DATE DEFAULT NULL,
  `tipo_contrato` VARCHAR(80) DEFAULT NULL,
  `carga_horaria_semanal` DECIMAL(5,2) NOT NULL DEFAULT 40.00,
  `pin_ponto` VARCHAR(20) DEFAULT NULL,
  `codigo_cartao` VARCHAR(80) DEFAULT NULL,
  `codigo_biometrico` VARCHAR(80) DEFAULT NULL,
  `estado` ENUM('ativo','suspenso','inativo') NOT NULL DEFAULT 'ativo',
  `observacoes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_funcionarios_utilizador` (`utilizador_id`),
  UNIQUE KEY `uk_funcionarios_numero_mecanografico` (`numero_mecanografico`),
  UNIQUE KEY `uk_funcionarios_pin_ponto` (`pin_ponto`),
  UNIQUE KEY `uk_funcionarios_codigo_cartao` (`codigo_cartao`),
  UNIQUE KEY `uk_funcionarios_codigo_biometrico` (`codigo_biometrico`),
  KEY `idx_funcionarios_setor` (`setor_id`),
  KEY `idx_funcionarios_equipa` (`equipa_id`),
  KEY `idx_funcionarios_estado` (`estado`),
  CONSTRAINT `fk_funcionarios_utilizador`
    FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_funcionarios_setor`
    FOREIGN KEY (`setor_id`) REFERENCES `setores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_funcionarios_equipa`
    FOREIGN KEY (`equipa_id`) REFERENCES `equipas` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `funcionarios`
(`utilizador_id`, `numero_mecanografico`, `nome`, `email`, `telefone`, `funcao`, `data_admissao`,
 `tipo_contrato`, `pin_ponto`, `codigo_cartao`, `codigo_biometrico`, `estado`)
SELECT u.id, u.numero_mecanografico, u.nome, u.email, u.telefone, u.cargo, u.data_admissao,
       u.tipo_contrato, u.pin_ponto, u.codigo_cartao, u.codigo_biometrico, u.estado
FROM `utilizadores` u
WHERE NOT EXISTS (
    SELECT 1 FROM `funcionarios` f WHERE f.utilizador_id = u.id
);

CALL add_column_if_missing('utilizadores', 'setor_id', 'INT UNSIGNED DEFAULT NULL AFTER `departamento_id`');
CALL add_column_if_missing('utilizadores', 'equipa_id', 'INT UNSIGNED DEFAULT NULL AFTER `setor_id`');
CALL add_column_if_missing('utilizadores', 'funcionario_id', 'INT UNSIGNED DEFAULT NULL AFTER `equipa_id`');
CALL add_index_if_missing('utilizadores', 'idx_utilizadores_setor', 'INDEX `idx_utilizadores_setor` (`setor_id`)');
CALL add_index_if_missing('utilizadores', 'idx_utilizadores_equipa', 'INDEX `idx_utilizadores_equipa` (`equipa_id`)');
CALL add_index_if_missing('utilizadores', 'idx_utilizadores_funcionario', 'INDEX `idx_utilizadores_funcionario` (`funcionario_id`)');
CALL add_fk_if_missing(
  'utilizadores',
  'fk_utilizadores_setor',
  'FOREIGN KEY (`setor_id`) REFERENCES `setores` (`id`) ON UPDATE CASCADE ON DELETE SET NULL'
);
CALL add_fk_if_missing(
  'utilizadores',
  'fk_utilizadores_equipa',
  'FOREIGN KEY (`equipa_id`) REFERENCES `equipas` (`id`) ON UPDATE CASCADE ON DELETE SET NULL'
);
CALL add_fk_if_missing(
  'utilizadores',
  'fk_utilizadores_funcionario',
  'FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON UPDATE CASCADE ON DELETE SET NULL'
);

UPDATE `utilizadores` u
INNER JOIN `funcionarios` f ON f.utilizador_id = u.id
SET u.funcionario_id = f.id
WHERE u.funcionario_id IS NULL;

-- Horarios/turnos passam a estar ligados a funcionarios. Mantem utilizador_id
-- apenas para compatibilidade historica com dados antigos.
ALTER TABLE `horarios_turno`
  MODIFY `utilizador_id` INT UNSIGNED DEFAULT NULL;
CALL add_column_if_missing('horarios_turno', 'funcionario_id', 'INT UNSIGNED DEFAULT NULL AFTER `utilizador_id`');
CALL add_index_if_missing('horarios_turno', 'idx_horarios_funcionario', 'INDEX `idx_horarios_funcionario` (`funcionario_id`)');
CALL add_fk_if_missing(
  'horarios_turno',
  'fk_horarios_turno_funcionario',
  'FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON UPDATE CASCADE ON DELETE CASCADE'
);

UPDATE `horarios_turno` ht
INNER JOIN `funcionarios` f ON f.utilizador_id = ht.utilizador_id
SET ht.funcionario_id = f.id
WHERE ht.funcionario_id IS NULL;

-- --------------------------------------------------------
-- Turnos com um ou mais periodos
-- --------------------------------------------------------

CALL add_column_if_missing('turnos', 'descricao', 'VARCHAR(255) DEFAULT NULL AFTER `codigo`');
CALL add_column_if_missing('turnos', 'total_periodos', 'TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER `horas_previstas`');
CALL add_column_if_missing('turnos', 'permite_multiplos_periodos', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `total_periodos`');
CALL add_column_if_missing('turnos', 'tolerancia_antes_min', 'SMALLINT UNSIGNED NOT NULL DEFAULT 15 AFTER `tolerancia_saida_min`');
CALL add_column_if_missing('turnos', 'tolerancia_depois_min', 'SMALLINT UNSIGNED NOT NULL DEFAULT 15 AFTER `tolerancia_antes_min`');

UPDATE `turnos`
SET `tolerancia_entrada_min` = 15,
    `tolerancia_saida_min` = 15,
    `tolerancia_antes_min` = 15,
    `tolerancia_depois_min` = 15
WHERE (`tolerancia_entrada_min` = 0 OR `tolerancia_saida_min` = 0);

CREATE TABLE IF NOT EXISTS `turno_periodos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `turno_id` INT UNSIGNED NOT NULL,
  `sequencia` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `hora_inicio` TIME NOT NULL,
  `hora_fim` TIME NOT NULL,
  `cruza_dia` TINYINT(1) NOT NULL DEFAULT 0,
  `tolerancia_antes_min` SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  `tolerancia_depois_min` SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  `minutos_previstos` SMALLINT UNSIGNED NOT NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_turno_periodos_seq` (`turno_id`, `sequencia`),
  KEY `idx_turno_periodos_turno` (`turno_id`),
  CONSTRAINT `fk_turno_periodos_turno`
    FOREIGN KEY (`turno_id`) REFERENCES `turnos` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `chk_turno_periodos_seq`
    CHECK (`sequencia` BETWEEN 1 AND 6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migra turnos atuais de periodo unico para a nova tabela de periodos.
INSERT INTO `turno_periodos`
(`turno_id`, `sequencia`, `hora_inicio`, `hora_fim`, `cruza_dia`, `tolerancia_antes_min`, `tolerancia_depois_min`, `minutos_previstos`)
SELECT t.id, 1, t.hora_entrada, t.hora_saida, IF(t.hora_saida <= t.hora_entrada, 1, 0),
       15, 15,
       TIMESTAMPDIFF(
         MINUTE,
         TIMESTAMP('2000-01-01', t.hora_entrada),
         TIMESTAMP(IF(t.hora_saida <= t.hora_entrada, '2000-01-02', '2000-01-01'), t.hora_saida)
       )
FROM `turnos` t
WHERE NOT EXISTS (
  SELECT 1 FROM `turno_periodos` tp WHERE tp.turno_id = t.id
);

INSERT INTO `turnos`
(`nome`, `codigo`, `descricao`, `hora_entrada`, `hora_saida`, `tolerancia_entrada_min`, `tolerancia_saida_min`,
 `tolerancia_antes_min`, `tolerancia_depois_min`, `horas_previstas`, `turno_noturno`, `total_periodos`, `permite_multiplos_periodos`, `ativo`)
VALUES
('00:00-08:00', 'T_0000_0800', 'Turno noturno/madrugada', '00:00:00', '08:00:00', 15, 15, 15, 15, 8.00, 1, 1, 0, 1),
('08:00-16:00', 'T_0800_1600', 'Turno da manha/tarde', '08:00:00', '16:00:00', 15, 15, 15, 15, 8.00, 0, 1, 0, 1),
('16:00-00:00', 'T_1600_0000', 'Turno da tarde/noite', '16:00:00', '00:00:00', 15, 15, 15, 15, 8.00, 1, 1, 0, 1),
('08:30-16:30', 'T_0830_1630', 'Turno administrativo', '08:30:00', '16:30:00', 15, 15, 15, 15, 8.00, 0, 1, 0, 1),
('08:00-13:00 e 17:00-20:00', 'T_0800_1300_1700_2000', 'Turno repartido', '08:00:00', '20:00:00', 15, 15, 15, 15, 8.00, 0, 2, 1, 1),
('08:00-14:00', 'T_0800_1400', 'Turno parcial de 6 horas', '08:00:00', '14:00:00', 15, 15, 15, 15, 6.00, 0, 1, 0, 1),
('08:00-12:00 e 18:00-20:00', 'T_0800_1200_1800_2000', 'Turno repartido parcial', '08:00:00', '20:00:00', 15, 15, 15, 15, 6.00, 0, 2, 1, 1),
('12:00-20:00', 'T_1200_2000', 'Turno tarde', '12:00:00', '20:00:00', 15, 15, 15, 15, 8.00, 0, 1, 0, 1),
('09:00-12:30 e 14:00-17:30', 'T_0900_1230_1400_1730', 'Turno administrativo repartido', '09:00:00', '17:30:00', 15, 15, 15, 15, 7.00, 0, 2, 1, 1)
ON DUPLICATE KEY UPDATE
  `nome` = VALUES(`nome`),
  `descricao` = VALUES(`descricao`),
  `hora_entrada` = VALUES(`hora_entrada`),
  `hora_saida` = VALUES(`hora_saida`),
  `tolerancia_entrada_min` = 15,
  `tolerancia_saida_min` = 15,
  `tolerancia_antes_min` = 15,
  `tolerancia_depois_min` = 15,
  `horas_previstas` = VALUES(`horas_previstas`),
  `turno_noturno` = VALUES(`turno_noturno`),
  `total_periodos` = VALUES(`total_periodos`),
  `permite_multiplos_periodos` = VALUES(`permite_multiplos_periodos`),
  `ativo` = 1;

INSERT INTO `turno_periodos` (`turno_id`, `sequencia`, `hora_inicio`, `hora_fim`, `cruza_dia`, `minutos_previstos`)
SELECT t.id, p.sequencia, p.hora_inicio, p.hora_fim, p.cruza_dia, p.minutos_previstos
FROM `turnos` t
INNER JOIN (
    SELECT 'T_0000_0800' codigo, 1 sequencia, '00:00:00' hora_inicio, '08:00:00' hora_fim, 0 cruza_dia, 480 minutos_previstos
    UNION ALL SELECT 'T_0800_1600', 1, '08:00:00', '16:00:00', 0, 480
    UNION ALL SELECT 'T_1600_0000', 1, '16:00:00', '00:00:00', 1, 480
    UNION ALL SELECT 'T_0830_1630', 1, '08:30:00', '16:30:00', 0, 480
    UNION ALL SELECT 'T_0800_1300_1700_2000', 1, '08:00:00', '13:00:00', 0, 300
    UNION ALL SELECT 'T_0800_1300_1700_2000', 2, '17:00:00', '20:00:00', 0, 180
    UNION ALL SELECT 'T_0800_1400', 1, '08:00:00', '14:00:00', 0, 360
    UNION ALL SELECT 'T_0800_1200_1800_2000', 1, '08:00:00', '12:00:00', 0, 240
    UNION ALL SELECT 'T_0800_1200_1800_2000', 2, '18:00:00', '20:00:00', 0, 120
    UNION ALL SELECT 'T_1200_2000', 1, '12:00:00', '20:00:00', 0, 480
    UNION ALL SELECT 'T_0900_1230_1400_1730', 1, '09:00:00', '12:30:00', 0, 210
    UNION ALL SELECT 'T_0900_1230_1400_1730', 2, '14:00:00', '17:30:00', 0, 210
) p ON p.codigo = t.codigo
ON DUPLICATE KEY UPDATE
  `hora_inicio` = VALUES(`hora_inicio`),
  `hora_fim` = VALUES(`hora_fim`),
  `cruza_dia` = VALUES(`cruza_dia`),
  `tolerancia_antes_min` = 15,
  `tolerancia_depois_min` = 15,
  `minutos_previstos` = VALUES(`minutos_previstos`),
  `ativo` = 1;

-- --------------------------------------------------------
-- Escala mensal
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `escala_mensal` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ano` SMALLINT UNSIGNED NOT NULL,
  `mes` TINYINT UNSIGNED NOT NULL,
  `setor_id` INT UNSIGNED DEFAULT NULL,
  `equipa_id` INT UNSIGNED DEFAULT NULL,
  `nome` VARCHAR(160) DEFAULT NULL,
  `estado` ENUM('rascunho','publicada','fechada','cancelada') NOT NULL DEFAULT 'rascunho',
  `publicada_at` DATETIME DEFAULT NULL,
  `publicada_por` INT UNSIGNED DEFAULT NULL,
  `fechada_at` DATETIME DEFAULT NULL,
  `fechada_por` INT UNSIGNED DEFAULT NULL,
  `observacoes` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_escala_mensal_contexto` (`ano`, `mes`, `setor_id`, `equipa_id`),
  KEY `idx_escala_mensal_setor` (`setor_id`),
  KEY `idx_escala_mensal_equipa` (`equipa_id`),
  KEY `idx_escala_mensal_estado` (`estado`),
  CONSTRAINT `fk_escala_mensal_setor`
    FOREIGN KEY (`setor_id`) REFERENCES `setores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_escala_mensal_equipa`
    FOREIGN KEY (`equipa_id`) REFERENCES `equipas` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_escala_mensal_publicada_por`
    FOREIGN KEY (`publicada_por`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_escala_mensal_fechada_por`
    FOREIGN KEY (`fechada_por`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_escala_mensal_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_escala_mensal_mes`
    CHECK (`mes` BETWEEN 1 AND 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `escala_mensal_dias` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `escala_mensal_id` BIGINT UNSIGNED NOT NULL,
  `funcionario_id` INT UNSIGNED DEFAULT NULL,
  `utilizador_id` INT UNSIGNED DEFAULT NULL,
  `setor_id` INT UNSIGNED DEFAULT NULL,
  `equipa_id` INT UNSIGNED DEFAULT NULL,
  `data_escala` DATE NOT NULL,
  `turno_id` INT UNSIGNED DEFAULT NULL,
  `tipo_dia` ENUM('trabalho','folga','feriado','ferias','ausencia','descanso','formacao') NOT NULL DEFAULT 'trabalho',
  `minutos_previstos` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `observacoes` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_escala_dia_funcionario` (`funcionario_id`, `data_escala`),
  KEY `idx_escala_dias_escala` (`escala_mensal_id`),
  KEY `idx_escala_dias_utilizador` (`utilizador_id`, `data_escala`),
  KEY `idx_escala_dias_setor` (`setor_id`, `data_escala`),
  KEY `idx_escala_dias_equipa` (`equipa_id`, `data_escala`),
  KEY `idx_escala_dias_turno` (`turno_id`),
  CONSTRAINT `fk_escala_dias_escala`
    FOREIGN KEY (`escala_mensal_id`) REFERENCES `escala_mensal` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_escala_dias_funcionario`
    FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_escala_dias_utilizador`
    FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_escala_dias_setor`
    FOREIGN KEY (`setor_id`) REFERENCES `setores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_escala_dias_equipa`
    FOREIGN KEY (`equipa_id`) REFERENCES `equipas` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_escala_dias_turno`
    FOREIGN KEY (`turno_id`) REFERENCES `turnos` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `escala_funcionarios` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `funcionario_id` INT UNSIGNED NOT NULL,
  `utilizador_id` INT UNSIGNED DEFAULT NULL,
  `setor_id` INT UNSIGNED DEFAULT NULL,
  `equipa_id` INT UNSIGNED DEFAULT NULL,
  `ano` SMALLINT UNSIGNED NOT NULL,
  `mes` TINYINT UNSIGNED NOT NULL,
  `data_escala` DATE NOT NULL,
  `dia` TINYINT UNSIGNED NOT NULL,
  `tipo_dia` ENUM('turno','folga','ferias','falta','baixa','substituicao','licenca_amamentacao') NOT NULL DEFAULT 'turno',
  `turno_id` INT UNSIGNED DEFAULT NULL,
  `substitui_funcionario_id` INT UNSIGNED DEFAULT NULL,
  `folga_trabalhada` TINYINT(1) NOT NULL DEFAULT 0,
  `observacoes` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_escala_funcionario_dia` (`funcionario_id`, `data_escala`),
  KEY `idx_escala_funcionarios_periodo` (`ano`, `mes`),
  KEY `idx_escala_funcionarios_setor` (`setor_id`, `data_escala`),
  KEY `idx_escala_funcionarios_equipa` (`equipa_id`, `data_escala`),
  KEY `idx_escala_funcionarios_turno` (`turno_id`),
  KEY `idx_escala_funcionarios_substitui` (`substitui_funcionario_id`),
  CONSTRAINT `fk_escala_funcionarios_funcionario`
    FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_escala_funcionarios_utilizador`
    FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_escala_funcionarios_setor`
    FOREIGN KEY (`setor_id`) REFERENCES `setores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_escala_funcionarios_equipa`
    FOREIGN KEY (`equipa_id`) REFERENCES `equipas` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_escala_funcionarios_turno`
    FOREIGN KEY (`turno_id`) REFERENCES `turnos` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_escala_funcionarios_substitui`
    FOREIGN KEY (`substitui_funcionario_id`) REFERENCES `funcionarios` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_escala_funcionarios_mes`
    CHECK (`mes` BETWEEN 1 AND 12),
  CONSTRAINT `chk_escala_funcionarios_dia`
    CHECK (`dia` BETWEEN 1 AND 31)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Ferias/ausencias operacionais
-- Mantem pedidos_ausencia como pedido/aprovacao e cria uma tabela propria
-- para calendario, impacto na escala e relatorios.
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `ferias_ausencias` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pedido_ausencia_id` INT UNSIGNED DEFAULT NULL,
  `funcionario_id` INT UNSIGNED DEFAULT NULL,
  `utilizador_id` INT UNSIGNED DEFAULT NULL,
  `tipo_ausencia_id` INT UNSIGNED NOT NULL,
  `data_inicio` DATE NOT NULL,
  `data_fim` DATE NOT NULL,
  `hora_inicio` TIME DEFAULT NULL,
  `hora_fim` TIME DEFAULT NULL,
  `dia_completo` TINYINT(1) NOT NULL DEFAULT 1,
  `minutos_justificados` INT UNSIGNED DEFAULT NULL,
  `afeta_assiduidade` TINYINT(1) NOT NULL DEFAULT 1,
  `desconta_banco_horas` TINYINT(1) NOT NULL DEFAULT 0,
  `estado` ENUM('pendente','aprovado','rejeitado','cancelado') NOT NULL DEFAULT 'pendente',
  `motivo` TEXT DEFAULT NULL,
  `ficheiro_justificativo` VARCHAR(255) DEFAULT NULL,
  `aprovado_por` INT UNSIGNED DEFAULT NULL,
  `aprovado_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ferias_funcionario_periodo` (`funcionario_id`, `data_inicio`, `data_fim`),
  KEY `idx_ferias_utilizador_periodo` (`utilizador_id`, `data_inicio`, `data_fim`),
  KEY `idx_ferias_tipo` (`tipo_ausencia_id`),
  KEY `idx_ferias_estado` (`estado`),
  KEY `idx_ferias_pedido` (`pedido_ausencia_id`),
  CONSTRAINT `fk_ferias_pedido`
    FOREIGN KEY (`pedido_ausencia_id`) REFERENCES `pedidos_ausencia` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_ferias_funcionario`
    FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_ferias_utilizador`
    FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_ferias_tipo`
    FOREIGN KEY (`tipo_ausencia_id`) REFERENCES `tipos_ausencia` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_ferias_aprovado_por`
    FOREIGN KEY (`aprovado_por`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_ferias_datas`
    CHECK (`data_fim` >= `data_inicio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `ferias_ausencias`
(`pedido_ausencia_id`, `funcionario_id`, `utilizador_id`, `tipo_ausencia_id`, `data_inicio`, `data_fim`,
 `hora_inicio`, `hora_fim`, `dia_completo`, `minutos_justificados`, `estado`, `motivo`,
 `ficheiro_justificativo`, `aprovado_por`, `aprovado_at`, `created_at`, `updated_at`)
SELECT p.id, f.id, p.utilizador_id, p.tipo_ausencia_id, p.data_inicio, p.data_fim,
       p.hora_inicio, p.hora_fim,
       IF(p.hora_inicio IS NULL AND p.hora_fim IS NULL, 1, 0),
       CASE WHEN p.total_horas IS NULL THEN NULL ELSE ROUND(p.total_horas * 60) END,
       p.estado, p.motivo, p.ficheiro_justificativo, p.aprovado_por, p.aprovado_at,
       p.created_at, p.updated_at
FROM `pedidos_ausencia` p
LEFT JOIN `funcionarios` f ON f.utilizador_id = p.utilizador_id
WHERE NOT EXISTS (
    SELECT 1 FROM `ferias_ausencias` fa WHERE fa.pedido_ausencia_id = p.id
);

-- --------------------------------------------------------
-- Registos de ponto enriquecidos para escala e periodos
-- --------------------------------------------------------

ALTER TABLE `registos_ponto`
  MODIFY `utilizador_id` INT UNSIGNED DEFAULT NULL;
CALL add_column_if_missing('registos_ponto', 'funcionario_id', 'INT UNSIGNED DEFAULT NULL AFTER `utilizador_id`');
CALL add_column_if_missing('registos_ponto', 'escala_mensal_dia_id', 'BIGINT UNSIGNED DEFAULT NULL AFTER `dispositivo_id`');
CALL add_column_if_missing('registos_ponto', 'turno_periodo_id', 'INT UNSIGNED DEFAULT NULL AFTER `escala_mensal_dia_id`');
CALL add_column_if_missing('registos_ponto', 'data_referencia', 'DATE DEFAULT NULL AFTER `data_hora`');
CALL add_column_if_missing('registos_ponto', 'data_hora_prevista', 'DATETIME DEFAULT NULL AFTER `data_referencia`');
CALL add_column_if_missing('registos_ponto', 'dentro_tolerancia', 'TINYINT(1) DEFAULT NULL AFTER `data_hora_prevista`');
CALL add_column_if_missing('registos_ponto', 'minutos_desvio', 'INT DEFAULT NULL AFTER `dentro_tolerancia`');
CALL add_column_if_missing('registos_ponto', 'motivo_correcao', 'VARCHAR(255) DEFAULT NULL AFTER `observacoes`');
CALL add_index_if_missing('registos_ponto', 'idx_registos_funcionario_data', 'INDEX `idx_registos_funcionario_data` (`funcionario_id`, `data_hora`)');
CALL add_index_if_missing('registos_ponto', 'idx_registos_data_referencia', 'INDEX `idx_registos_data_referencia` (`data_referencia`)');
CALL add_index_if_missing('registos_ponto', 'idx_registos_escala_dia', 'INDEX `idx_registos_escala_dia` (`escala_mensal_dia_id`)');
CALL add_index_if_missing('registos_ponto', 'idx_registos_turno_periodo', 'INDEX `idx_registos_turno_periodo` (`turno_periodo_id`)');
CALL add_fk_if_missing(
  'registos_ponto',
  'fk_registos_ponto_funcionario',
  'FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON UPDATE CASCADE ON DELETE SET NULL'
);
CALL add_fk_if_missing(
  'registos_ponto',
  'fk_registos_ponto_escala_dia',
  'FOREIGN KEY (`escala_mensal_dia_id`) REFERENCES `escala_mensal_dias` (`id`) ON UPDATE CASCADE ON DELETE SET NULL'
);
CALL add_fk_if_missing(
  'registos_ponto',
  'fk_registos_ponto_turno_periodo',
  'FOREIGN KEY (`turno_periodo_id`) REFERENCES `turno_periodos` (`id`) ON UPDATE CASCADE ON DELETE SET NULL'
);

UPDATE `registos_ponto` rp
INNER JOIN `funcionarios` f ON f.utilizador_id = rp.utilizador_id
SET rp.funcionario_id = f.id,
    rp.data_referencia = DATE(rp.data_hora)
WHERE rp.funcionario_id IS NULL OR rp.data_referencia IS NULL;

-- --------------------------------------------------------
-- Resumo diario de assiduidade
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `resumo_diario_assiduidade` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `funcionario_id` INT UNSIGNED DEFAULT NULL,
  `utilizador_id` INT UNSIGNED DEFAULT NULL,
  `setor_id` INT UNSIGNED DEFAULT NULL,
  `equipa_id` INT UNSIGNED DEFAULT NULL,
  `data` DATE NOT NULL,
  `escala_mensal_dia_id` BIGINT UNSIGNED DEFAULT NULL,
  `turno_id` INT UNSIGNED DEFAULT NULL,
  `minutos_previstos` INT NOT NULL DEFAULT 0,
  `minutos_trabalhados` INT NOT NULL DEFAULT 0,
  `horas_previstas` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  `horas_realizadas` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  `minutos_ausencia_justificada` INT NOT NULL DEFAULT 0,
  `minutos_atraso` INT NOT NULL DEFAULT 0,
  `minutos_saida_antecipada` INT NOT NULL DEFAULT 0,
  `minutos_extra` INT NOT NULL DEFAULT 0,
  `minutos_saldo` INT NOT NULL DEFAULT 0,
  `dentro_tolerancia` TINYINT(1) NOT NULL DEFAULT 1,
  `estado` ENUM('previsto','presente','ausente','ferias','folga','feriado','incompleto','corrigido','sem_escala') NOT NULL DEFAULT 'previsto',
  `entrada_prevista` DATETIME DEFAULT NULL,
  `saida_prevista` DATETIME DEFAULT NULL,
  `entrada_real` DATETIME DEFAULT NULL,
  `saida_real` DATETIME DEFAULT NULL,
  `falta` TINYINT(1) NOT NULL DEFAULT 0,
  `folga_trabalhada` TINYINT(1) NOT NULL DEFAULT 0,
  `substituicao` TINYINT(1) NOT NULL DEFAULT 0,
  `substitui_funcionario_id` INT UNSIGNED DEFAULT NULL,
  `licenca_amamentacao` TINYINT(1) NOT NULL DEFAULT 0,
  `primeira_entrada` DATETIME DEFAULT NULL,
  `ultima_saida` DATETIME DEFAULT NULL,
  `observacoes` VARCHAR(255) DEFAULT NULL,
  `calculado_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_resumo_diario_funcionario` (`funcionario_id`, `data`),
  KEY `idx_resumo_diario_utilizador` (`utilizador_id`, `data`),
  KEY `idx_resumo_diario_setor` (`setor_id`, `data`),
  KEY `idx_resumo_diario_equipa` (`equipa_id`, `data`),
  KEY `idx_resumo_diario_estado` (`estado`),
  KEY `idx_resumo_substitui_funcionario` (`substitui_funcionario_id`),
  CONSTRAINT `fk_resumo_funcionario`
    FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_resumo_utilizador`
    FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_resumo_setor`
    FOREIGN KEY (`setor_id`) REFERENCES `setores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_resumo_equipa`
    FOREIGN KEY (`equipa_id`) REFERENCES `equipas` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_resumo_escala_dia`
    FOREIGN KEY (`escala_mensal_dia_id`) REFERENCES `escala_mensal_dias` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_resumo_turno`
    FOREIGN KEY (`turno_id`) REFERENCES `turnos` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_resumo_substitui_funcionario`
    FOREIGN KEY (`substitui_funcionario_id`) REFERENCES `funcionarios` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL add_column_if_missing('resumo_diario_assiduidade', 'entrada_prevista', 'DATETIME DEFAULT NULL AFTER `estado`');
CALL add_column_if_missing('resumo_diario_assiduidade', 'horas_previstas', 'DECIMAL(6,2) NOT NULL DEFAULT 0.00 AFTER `minutos_trabalhados`');
CALL add_column_if_missing('resumo_diario_assiduidade', 'horas_realizadas', 'DECIMAL(6,2) NOT NULL DEFAULT 0.00 AFTER `horas_previstas`');
CALL add_column_if_missing('resumo_diario_assiduidade', 'saida_prevista', 'DATETIME DEFAULT NULL AFTER `entrada_prevista`');
CALL add_column_if_missing('resumo_diario_assiduidade', 'entrada_real', 'DATETIME DEFAULT NULL AFTER `saida_prevista`');
CALL add_column_if_missing('resumo_diario_assiduidade', 'saida_real', 'DATETIME DEFAULT NULL AFTER `entrada_real`');
CALL add_column_if_missing('resumo_diario_assiduidade', 'falta', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `saida_real`');
CALL add_column_if_missing('resumo_diario_assiduidade', 'folga_trabalhada', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `falta`');
CALL add_column_if_missing('resumo_diario_assiduidade', 'substituicao', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `folga_trabalhada`');
CALL add_column_if_missing('resumo_diario_assiduidade', 'substitui_funcionario_id', 'INT UNSIGNED DEFAULT NULL AFTER `substituicao`');
CALL add_column_if_missing('resumo_diario_assiduidade', 'licenca_amamentacao', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `substitui_funcionario_id`');
CALL add_index_if_missing('resumo_diario_assiduidade', 'idx_resumo_substitui_funcionario', 'INDEX `idx_resumo_substitui_funcionario` (`substitui_funcionario_id`)');
CALL add_fk_if_missing(
  'resumo_diario_assiduidade',
  'fk_resumo_substitui_funcionario',
  'FOREIGN KEY (`substitui_funcionario_id`) REFERENCES `funcionarios` (`id`) ON UPDATE CASCADE ON DELETE SET NULL'
);

-- --------------------------------------------------------
-- Relatorios mensais por funcionario, equipa e setor
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `relatorios_mensais` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ano` SMALLINT UNSIGNED NOT NULL,
  `mes` TINYINT UNSIGNED NOT NULL,
  `tipo` ENUM('funcionario','equipa','setor') NOT NULL,
  `funcionario_id` INT UNSIGNED DEFAULT NULL,
  `equipa_id` INT UNSIGNED DEFAULT NULL,
  `setor_id` INT UNSIGNED DEFAULT NULL,
  `estado` ENUM('rascunho','calculado','validado','fechado') NOT NULL DEFAULT 'rascunho',
  `dias_previstos` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `dias_trabalhados` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `dias_ferias` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `dias_ausencia` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `minutos_previstos` INT NOT NULL DEFAULT 0,
  `minutos_trabalhados` INT NOT NULL DEFAULT 0,
  `minutos_ausencia_justificada` INT NOT NULL DEFAULT 0,
  `minutos_atraso` INT NOT NULL DEFAULT 0,
  `minutos_saida_antecipada` INT NOT NULL DEFAULT 0,
  `minutos_extra` INT NOT NULL DEFAULT 0,
  `minutos_saldo` INT NOT NULL DEFAULT 0,
  `gerado_por` INT UNSIGNED DEFAULT NULL,
  `gerado_at` DATETIME DEFAULT NULL,
  `validado_por` INT UNSIGNED DEFAULT NULL,
  `validado_at` DATETIME DEFAULT NULL,
  `observacoes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_relatorio_mensal_contexto` (`ano`, `mes`, `tipo`, `funcionario_id`, `equipa_id`, `setor_id`),
  KEY `idx_relatorios_funcionario` (`funcionario_id`),
  KEY `idx_relatorios_equipa` (`equipa_id`),
  KEY `idx_relatorios_setor` (`setor_id`),
  KEY `idx_relatorios_estado` (`estado`),
  CONSTRAINT `fk_relatorios_funcionario`
    FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_relatorios_equipa`
    FOREIGN KEY (`equipa_id`) REFERENCES `equipas` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_relatorios_setor`
    FOREIGN KEY (`setor_id`) REFERENCES `setores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_relatorios_gerado_por`
    FOREIGN KEY (`gerado_por`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_relatorios_validado_por`
    FOREIGN KEY (`validado_por`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_relatorios_mes`
    CHECK (`mes` BETWEEN 1 AND 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `relatorio_mensal_linhas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `relatorio_mensal_id` BIGINT UNSIGNED NOT NULL,
  `resumo_diario_id` BIGINT UNSIGNED DEFAULT NULL,
  `funcionario_id` INT UNSIGNED DEFAULT NULL,
  `data` DATE NOT NULL,
  `estado` VARCHAR(40) NOT NULL,
  `minutos_previstos` INT NOT NULL DEFAULT 0,
  `minutos_trabalhados` INT NOT NULL DEFAULT 0,
  `minutos_saldo` INT NOT NULL DEFAULT 0,
  `observacoes` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_relatorio_linha_dia` (`relatorio_mensal_id`, `funcionario_id`, `data`),
  KEY `idx_relatorio_linhas_resumo` (`resumo_diario_id`),
  KEY `idx_relatorio_linhas_funcionario` (`funcionario_id`, `data`),
  CONSTRAINT `fk_relatorio_linhas_relatorio`
    FOREIGN KEY (`relatorio_mensal_id`) REFERENCES `relatorios_mensais` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_relatorio_linhas_resumo`
    FOREIGN KEY (`resumo_diario_id`) REFERENCES `resumo_diario_assiduidade` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_relatorio_linhas_funcionario`
    FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Ajustes ao banco de horas para ligar ao resumo diario
-- --------------------------------------------------------

ALTER TABLE `banco_horas`
  MODIFY `utilizador_id` INT UNSIGNED DEFAULT NULL;
CALL add_column_if_missing('banco_horas', 'funcionario_id', 'INT UNSIGNED DEFAULT NULL AFTER `utilizador_id`');
CALL add_column_if_missing('banco_horas', 'resumo_diario_id', 'BIGINT UNSIGNED DEFAULT NULL AFTER `pedido_ausencia_id`');
CALL add_index_if_missing('banco_horas', 'idx_banco_horas_funcionario_data', 'INDEX `idx_banco_horas_funcionario_data` (`funcionario_id`, `data_movimento`)');
CALL add_index_if_missing('banco_horas', 'idx_banco_horas_resumo', 'INDEX `idx_banco_horas_resumo` (`resumo_diario_id`)');
CALL add_fk_if_missing(
  'banco_horas',
  'fk_banco_horas_funcionario',
  'FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON UPDATE CASCADE ON DELETE SET NULL'
);
CALL add_fk_if_missing(
  'banco_horas',
  'fk_banco_horas_resumo',
  'FOREIGN KEY (`resumo_diario_id`) REFERENCES `resumo_diario_assiduidade` (`id`) ON UPDATE CASCADE ON DELETE SET NULL'
);

UPDATE `banco_horas` bh
INNER JOIN `funcionarios` f ON f.utilizador_id = bh.utilizador_id
SET bh.funcionario_id = f.id
WHERE bh.funcionario_id IS NULL;

-- --------------------------------------------------------
-- Views de consulta para PHP/MySQLi
-- --------------------------------------------------------

CREATE OR REPLACE VIEW `vw_funcionarios_contexto` AS
SELECT
  f.id AS funcionario_id,
  f.utilizador_id,
  COALESCE(f.numero_mecanografico, u.numero_mecanografico) AS numero_mecanografico,
  COALESCE(f.nome, u.nome) AS nome,
  COALESCE(f.email, u.email) AS email,
  f.funcao,
  f.estado,
  f.setor_id,
  s.nome AS setor_nome,
  f.equipa_id,
  e.nome AS equipa_nome
FROM `funcionarios` f
LEFT JOIN `utilizadores` u ON u.id = f.utilizador_id
LEFT JOIN `setores` s ON s.id = f.setor_id
LEFT JOIN `equipas` e ON e.id = f.equipa_id;

CREATE OR REPLACE VIEW `vw_turnos_periodos` AS
SELECT
  t.id AS turno_id,
  t.nome AS turno_nome,
  t.codigo AS turno_codigo,
  t.horas_previstas,
  t.total_periodos,
  t.permite_multiplos_periodos,
  tp.id AS periodo_id,
  tp.sequencia,
  tp.hora_inicio,
  tp.hora_fim,
  tp.cruza_dia,
  tp.tolerancia_antes_min,
  tp.tolerancia_depois_min,
  tp.minutos_previstos
FROM `turnos` t
INNER JOIN `turno_periodos` tp ON tp.turno_id = t.id
WHERE t.ativo = 1
  AND tp.ativo = 1;

CREATE OR REPLACE VIEW `vw_relatorio_mensal_assiduidade` AS
SELECT
  rda.data,
  YEAR(rda.data) AS ano,
  MONTH(rda.data) AS mes,
  rda.funcionario_id,
  f.nome AS funcionario_nome,
  rda.setor_id,
  s.nome AS setor_nome,
  rda.equipa_id,
  e.nome AS equipa_nome,
  rda.estado,
  rda.minutos_previstos,
  rda.minutos_trabalhados,
  rda.minutos_ausencia_justificada,
  rda.minutos_atraso,
  rda.minutos_saida_antecipada,
  rda.minutos_extra,
  rda.minutos_saldo
FROM `resumo_diario_assiduidade` rda
LEFT JOIN `funcionarios` f ON f.id = rda.funcionario_id
LEFT JOIN `setores` s ON s.id = rda.setor_id
LEFT JOIN `equipas` e ON e.id = rda.equipa_id;

DROP PROCEDURE IF EXISTS add_column_if_missing;
DROP PROCEDURE IF EXISTS add_index_if_missing;
DROP PROCEDURE IF EXISTS add_fk_if_missing;

SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS;
