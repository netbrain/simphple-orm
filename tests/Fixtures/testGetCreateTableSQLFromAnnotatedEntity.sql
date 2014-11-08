CREATE TABLE IF NOT EXISTS Test (
  `myId`         VARCHAR(13),
  `some-bool`    BOOL,
  `some-float`   FLOAT,
  `some-integer` INT,
  `some-string`  VARCHAR(60) NOT NULL,
  `_version`     INT         NOT NULL DEFAULT 1,
  PRIMARY KEY (`myId`),
  UNIQUE (`some-integer`)
)
  ENGINE=InnoDB