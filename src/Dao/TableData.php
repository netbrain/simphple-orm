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
        $this->addField(new TableField(self::VERSION_FIELD,self::VERSION_FIELD,"INT",TableField::NOT_NULL,1));
    }


    public function addField(TableField $field) {
        $this->fields[] = $field;
        $this->fieldsByFieldName[$field->getFieldName()] = $field;
        $this->fieldsByPropertyName[$field->getPropertyName()] = $field;
        if($field->isPrimaryKey()){
            $this->primaryKey = $field;
        }
        $this->fieldsSorted = false;
    }

    /**
     * @return TableField[]
     */
    public function getFields() {
        return $this->fields;
    }

    /**
     * @param string $tableName
     */
    public function setName($tableName) {
        $this->name = $tableName;
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    public function getDropTableSQL() {
        return "DROP TABLE IF EXISTS {$this->name}";
    }

    public function getCreateTableSQL() {
        return sprintf("CREATE TABLE IF NOT EXISTS %s (%s)",$this->getName(),$this->getFieldsDefinitionSQL());
    }

    public function  getFindSQL($id) {
        $id = $this->getSQLFormattedID($id);
        $fields = $this->getSQLFields($this->getFields());
        $query = sprintf('SELECT %s FROM %s WHERE %s = %s LIMIT 1',
            $fields, $this->getName(), $this->getPrimaryKey()->getFieldName(), $id);
        return $query;
    }

    /**
     * @return TableField
     */
    public function getPrimaryKey() {
        return $this->primaryKey;
    }

    private function getFieldsDefinitionSQL() {
        $this->sortFields();
        $propertiesSql = array();
        foreach ($this->fields as $field) {
            $propertiesSql[] = $this->getFieldDefinitionSQL($field);
        }
        return join(',', $propertiesSql);
    }

    private function getFieldDefinitionSQL(TableField $field) {
        $sqlArray[] = '`' . $field->getFieldName() . '`';
        $sqlArray[] = $field->getType();
        $sqlArray[] = $field->isNotNull() ? 'NOT NULL' : false;
        $sqlArray[] = $field->hasDefault() ? 'DEFAULT '.$field->getDefault() : false;
        $sqlArray[] = $field->isUnique() ? 'UNIQUE' : false;
        $sqlArray[] = $field->isPrimaryKey() ? 'PRIMARY KEY' : false;
        $sqlArray[] = $field->isAutoIncrement() ? 'AUTO_INCREMENT' : false;
        $sqlArray = array_filter($sqlArray);

        return join(' ', $sqlArray);
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
    public function getPrimitiveFields() {
        return array_filter($this->getFields(), function ($field) {
            /**
             * @var $field TableField
             */
            return !$field->isReference();
        });
    }

    public function getCreateSQL($obj) {
        $fields = $this->getFields();

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

    public function getUpdateSQL($obj) {
        if($obj instanceof EntityProxy) {
            $obj = $obj->getDelegate();
        }

        $fields = $this->getFields();
        $idName = $idValue = null;
        $fieldValues = array();
        $oldVersion = null;
        $versionFieldName = null;

        foreach ($fields as $field) {
            if($field->isPrimaryKey()) {
                $idName = $field->getFieldName();
                $idValue = $this->getSQLFieldValue($this->getId($obj), $field);
            /*}else if($field->isReference()){
                $value = $this->getPropertyValue($obj, $field->getPropertyName());
                $referenceTableData = TableDataBuilder::build(get_class($value));
                $referenceIdName = $referenceTableData->getPrimaryKey()->getPropertyName();
                if($this->isTransient($value)){
                    $referenceId = DaoFactory::getDaoFromEntity(get_class($value))->create($value)->getDelegatePropertyValue($referenceIdName);
                }else{
                    $referenceId = $referenceTableData->getPropertyValue($obj,$referenceIdName);
                }
                $fieldValues[$field->getFieldName()] = $referenceId;
            */}else{
                $value = $this->getPropertyValue($obj, $field->getPropertyName());
                if($field->isVersion()){
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
            $this->getName(),$sqlFieldValues,$idName,$idValue,$versionFieldName,$oldVersion);
    }

    /*private function isTransient($obj){
        return is_null($this->getPropertyValue($obj,$this->getPrimaryKey()->getPropertyName()));
    }*/

    /**
     * @param $obj mixed
     * @param $field string|TableField
     *
     * @return mixed
     */
    public function getPropertyValue($obj, $field) {
        if(is_string($field)){
            $field = $this->getTableFieldByPropertyName($field);
        }

        if(is_null($field)){
            throw new \InvalidArgumentException("Could not find field");
        }

        if($field->isVersion()){
            if(property_exists($obj,$field->getPropertyName())){
                return $obj->{$field->getPropertyName()};
            }
            return $field->getDefault();
        }else{
            $property = $this->reflectedEntity->getProperty($field->getPropertyName());
            $property->setAccessible(true);
            $value = $property->getValue($obj);
            $property->setAccessible(false);
            return $value;
        }
    }

    /**
     * @param $obj mixed
     * @param $field string|TableField
     * @return bool
     */
    public function hasPropertyValue($obj, $field){
        return !is_null($this->getPropertyValue($obj,$field));
    }

    /**
     * @param $obj mixed
     * @param $field string|TableField
     * @param $value mixed
     */
    public function setPropertyValue(&$obj, $field, $value) {
        if(is_string($field)){
            $field = $this->getTableFieldByPropertyName($field);
        }

        if(is_null($field)){
            throw new \InvalidArgumentException("Could not find field");
        }

        if($field->isVersion()){
            $obj->{$field->getPropertyName()} = $value;
        }else{
            $property = $this->reflectedEntity->getProperty($field->getPropertyName());
            $property->setAccessible(true);
            $property->setValue($obj,$value);
            $property->setAccessible(false);
        }
    }

    /**
     *
     * @param $fields TableField[]
     *
     * @return array
     */
    protected function getSQLFormattedFieldNames($fields) {
        $fields = array_filter($fields);
        foreach ($fields as $key => $field) {
            $fields[$key] = $this->getSQLFormattedFieldName($field);
        }
        return $fields;
    }

    /**
     * @param $fields TableField[]
     * @return string
     */
    protected function getSQLFields($fields) {
        return join(',', $this->getSQLFormattedFieldNames($fields));
    }

    /**
     * @param $fieldValues array
     *
     * @return string
     */
    private function getSQLInsertValues($fieldValues) {
        return join(',', $fieldValues);
    }

    /**
     * @param $fieldValues array
     * @return string
     */
    private function getSQLUpdateValues($fieldValues) {
        return join(',',array_map(
            function($fieldName) use ($fieldValues) {
                return "$fieldName={$fieldValues[$fieldName]}";
            }, array_keys($fieldValues)
        ));
    }

    /**
     * @param $value mixed
     * @param $field TableField
     *
     * @return string
     */
    private function getSQLFieldValue($value, $field) {
        if (!isset($value)) {
            if($field->isPrimaryKey()){
                return 'DEFAULT';
            }

            if($field->hasDefault()){
                $value = $field->getDefault();
            }else{
                return 'NULL';
            }
        }

        if($field->isReference()){
            if(is_array($value)){
                //FIXME
                return 'NULL';
            }
            return $field->getReference()->getId($value);
        }

        if (is_bool($value)) {
            return strtoupper(var_export($value, true));
        }

        if ($field->isNumericType()) {
            return $value;
        }
        return "'" . $value . "'";
    }

    /**
     * @param $name
     * @return TableField
     */
    public function getTableFieldByFieldName($name) {
        return $this->fieldsByFieldName[$name];
    }

    /**
     * @param $name
     * @return TableField
     */
    public function getTableFieldByPropertyName($name) {
        return $this->fieldsByPropertyName[$name];
    }

    /**
     * @return object
     */
    public function getEntityInstance() {
        return $this->reflectedEntity->newInstanceWithoutConstructor();
    }

    public function getVersionSQL($id) {
        $id = $this->getSQLFormattedID($id);
        return sprintf("SELECT %s FROM %s WHERE %s = %s LIMIT 1",$this->getVersionField()->getFieldName(),$this->getName(), $this->getPrimaryKey()->getFieldName(), $id);

    }

    /**
     * @param $id
     * @return string
     */
    private function getSQLFormattedID($id) {
        if (!$this->getPrimaryKey()->isNumericType()) {
            $id = "'$id'";
            return $id;
        }
        return $id;
    }

    public function getVersionField() {
        return $this->getTableFieldByFieldName(self::VERSION_FIELD);
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

    private function getId($entity) {
        return $this->getPropertyValue($entity,$this->getPrimaryKey());
    }

    /**
     * @param $field TableField
     * @return mixed
     */
    protected function getSQLFormattedFieldName($field) {
        $name = '`' . $field->getFieldName() . '`';
        return $name;
    }


}
