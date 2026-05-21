-- Simplifica a estrutura operacional: funcionarios pertencem a equipas.
-- O antigo setor fica apenas como coluna legada opcional para bases ja migradas.

SET @fk_equipas_setor := (
  SELECT CONSTRAINT_NAME
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'equipas'
    AND COLUMN_NAME = 'setor_id'
    AND REFERENCED_TABLE_NAME = 'setores'
  LIMIT 1
);

SET @drop_fk_equipas_setor := IF(
  @fk_equipas_setor IS NULL,
  'SELECT 1',
  CONCAT('ALTER TABLE `equipas` DROP FOREIGN KEY `', @fk_equipas_setor, '`')
);

PREPARE stmt FROM @drop_fk_equipas_setor;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE `equipas`
  MODIFY `setor_id` INT UNSIGNED DEFAULT NULL;

ALTER TABLE `equipas`
  ADD CONSTRAINT `fk_equipas_setor`
    FOREIGN KEY (`setor_id`) REFERENCES `setores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL;

UPDATE `funcionarios`
SET `setor_id` = NULL,
    `categoria_profissional` = NULL;
