CREATE TABLE IF NOT EXISTS reference (
  `id`                   INT AUTO_INCREMENT,
  `parent_reference_fk`  INT,
  `parent_references_fk` INT,
  `_version`             INT NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE (`parent_reference_fk`),
  FOREIGN KEY (`parent_reference_fk`) REFERENCES reference (`id`)
    ON DELETE CASCADE,
  FOREIGN KEY (`parent_references_fk`) REFERENCES reference (`id`)
    ON DELETE CASCADE
)
  ENGINE=InnoDB