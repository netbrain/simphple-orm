CREATE TABLE IF NOT EXISTS NotAnnotatedTest (
  `id`       INT AUTO_INCREMENT,
  `boolean`  BOOL,
  `float`    FLOAT,
  `int`      INT,
  `string`   VARCHAR(255),
  `_version` INT NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
)
  ENGINE=InnoDB