INSERT INTO Test (
  `myId`,
  `some-bool`,
  `some-float`,
  `some-integer`,
  `some-string`,
  `one_to_one_child`,
  `one_to_many_child`,
  `_version`
) VALUES (
  '%s',
  TRUE,
  NULL,
  NULL,
  'some string',
  NULL,
  NULL,
  1
)