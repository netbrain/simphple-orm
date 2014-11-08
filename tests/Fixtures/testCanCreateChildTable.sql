CREATE TABLE IF NOT EXISTS child (
  `id`                 INT AUTO_INCREMENT,
  `parent_child_fk`    INT,
  `parent_children_fk` INT,
  `_version`           INT NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE (`parent_child_fk`),
  FOREIGN KEY (`parent_child_fk`) REFERENCES parent (`id`)
    ON DELETE CASCADE,
  FOREIGN KEY (`parent_children_fk`) REFERENCES parent (`id`)
    ON DELETE CASCADE
)
  ENGINE=InnoDB