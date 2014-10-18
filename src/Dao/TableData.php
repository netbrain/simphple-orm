<?php

namespace SimphpleOrm\Dao;

use Logger;

class TableData {

    CONST VERSION_FIELD = '_version';

    /**
     * @var Logger
     */
    private $logger;
    /**
     * @var TableField[]
     **/
    private $fields;

    /**
     * @var TableField[]
     **/
    private $fieldsByFieldName;

    /**
     * @var TableField[]
     **/
    private $fieldsByPropertyName;

    /**
     * @var TableField
     */
    private $primaryKey;

    /**
     * @var string
     */
    private $name;
    /**
     * @var \ReflectionClass
     */
    private $reflectedEntity;

    /**
     * @var bool
     */
    private $fieldsSorted = false;

    /**
     * @param $reflectedEntity \ReflectionClass
     */
    function __construct($reflectedEntity) {
        $this->logger = Logger::getRootLogger();
        $this->reflectedEntity = $reflectedEntity;
        $this->addField(new TableField(self::VERSION_FIELD, self::VERSION_FIELD, "INT", TableField::NOT_NULL, 1));
    }


    public function addField(TableField $field) {
        $this->fields[] = $field;
        $this->fieldsByFieldName[$field->getFieldName()] = $field;
        $this->fieldsByPropertyName[$field->getPropertyName()] = $field;
        if ($field->isPrimaryKey()) {
            $this->primaryKey = $field;
        }
        $this->fieldsSorted = false;
    }

    public function getDropTableSQL() {
        return "DROP TABLE IF EXISTS {$this->name}";
    }

    public function getCreateTableSQL() {
        return sprintf("CREATE TABLE IF NOT EXISTS %s (%s)", $this->getName(), $this->getFieldsDefinitionSQL());
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @param string $tableName
     */
    public function setName($tableName) {
        $this->name = $tableName;
    }

    private function getFieldsDefinitionSQL() {
        $this->sortFields();
        $propertiesSql = array();
        foreach ($this->getLocalFields() as $field) {
            $propertiesSql[] = $this->getFieldDefinitionSQL($field);
        }
        return join(',', $propertiesSql);
    }

    private function sortFields() {
        if (!$this->fieldsSorted) {
            uasort($this->fields,
                function ($e1, $e2) {
                    /**
                     * @var $e1 TableField
                     * @var $e2 TableField
                     */
                    if ($e1->isPrimaryKey()) {
                        return -1;
                    }

                    if ($e2->isPrimaryKey()) {
                        return 1;
                    }

                    if ($e1->isVersion()) {
                        return 1;
                    }

                    if ($e2->isVersion()) {
                        return -1;
                    }

                    if ($e1->isReference()) {
                        return 1;
                    }

                    if ($e2->isReference()) {
                        return -1;
                    }


                    return strnatcmp($e1->getFieldName(), $e2->getFieldName());


                }
            );
            $this->fieldsSorted = true;
        }
    }

    private function getFieldDefinitionSQL(TableField $field) {
        $sqlArray[] = '`' . $field->getFieldName() . '`';
        $sqlArray[] = $field->getType();
        $sqlArray[] = $field->isNotNull() ? 'NOT NULL' : false;
        $sqlArray[] = $field->hasDefault() ? 'DEFAULT ' . $field->getDefault() : false;
        $sqlArray[] = $field->isUnique() ? 'UNIQUE' : false;
        $sqlArray[] = $field->isPrimaryKey() ? 'PRIMARY KEY' : false;
        $sqlArray[] = $field->isAutoIncrement() ? 'AUTO_INCREMENT' : false;
        $sqlArray = array_filter($sqlArray);

        return join(' ', $sqlArray);
    }

    public function getFindSQL($id) {
        $id = $this->getSQLFormattedValue($id, $this->getPrimaryKey());
        $fields = $this->getSQLFields($this->getLocalFields());
        $query = sprintf('SELECT %s FROM %s WHERE %s = %s LIMIT 1',
            $fields, $this->getName(), $this->getPrimaryKey()->getFieldName(), $id);
        return $query;
    }

    /**
     * @param $value
     * @param $tableField TableField
     * @return string
     */
    private function getSQLFormattedValue($value,$tableField) {
        if($tableField->isNumericType()){
            return var_export($value,true);
        }else if($tableField->isBoolType()){
            return strtoupper(var_export($value, true));
        }else if($tableField->isStringType()){
            if(!is_string($value)){
                $value = var_export($value,true);
            }
            return sprintf("'%s'",$value);
        }
    }

    /**
     * @return TableField
     */
    public function getPrimaryKey() {
        return $this->primaryKey;
    }

    /**
     * @param $fields TableField[]
     * @return string
     */
    protected function getSQLFields($fields) {
        return join(',', $this->getSQLFormattedFieldNames($fields));
    }

    /**
     *
     * @param $fields TableField[]
     *
     * @return array
     */
    protected function getSQLFormattedFieldNames($fields) {
        $fields = array_filter($fields);
        /**
         * @var $field TableField
         */
        foreach ($fields as $key => $field) {
            $fields[$key] = $this->getSQLFormattedFieldName($field);
        }
        return $fields;
    }

    /**
     * @param $field TableField
     * @return mixed
     */
    protected function getSQLFormattedFieldName($field) {
        $name = '`' . $field->getFieldName() . '`';
        return $name;
    }

    /**
     * @return TableField[]
     */
    public function getFields() {
        return $this->fields;
    }

    /**
     * Get fields local to this table
     * not one-to-many assosciations
     */
    public function getLocalFields(){
        return array_filter($this->getFields(),function($field){
            /**
             * @var $field TableField
             */
            return !$field->isOneToMany();
        });
    }

    function __toString() {
        return $this->getName();
    }

    /**
     * @return TableField[]
     */
    public function getFieldsWithReference() {
        return array_filter($this->getFields(), function ($field) {
            /**
             * @var $field TableField
             */
            return $field->isReference();
        });
    }

    /**
     * @return TableField[]
     */
    public function getFieldsWithOneToManyRelationship() {
        return array_filter($this->getFields(), function ($field) {
            /**
             * @var $field TableField
             */
            return $field->isOneToMany();
        });
    }


    /**
     * @return TableField[]
     */
    public function getPrimitiveFields() {
        return array_filter($this->getFields(), function ($field) {
            /**
             * @var $field TableField
             */
            return !$field->isReference();
        });
    }

    public function getCreateSQL($obj) {
        $fields = $this->getLocalFields();

        $sqlFields = $this->getSQLFields($fields);
        $fieldValues = array();
        foreach ($fields as $field) {
            $value = $this->getPropertyValue($obj, $field);
            $value = $this->getSQLFieldValue($value, $field);
            $fieldValues[] = $value;
        }
        $sqlFieldValues = $this->getSQLInsertValues($fieldValues);

        return sprintf("INSERT INTO {$this->name} (%s) VALUES (%s)", $sqlFields, $sqlFieldValues);
    }

    /**
     * @param $obj mixed
     * @param $field string|TableField
     *
     * @return mixed
     */
    public function getPropertyValue($obj, $field) {
        if (is_string($field)) {
            $field = $this->getTableFieldByPropertyName($field);
        }

        if (is_null($field)) {
            throw new \InvalidArgumentException("Could not find field");
        }

        if ($field->isVersion()) {
            if (property_exists($obj, $field->getPropertyName())) {
                return $obj->{$field->getPropertyName()};
            }
            return $field->getDefault();
        } else {
            if($this->reflectedEntity !== null){
                $property = $this->reflectedEntity->getProperty($field->getPropertyName());
                $property->setAccessible(true);
                $value = $property->getValue($obj);
                $property->setAccessible(false);
                return $value;
            }else{
                if (property_exists($obj, $field->getPropertyName())) {
                    return $obj->{$field->getPropertyName()};
                }else{
                    return null;
                }
            }
        }
    }

    /**
     * @param $name
     * @return TableField
     */
    public function getTableFieldByPropertyName($name) {
        return $this->fieldsByPropertyName[$name];
    }

    /**
     * @param $value mixed
     * @param $field TableField
     *
     * @return string
     */
    private function getSQLFieldValue($value, $field) {
        if (!isset($value)) {
            if ($field->isPrimaryKey()) {
                return 'DEFAULT';
            }

            if ($field->hasDefault()) {
                $value = $field->getDefault();
            } else {
                return 'NULL';
            }
        }

        if ($field->isReference()) {
            if(is_object($value)){
                $value = $field->getReference()->getId($value);
            }
        }

        if (is_bool($value)) {
            return strtoupper(var_export($value, true));
        }

        if ($field->isNumericType()) {
            return $value;
        }
        return "'" . $value . "'";
    }

    private function getId($entity) {
        return $this->getPropertyValue($entity, $this->getPrimaryKey());
    }

    /**
     * @param $fieldValues array
     *
     * @return string
     */
    private function getSQLInsertValues($fieldValues) {
        return join(',', $fieldValues);
    }

    public function getUpdateSQL($obj) {
        if ($obj instanceof EntityProxy) {
            $obj = $obj->getDelegate();
        }

        $fields = $this->getLocalFields();
        $idName = $idValue = null;
        $fieldValues = array();
        $oldVersion = null;
        $versionFieldName = null;

        foreach ($fields as $field) {
            if ($field->isPrimaryKey()) {
                $idName = $field->getFieldName();
                $idValue = $this->getSQLFieldValue($this->getId($obj), $field);
            } else {
                $value = $this->getPropertyValue($obj, $field->getPropertyName());
                if ($field->isVersion()) {
                    $oldVersion = $value;
                    $versionFieldName = $field->getFieldName();
                    $value++; //increment version
                }
                $value = $this->getSQLFieldValue($value, $field);
                $fieldValues[$this->getSQLFormattedFieldName($field)] = $value;
            }
        }
        $sqlFieldValues = $this->getSQLUpdateValues($fieldValues);


        return sprintf("UPDATE %s SET %s WHERE %s = %s AND %s = %s LIMIT 1",
            $this->getName(), $sqlFieldValues, $idName, $idValue, $versionFieldName, $oldVersion);
    }

    /**
     * @param $fieldValues array
     * @return string
     */
    private function getSQLUpdateValues($fieldValues) {
        return join(',', array_map(
            function ($fieldName) use ($fieldValues) {
                return "$fieldName={$fieldValues[$fieldName]}";
            }, array_keys($fieldValues)
        ));
    }

    /**
     * @param $obj mixed
     * @param $field string|TableField
     * @return bool
     */
    public function hasPropertyValue($obj, $field) {
        return !is_null($this->getPropertyValue($obj, $field));
    }

    /**
     * @param $obj mixed
     * @param $field string|TableField
     * @param $value mixed
     */
    public function setPropertyValue(&$obj, $field, $value) {
        if (is_string($field)) {
            $field = $this->getTableFieldByPropertyName($field);
        }

        if (is_null($field)) {
            throw new \InvalidArgumentException("Could not find field");
        }

        if ($field->isVersion()) {
            $obj->{$field->getPropertyName()} = $value;
        } else {
            $property = $this->reflectedEntity->getProperty($field->getPropertyName());
            $property->setAccessible(true);
            $property->setValue($obj, $value);
            $property->setAccessible(false);
        }
    }

    /**
     * Returns true if this table is bound to an entity.
     * false if not.
     *
     * Usually this means this table does not represent a single entity.
     * Fx. a join table.
     * @return bool
     */
    public function isBoundToEntity(){
       return $this->reflectedEntity !== null;
    }

    /**
     * @return object
     */
    public function getEntityInstance() {
        return $this->reflectedEntity->newInstanceWithoutConstructor();
    }

    /**
     * @return string
     */
    public function getEntityClassName() {
        return $this->reflectedEntity->getName();
    }

    public function getVersionSQL($id) {
        $id = $this->getSQLFormattedValue($id, $this->getPrimaryKey());
        return sprintf("SELECT %s FROM %s WHERE %s = %s LIMIT 1", $this->getVersionField()->getFieldName(), $this->getName(), $this->getPrimaryKey()->getFieldName(), $id);

    }

    public function getVersionField() {
        return $this->getTableFieldByFieldName(self::VERSION_FIELD);
    }

    /**
     * @param $name
     * @return TableField
     */
    public function getTableFieldByFieldName($name) {
        return $this->fieldsByFieldName[$name];
    }

    public function getDeleteSQL($obj) {
        $id = $this->getSQLFormattedValue($this->getId($obj),$this->getPrimaryKey());
        return sprintf("DELETE FROM %s WHERE %s = %s AND %s = %s",
            $this->getName(), $this->getPrimaryKey()->getFieldName(),
            $id,
            $this->getVersionField()->getFieldName(),
            $this->getVersion($obj));

    }

    public function getAllSQL() {
        return sprintf("SELECT * FROM %s",$this->getName());
    }


    /**
     * @param $joinField TableField
     * @param $id mixed
     * @return string
     */
    public function getJoinSql($joinField, $id) {
        $id = $this->getSQLFormattedValue($id,$this->getPrimaryKey());
        $fields = [];
        foreach($this->getFieldsWithReference() as $field){
            if($field !== $joinField){
                $fields[] = $field;
            }
        }
        $fields = $this->getSQLFields($fields);
        return sprintf("SELECT %s FROM %s WHERE %s = %s ",$fields, $this->getName(), $joinField->getFieldName(), $id);
    }

    private function getVersion($obj) {
        return $this->getPropertyValue($obj,$this->getVersionField());
    }

    /**
     * @param $tableData TableData
     * @return \SimphpleOrm\Dao\TableField
     */
    public function getFieldThatReferences($tableData) {
        /**
         * @var $field TableField
         */
        foreach($this->getFieldsWithReference() as $field){
            if($field->getReference() === $tableData){
                return $field;
            };
        }
    }

}
