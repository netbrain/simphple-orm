<?php

namespace SimphpleOrm\Dao;


class TableField {

    const PRIMARY = 1;
    const AUTO_INCREMENT = 2;
    const UNIQUE = 4;
    CONST NOT_NULL = 8;

    /**
     * @var string
     */
    private $fieldName;

    /**
     * @var string
     */
    private $propertyName;

    /**
     * @var string
     */
    private $type;

    /**
     * @var integer
     */
    private $flags = 0;

    /**
     * @var TableData
     */
    private $reference;

    function __construct($fieldName, $propertyName, $type, $flags = 0, $default = null, TableData $reference = null) {
        $this->flags = $flags;
        $this->fieldName = $fieldName;
        $this->propertyName = $propertyName;
        $this->type = $type;
        $this->reference = $reference;
        $this->default = $default;
    }


    public function isPrimaryKey() {
        return $this->isFlagSet(self::PRIMARY);
    }

    private function isFlagSet($flag) {
        return (($this->flags & $flag) == $flag);
    }

    public function isAutoIncrement() {
        return $this->isFlagSet(self::AUTO_INCREMENT);
    }

    public function isUnique() {
        return $this->isFlagSet(self::UNIQUE);
    }

    public function isNotNull() {
        return $this->isFlagSet(self::NOT_NULL);
    }

    public function removeFlag($flag) {
        if ($this->isFlagSet($flag)) {
            $this->flags ^= $flag;
        }
    }

    public function addFlag($flag) {
        $this->flags |= $flag;
    }


    public function getDefault() {
        return $this->default;
    }

    public function setDefault($default) {
        $this->default = $default;
    }

    /**
     * Returns the PHP property name
     * @return string
     */
    public function getPropertyName() {
        return $this->propertyName;
    }

    /**
     * @param string $name
     */
    public function setPropertyName($name) {
        $this->propertyName = $name;
    }

    /**
     * @return TableData
     */
    public function getReference() {
        return $this->reference;
    }

    /**
     * @param TableData $reference
     */
    public function setReference(TableData $reference) {
        $this->reference = $reference;
    }

    public function isReference() {
        return !is_null($this->reference);
    }

    public function isNumericType() {
        switch ($this->getType()) {
            case 'INT':
            case 'INTEGER':
            case 'TINYINT':
            case 'MEDIUMINT':
            case 'DOUBLE':
            case 'BIT':
            case 'SMALLINT':
            case 'BIGINT':
            case 'FLOAT':
            case 'DECIMAL':
                return true;
            default:
                return false;
        }
    }

    /**
     * @return string
     */
    public function getType() {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType($type) {
        $this->type = $type;
    }

    public function isBoolType() {
        switch ($this->getType()) {
            case 'BOOL':
            case 'BOOLEAN':
                return true;
            default:
                return false;
        }
    }

    public function isStringType() {
        switch ($this->getType()) {
            case 'TINYTEXT':
            case 'TEXT':
            case 'LONGTEXT':
                return true;
            default:
                if (strpos($this->getType(), "VARCHAR") === 0) {
                    return true;
                }
                return false;
        }
    }

    public function hasDefault() {
        return !is_null($this->default);
    }

    public function isVersion() {
        return $this->getFieldName() === TableData::VERSION_FIELD;
    }

    /**
     * @return string
     */
    public function getFieldName() {
        return $this->fieldName;
    }

    /**
     * Returns the SQL field name
     * @param string $name
     */
    public function setFieldName($name) {
        $this->fieldName = $name;
    }

}