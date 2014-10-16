CREATE TABLE IF NOT EXISTS Test (
  `myId`              VARCHAR(13) PRIMARY KEY,
  `some-bool`         BOOL,
  `some-float`        FLOAT,
  `some-integer`      INT UNIQUE,
  `some-string`       VARCHAR(60) NOT NULL,
  `one_to_one_child`  INT,
  `one_to_many_child` INT,
  `_version`          INT         NOT NULL DEFAULT 1
)