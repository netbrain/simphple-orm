<?php

namespace SimphpleOrm\Dao;

use Logger;

class Table {

    CONST VERSION_FIELD = '_version';

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var TableField[]
     **/
    private $fields = array();

    /**
     * @var TableField[]
     **/
    private $fieldsByFieldName = array();

    /**
     * @var TableField[]
     **/
    private $fieldsByPropertyName = array();

    /**
     * @var ForeignKeyConstraint[]
     */
    private $foreignKeyConstaints = array();

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
     * @var Database
     */
    private $database;

    /**
     * @param $reflectedEntity \ReflectionClass
     * @param $database Database
     */
    function __construct($reflectedEntity,$database) {
        $this->logger = Logger::getRootLogger();
        $this->reflectedEntity = $reflectedEntity;
        if($this->isBoundToEntity()){
            $this->addField(new TableField(self::VERSION_FIELD, self::VERSION_FIELD, "INT", TableField::NOT_NULL, 1));
        }
        $this->database = $database;
    }


    public function addField(TableField $field) {
        $this->catalogField($field);
        if ($field->isPrimaryKey()) {
            $this->primaryKey = $field;
        }
        $this->fieldsSorted = false;
        $field->setTable($this);
    }

    public function addForeignKeyConstraint(ForeignKeyConstraint $foreignKeyConstraint){
        $this->foreignKeyConstaints[] = $foreignKeyConstraint;
    }

    public function getDropTableSQL() {
        return "DROP TABLE IF EXISTS {$this->name}";
    }

    public function getCreateTableSQL() {
        $sql = "CREATE TABLE IF NOT EXISTS %s (%s) ENGINE=InnoDB";
        $sql = sprintf($sql, $this->getName(), $this->getFieldsDefinitionSQL());
        return $sql;
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
        $constraintsSql = array();
        $fields = $this->getFields();
        foreach ($fields as $field) {
            $propertiesSql[] = $this->getFieldDefinitionSQL($field);
            $constraintsSql[] = $this->getConstaintsSQL($field);
        }

        $constraintsSql = array_filter($constraintsSql);
        $sql = join(',', array_merge($propertiesSql,$constraintsSql));

        return $sql;
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

                    if ($e1->isForeignKey()) {
                        return 1;
                    }

                    if ($e2->isForeignKey()) {
                        return -1;
                    }


                    return strnatcmp($e1->getFieldName(), $e2->getFieldName());


                }
            );
            $this->fieldsSorted = true;
        }
    }


    /**
     * @param $field TableField
     * @return string
     */
    private function getFieldDefinitionSQL($field) {
        $sqlArray[] = $this->getSQLFormattedFieldName($field);
        $sqlArray[] = $field->getType();
        $sqlArray[] = $field->isNotNull() ? 'NOT NULL' : false;
        $sqlArray[] = $field->hasDefault() ? 'DEFAULT ' . $field->getDefault() : false;
        $sqlArray[] = $field->isAutoIncrement() ? 'AUTO_INCREMENT' : false;
        $sqlArray = array_filter($sqlArray);

        return join(' ', $sqlArray);
    }

    /**
     * @param $field TableField
     * @return string
     */
    private function getConstaintsSQL($field) {
        $sqlArray[] = $field->isPrimaryKey() ? "PRIMARY KEY ({$this->getSQLFormattedFieldName($field)})" : false;
        $sqlArray[] = $field->isUnique() ? "UNIQUE ({$this->getSQLFormattedFieldName($field)})" : false;

        if($fkConstraint = $field->getForeignKeyConstraint()){
            $primaryKeyField = $fkConstraint->getPrimaryKeyField();
            $foreignKeyField = $fkConstraint->getForeignKeyField();
            $tableName = $primaryKeyField->getTable()->getName();

            $sqlArray[] = sprintf("FOREIGN KEY (%s) REFERENCES %s (%s) ON DELETE CASCADE",
                $this->getSQLFormattedFieldName($foreignKeyField),
                $tableName,
                $this->getSQLFormattedFieldName($primaryKeyField)
                );
        }

        $sqlArray = array_filter($sqlArray);
        return join(',', $sqlArray);
    }

    public function getFindSQL($id) {
        $id = $this->getSQLFormattedValue($id, $this->getPrimaryKeyField());
        $fields = $this->getSQLFields($this->getFields());
        $query = sprintf('SELECT %s FROM %s WHERE %s = %s LIMIT 1',
            $fields, $this->getName(), $this->getPrimaryKeyField()->getFieldName(), $id);
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
        }else{
            throw new \RuntimeException("Unknown type");
        }
    }

    /**
     * @return TableField
     */
    public function getPrimaryKeyField() {
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
        return '`' . $field->getFieldName() . '`';
    }

    /**
     * @return TableField[]
     */
    public function getFields() {
        return $this->fields;
    }

    function __toString() {
        return $this->getName();
    }

    /**
     * @return TableField[]
     */
    public function getFieldsWithForeignKeyConstraint() {
        return array_filter($this->getFields(), function ($field) {
            /**
             * @var $field TableField
             */
            return $field->isForeignKey();
        });
    }

    public function getCreateSQL($obj, $parentId = null, $fkField = null) {
        $fields = $this->getFields();

        $fieldValues = array();
        foreach ($fields as $field) {
            if($parentId && $fkField && $field === $fkField){
                $value = $this->getSQLFieldValue($parentId, $fkField);
                $fieldValues[] = $value;
            }else{
                $value = $this->getPropertyValue($obj, $field);
                if(is_object($value) || is_array($value)){
                    $value = null;
                }
                $value = $this->getSQLFieldValue($value, $field);
                $fieldValues[] = $value;
            }
        }

        $sqlFields = $this->getSQLFields($fields);
        $sqlFieldValues = $this->getSQLInsertValues($fieldValues);

        return sprintf("INSERT INTO %s (%s) VALUES (%s)",$this->getName(),$sqlFields, $sqlFieldValues);
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

            if (property_exists($obj, $field->getPropertyName())) {
                $reflectionObject = new \ReflectionObject($obj);
                $property = $reflectionObject->getProperty($field->getPropertyName());
                if(!$property->isPublic()){
                    $property->setAccessible(true);
                    $value = $property->getValue($obj);
                    $property->setAccessible(false);
                    return $value;
                }else{
                    return $obj->{$field->getPropertyName()};
                }
            }
            return null;
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


        if (is_bool($value)) {
            return strtoupper(var_export($value, true));
        }

        if ($field->isNumericType()) {
            return $value;
        }
        return "'" . $value . "'";
    }

    private function getId($entity) {
        return $this->getPropertyValue($entity, $this->getPrimaryKeyField());
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

        $fields = $this->getFields();
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
        $id = $this->getSQLFormattedValue($id, $this->getPrimaryKeyField());
        return sprintf("SELECT %s FROM %s WHERE %s = %s LIMIT 1", $this->getVersionField()->getFieldName(), $this->getName(), $this->getPrimaryKeyField()->getFieldName(), $id);

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

    public function getDeleteSQL($entity) {
        $id = $this->getSQLFormattedValue($this->getId($entity), $this->getPrimaryKeyField());
        return sprintf("DELETE FROM %s WHERE %s = %s AND %s = %s",
            $this->getName(), $this->getPrimaryKeyField()->getFieldName(),
            $id,
            $this->getVersionField()->getFieldName(),
            $this->getVersion($entity));
    }

    public function getDeleteByIdSQL($id) {
        return sprintf("DELETE FROM %s WHERE %s = %s",
            $this->getName(), $this->getPrimaryKeyField()->getFieldName(),
            $id);
    }

    public function getAllSQL() {
        return sprintf("SELECT * FROM %s",$this->getName());
    }


    /**
     * @param $id
     * @param $field TableField
     * @return string
     */
    public function getFindByFKSQL($id, $field) {
        return sprintf("SELECT * FROM %s WHERE %s = %s",$this->getName(),$this->getSQLFormattedFieldName($field),$this->getSQLFormattedValue($id,$field));
    }

    /**
     * @param $joinField TableField
     * @param $id mixed
     * @return string
     */
    public function getJoinSql($joinField, $id) {
        $id = $this->getSQLFormattedValue($id,$this->getPrimaryKeyField());
        $fields = [];
        foreach($this->getFields() as $field){
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
     * @param TableField $field
     */
    protected function catalogField(TableField $field) {
        if(!$this->isBoundToEntity()){
            $field->setPropertyName(uniqid());
        }
        $this->fieldsByFieldName[$field->getFieldName()] = $field;
        $this->fieldsByPropertyName[$field->getPropertyName()] = $field;
        $this->fields[] = $field;

    }

    /**
     * Retrieve a list of fields relevant for an entity.
     * The fields relevant to an entity is it's local fields and the remote foreign key fields.
     * @return TableField[]
     */
    public function getEntityFields() {
        return array_merge(array_filter($this->getFields(),function($field){
            /**
             * @var $field TableField
             */
            return !$field->isForeignKey();
        }),$this->getIncomingForeignKeyFields());
    }

    /**
     * @return ForeignKeyConstraint[]
     */
    public function getForeignKeyConstaints() {
        return $this->foreignKeyConstaints;
    }

    /**
     * @return TableField[]
     */
    public function getIncomingForeignKeyFields() {
        return array_filter(array_map(function($fkConstraint){
            /**
             * @var $fkConstraint ForeignKeyConstraint
             */
            if($fkConstraint->getPrimaryKeyField() === $this->getPrimaryKeyField()){
                return $fkConstraint->getForeignKeyField();
            }
            return false;
        },$this->foreignKeyConstaints));
    }


}
