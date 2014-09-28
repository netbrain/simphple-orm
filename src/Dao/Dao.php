<?php

namespace SimphpleOrm\Dao;

use Logger;
use ReflectionClass;
use ReflectionObject;
use stdClass;

abstract class Dao {

	/**
	 * @var Logger
	 */
	protected $logger;

    /**
     * @var \mysqli
     */
    protected $db;

    /**
     * @var TableData
     */
    protected $tableData;

    /**
     * @var \ReflectionClass
     */
    protected $reflectionEntityClass;

    function __construct(\mysqli $db) {
	    $this->logger = Logger::getLogger("main");
        $this->db = $db;
        $this->reflectionEntityClass = new ReflectionClass($this->getEntityClass());
        $this->tableData = TableDataBuilder::build($this->getEntityClass());
	    $this->runQuery($this->tableData->getCreateTableSQL());
    }

    /**
     * @param $id
     * @return null|object
     * @throws \Exception
     */
    public function find($id) {
        $entity = $this->findEntity($id);
        $entity = $this->cast($entity);
        return $entity;
    }

    private function findEntity($id) {
        $result = $this->runQuery($this->tableData->getFindSQL($id));

        if (!$result) {
            return null;
        }

        $obj = $result->fetch_object();
        return $obj;
    }

    public function refresh(&$obj){
        $entity = $this->findEntity($this->getIdValue($obj));
        $this->cast($entity,$obj);
    }


    //FIXME this needs to be optimized!
    public function all() {
        $query = "SELECT id FROM {$this->tableData->getName()}";
        $result = $this->runQuery($query);

        $entities = array();
        while ($row = $result->fetch_assoc()) {
            $entities[] = $this->find($row['id']);
        }

        return $entities;
    }


	public function create( $obj ) {

        foreach($this->tableData->getFieldsWithReference() as $referencedField){
            if($this->tableData->hasPropertyValue($obj,$referencedField)){
                $referencedValue = $this->tableData->getPropertyValue($obj,$referencedField);
                if(is_array($referencedValue)){
                    //collection of entities (one-to-many)
                    foreach ($referencedValue as $entity){
                        $this->persistReferencedEntity($entity);
                    }
                }else{
                    //single enity (one-to-one)
                    $this->persistReferencedEntity($referencedValue);
                }

            }
        }

		$query = $this->tableData->getCreateSQL($obj);
		$this->runQuery($query);

        if($this->tableData->getPrimaryKey()->isAutoIncrement()) {
            $insertId = $this->db->insert_id;
            $this->setIdValue($obj, $insertId);
        }
        $this->setVersion($obj);
	}

    public function update($obj) {
        if($this->isTransient($obj)){
            throw new \InvalidArgumentException("Cannot update a transient entity");
        }
        $query = $this->tableData->getUpdateSQL($obj);
        $this->runQuery($query);

        if(!mysqli_affected_rows($this->db) > 0){
            throw new \RuntimeException("No rows updated by update query! either row has been deleted or another version was committed");
        }
    }

    /**
     * @param $query
     * @return bool|\mysqli_result
     * @throws \Exception
     */
    protected function runQuery($query) {
	    $this->logger->info("Ran query: ".$query);
        $result = $this->db->query($query);
        if(!$result){
            throw new \RuntimeException("query failed: $query, errorno: " . $this->db->errno . ", error:" . $this->db->error);
        }
        return $result;
    }

    /**
     * Creates entity object without invoking constructor and transfers data from
     * stdClass to entityClass
     * @param stdClass $source
     * @param null $destination
     * @return mixed
     */
    private function cast($source, $destination = null){
        $sourceReflection = new ReflectionObject($source);

        if($destination == null){
            $destination =  $this->reflectionEntityClass->newInstanceWithoutConstructor();
        }

        foreach ($this->tableData->getFields() as $field) {
            $fieldName = $field->getFieldName();
            $propertyName = $field->getPropertyName();

            $sourceProperty = $sourceReflection->getProperty($fieldName);
            if($this->reflectionEntityClass->hasProperty($propertyName)){
                $property = $this->reflectionEntityClass->getProperty($propertyName);
                if($property->isPrivate() || $property->isProtected()){
                    $property->setAccessible(true);
                    $value = $this->getPropertyValue($sourceProperty->getValue($source), $propertyName);
                    $property->setValue($destination,$value);
                    $property->setAccessible(false);
                }else{
                    $value = $this->getPropertyValue($sourceProperty->getValue($source), $propertyName);
                    $property->setValue($destination,$value);
                }
            }else{
                $destination->$propertyName = $this->getPropertyValue($sourceProperty->getValue($source),$propertyName);
            }

        }
        return $destination;
    }

    /**
     * Class name for entity this dao class handles.
     * @return string
     */
    public abstract function getEntityClass();

    /**
     * @param $value
     * @param $propertyName
     * @return mixed|\SimphpleOrm\Orm\EntityProxy
     */
    private function getPropertyValue($value, $propertyName) {
        $tableField = $this->tableData->getTableFieldByPropertyName($propertyName);
        if(!is_null($value)){
            if($tableField->isNumericType()){
                settype($value,"float");
            }elseif($tableField->isBoolType()){
                settype($value,"boolean");
            }elseif($tableField->isStringType()){
                settype($value,"string");
            }

            if($tableField->isReference()){
                $referencedTableData = $tableField->getReference();
                $entity = $referencedTableData->getEntityInstance();
                $this->setIdValue($entity,$value,$referencedTableData);
                $value = new EntityProxy($entity,DaoFactory::getDaoFromEntity($entity),$referencedTableData, false);
            }
        }
        return $value;
    }

    public function isTransient($entity){
        return !property_exists($entity,$this->tableData->getVersionField()->getPropertyName());
    }

    /**
     * @param null $tableData
     * @return string
     */
    private function getIdPropertyName($tableData = null) {
        if(is_null($tableData)){
            $tableData = $this->tableData;
        }
        return $tableData->getPrimaryKey()->getPropertyName();
    }

    /**
     * @param $entity
     * @param $value
     * @param null $tableData
     */
    private function setIdValue($entity, $value, $tableData = null) {
        if(is_null($tableData)){
            $tableData = $this->tableData;
        }
        $tableData->setPropertyValue($entity, $this->getIdPropertyName($tableData), $value);
    }

    /**
     * @param $entity
     * @param null $tableData
     * @return mixed
     */
    private function getIdValue($entity, $tableData = null){
        if(is_null($tableData)){
            $tableData = $this->tableData;
        }
        return $tableData->getPropertyValue($entity, $this->getIdPropertyName($tableData));
    }

    /**
     * @param $referencedValue
     */
    private function persistReferencedEntity($referencedValue) {
        $dao = DaoFactory::getDaoFromEntity($referencedValue);
        if (is_null($dao)) {
            throw new \RuntimeException("Unhandled entity type: " . get_class($referencedValue));
        }

        $dao->create($referencedValue);
    }

    private function setVersion($obj,$version = null) {
        $versionField = $this->tableData->getVersionField();

        if(is_null($version)){
            $version = $versionField->getDefault();
        }

        $obj->{$versionField->getPropertyName()} = $version;
    }
}