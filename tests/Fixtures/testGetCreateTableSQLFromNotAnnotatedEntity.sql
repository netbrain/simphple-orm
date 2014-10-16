CREATE TABLE IF NOT EXISTS NotAnnotatedTest (
  `id`       INT PRIMARY KEY AUTO_INCREMENT,
  `boolean`  BOOL,
  `float`    FLOAT,
  `int`      INT,
  `string`   VARCHAR(255),
  `_version` INT NOT NULL DEFAULT 1
)