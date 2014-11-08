<?php

namespace SimphpleOrm\Dao;

use Logger;
use ReflectionClass;
use ReflectionObject;
use stdClass;

abstract class Dao {

    const CACHE = '_cache';
    const TRANSIENT_PREFIX = "\$_";
    const VERSION = Table::VERSION_FIELD;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var Database
     */
    protected $database;

    /**
     * @var Table
     */
    protected $table;

    /**
     * @var \ReflectionClass
     */
    protected $reflectionEntityClass;

    /**
     * @var DaoFactory
     */
    protected $daoFactory;

    function __construct(Database $database,DaoFactory $daoFactory) {
        $this->logger = Logger::getLogger("main");
        $this->database = $database;
        $this->reflectionEntityClass = new ReflectionClass($this->getEntityClass());
        $this->table = $database->build($this->getEntityClass());
        $this->daoFactory = $daoFactory;
    }

    public function createTable(){
        $this->runQuery($this->table->getCreateTableSQL());
    }

    /**
     * Creates an entity in the database
     * @param $obj
     * @return mixed
     */
    public function create($obj) {
        $id = $this->persistEntity($obj);
        return $id;
    }

    /**
     * Retrieves an entity from the database
     * @param $id
     * @return null|object
     * @throws \Exception
     */
    public function find($id) {
        $entity = $this->findEntity($id);
        if($entity != null){
            $entity = $this->cast($entity);
        }
        return $entity;
    }

    /**
     * Deletes an entity from the database
     * @param $obj
     * @return null|object
     */
    public function delete($obj) {
        $query = $this->table->getDeleteSQL($obj);
        $this->runQuery($query);
        if(!mysqli_affected_rows($this->database->getMysqli()) > 0){
            throw new \RuntimeException("Nothing was deleted, might be due to optimistic locking failure or simply that the entity no longer exists");
        }
    }

    /**
     * Refreshes an entity from the database;
     * @param $obj
     */
    public function refresh($obj) {
        $entity = $this->findEntity($this->getIdValue($obj));
        if($entity == null){
            throw new \RuntimeException("Cannot refresh this entity, it seem's it doesn't exist anymore!");
        }
        $this->cast($entity, $obj);
    }

    /**
     * @param $destination object|array
     * @param $id
     * @param $fkConstraint ForeignKeyConstraint
     */
    public function __refreshByFK(&$destination, $id, $fkConstraint) {
        $query = $this->table->getFindByFKSQL($id,$fkConstraint->getForeignKeyField());

        $result = $this->runQuery($query);
        if($result) {
            if ($fkConstraint->isOneToOne()) {
                if(mysqli_num_rows($result) == 1){
                    $obj = mysqli_fetch_object($result);
                    $this->cast($obj, $destination);
                }else{
                    $destination = null;
                }
            } else if ($fkConstraint->isOneToMany()) {
                if(mysqli_num_rows($result) >= 1){
                    while($obj = mysqli_fetch_object($result)){
                        $element = $this->table->getEntityInstance();
                        $this->cast($obj,$element);
                        if(is_array($destination)){
                            $destination[] = $element;
                        }else if($destination instanceof \ArrayObject){
                            $destination->append($element);
                        }else {
                            throw new \RuntimeException("Invalid destination");
                        }
                    }
                }else{
                    $destination = array();
                }
            }
        }

    }

    /**
     * Retrieves all entities from the database
     * @return array
     */
    public function all() {
        $query = $this->table->getAllSQL();
        $result = $this->runQuery($query);

        $entities = array();
        while ($obj = $result->fetch_object()) {
            $entities[] = $entity = $this->cast($obj);
        }

        return $entities;
    }

    /**
     * Updates an entity to the database
     * @param $obj
     */
    public function update($obj) {
        if ($this->isTransient($obj)) {
            throw new \InvalidArgumentException("Cannot update a transient entity");
        }

        foreach ($this->getOneToManyReferences($obj) as $oneToManyFields){
            $this->handleOneToManyChanges($obj,$oneToManyFields);
        }

        $query = $this->table->getUpdateSQL($obj);
        $this->runQuery($query);

        if (!mysqli_affected_rows($this->database->getMysqli()) > 0) {
            throw new \RuntimeException("No rows updated by update query! either row has been deleted or another version was committed");
        }else{
            //increment version
            $versionPropertyName = $this->table->getVersionField()->getPropertyName();
            $obj->{$versionPropertyName}++;
        }
    }

    public function initialize($entityOrCollection){
        if($entityOrCollection == null){
            return;
        }
        if(is_array($entityOrCollection)){
            foreach($entityOrCollection as $entity){
                if(!$this->isInitialized($entity)){
                    $this->initialize($entity);
                }
            }
        }else {
            if ($entityOrCollection instanceof Proxy) {
                if (!$entityOrCollection->isInitialized()) {
                    $entityOrCollection->initialize();
                }
            }
        }
    }

    public function isInitialized($entityOrCollection) {
        if(is_array($entityOrCollection)){
            foreach($entityOrCollection as $entity){
                if(!$this->isInitialized($entity)){
                    return false;
                }
            }
            return true;
        }else{
            return !($entityOrCollection instanceof Proxy);
        }
    }

    /**
     * Initializes an entity with two levels of data
     * @param $entity
     */
    public function initializeDeep($entity){
        $this->initialize($entity);
        foreach($this->table->getIncomingForeignKeyFields() as $field){
            $value = $this->table->getPropertyValue($entity,$field);
            if($value == null){
                continue;
            }
            $this->initializeDeep($value);
        }
    }

    /**
     * Determines if an entity is stored in the database
     * @param $entity
     * @return bool
     */
    public function isTransient($entity) {
        return !property_exists($entity, $this->table->getVersionField()->getPropertyName());
    }

    /**
     * Returns the id value on an entity object
     * @param $entity
     * @param null $tableData
     * @return mixed
     */
    private function getIdValue($entity, $tableData = null) {
        if (is_null($tableData)) {
            $tableData = $this->table;
        }
        return $tableData->getPropertyValue($entity, $tableData->getPrimaryKeyField());
    }

    private function setVersion($obj, $version = null) {
        $versionField = $this->table->getVersionField();

        if (is_null($version)) {
            $version = $versionField->getDefault();
        }

        $obj->{$versionField->getPropertyName()} = $version;
    }

    /**
     * @param $parent
     * @param $child
     * @param $parentField TableField
     */
    private function persistReferencedEntity($parent,$child,$parentField) {
        $dao = $this->daoFactory->getDaoFromEntity($child);
        if (is_null($dao)) {
            throw new \RuntimeException("Unhandled entity type: " . get_class($child));
        }

        $childField = $dao->table->getTableFieldByFieldName($parentField->getFieldName());

        $dao->persistEntity($child,$this->getIdValue($parent),$childField);

    }

    /**
     * @param $entity
     * @param $value
     * @param null $tableData
     */
    private function setIdValue($entity, $value, $tableData = null) {
        if (is_null($tableData)) {
            $tableData = $this->table;
        }
        $tableData->setPropertyValue($entity, $tableData->getPrimaryKeyField()->getPropertyName(), $value);
    }

    /**
     * @param $tableField TableField
     * @param $value object
     * @param $propertyName string
     * @param $destination object
     * @return mixed|EntityProxy
     */
    private function setPropertyValue($tableField,$value, $propertyName, $destination) {

        if (!is_null($value)) {
            if ($tableField->isNumericType()) {
                settype($value, "float");
            } elseif ($tableField->isBoolType()) {
                settype($value, "boolean");
            } elseif ($tableField->isStringType()) {
                settype($value, "string");
            }
        }

        if ($fkConstraint = $tableField->getForeignKeyConstraint()) {
            if ($fkConstraint->isOneToMany()) {
                $referencedTableData = $fkConstraint->getForeignKeyField()->getTable();
                $entity = $referencedTableData->getEntityInstance();
                $value = new CollectionProxy($destination, $this->daoFactory->getDaoFromEntity($entity), $tableField);
            } else {
                $referencedTableData = $fkConstraint->getForeignKeyField()->getTable();
                $entity = $referencedTableData->getEntityInstance();
                $value = new EntityProxy($destination, $entity, $this->daoFactory->getDaoFromEntity($entity), $tableField);
            }
        }

        if ($this->reflectionEntityClass->hasProperty($propertyName)) {
            $property = $this->reflectionEntityClass->getProperty($propertyName);
            if (!$property->isPublic()) {
                $property->setAccessible(true);
                $property->setValue($destination, $value);
                $property->setAccessible(false);
            } else {
                $property->setValue($destination, $value);
            }
        } else{
            $destination->{$propertyName} = $value;
        }
    }

    /**
     * Creates entity object without invoking constructor and transfers data from
     * stdClass to entityClass
     * @param stdClass $source
     * @param null $destination
     * @return mixed
     */
    private function cast($source, $destination = null) {
        $sourceReflection = new ReflectionObject($source);

        if ($destination == null) {
            $destination = $this->reflectionEntityClass->newInstanceWithoutConstructor();
        }

        $entityFields = $this->table->getEntityFields();
        foreach ($entityFields as $field) {
            $propertyName = $field->getPropertyName();
            $fieldName = $field->getFieldName();

            $value = null;
            if ($sourceReflection->hasProperty($fieldName)) {
                $sourceProperty = $sourceReflection->getProperty($fieldName);
                $value = $sourceProperty->getValue($source);
            }
            $this->setPropertyValue($field, $value, $propertyName, $destination);
        }
        $this->setCache($destination);
        return $destination;
    }

    /**
     * @param $id
     * @return null|object|stdClass
     */
    private function findEntity($id) {
        $query = $this->table->getFindSQL($id);
        $result = $this->runQuery($query);

        if (!$result || mysqli_num_rows($result) == 0) {
            return null;
        }

        $obj = $result->fetch_object();

        return $obj;
    }

    /**
     * @param $query
     * @return bool|\mysqli_result
     * @throws \Exception
     */
    protected function runQuery($query) {
        $this->logger->info("Ran query: " . $query);
        $mysqli = $this->database->getMysqli();
        $result = $mysqli->query($query);
        if (!$result) {
            throw new \RuntimeException("query failed: $query, error no: " . $mysqli->errno . ", error:" . $mysqli->error);
        }
        return $result;
    }

    /**
     * Class name for entity this dao class handles.
     * @return string
     */
    public abstract function getEntityClass();


    /**
     * @param $daoFactory DaoFactory
     * @return self
     */
    public static function getInstance($daoFactory){
        return $daoFactory->getDao(get_called_class());
    }

    /**
     * @param $parent
     * @return mixed
     */
    private function persistReferences($parent) {
        foreach ($this->getForeignKeyReferences($parent) as $data) {
            list($referencedField, $referencedValue) = $data;
            if(is_array($referencedValue)){
                foreach($referencedValue as $refVal){
                    $this->persistReference($parent, $refVal, $referencedField);
                }
            }else{
                $this->persistReference($parent, $referencedValue, $referencedField);
            }
        }
    }

    /**
     * @param $obj
     * @param $parentId
     * @param $fkField TableField
     * @return mixed
     */
    private function persistEntity($obj,$parentId = null, $fkField = null) {
        $query = $this->table->getCreateSQL($obj, $parentId, $fkField);
        $this->runQuery($query);

        if ($this->table->getPrimaryKeyField()->isAutoIncrement()) {
            $insertId = $this->database->getMysqli()->insert_id;
            $this->setIdValue($obj, $insertId);
        }
        $this->setVersion($obj);
        $this->setCache($obj);

        $this->persistReferences($obj);

        return $this->getIdValue($obj);
    }


    /**
     * @param $obj
     * @return array
     */
    private function getForeignKeyReferences($obj) {
        $references = array();

        foreach ($this->table->getIncomingForeignKeyFields() as $referencedField) {
            if ($this->table->hasPropertyValue($obj, $referencedField)) {
                $referencedValue = $this->table->getPropertyValue($obj, $referencedField);
                $references[] = array($referencedField,$referencedValue);
            }
        }
        return $references;
    }


    /**
     * @param $obj
     * @return TableField[]
     */
    private function getOneToManyReferences($obj) {
        $oneToManyReferences = array();
        foreach ($this->table->getIncomingForeignKeyFields() as $referencedField) {
            if ($fkConstraint = $referencedField->getForeignKeyConstraint()) {
                if($fkConstraint->isOneToMany()){
                    $oneToManyReferences[] = $referencedField;
                }
            }
        }
        return $oneToManyReferences;
    }

    private function setCache($obj) {
        $obj->{self::CACHE} = clone $obj;
    }

    private function getCache($obj){
        return $obj->{self::CACHE};
    }

    /**
     * @param $parentEntity object
     * @param $parentField TableField
     * @return bool
     */
    private function handleOneToManyChanges($parentEntity, $parentField) {

        $a = $this->table->getPropertyValue($parentEntity,$parentField);
        if($a == null){
            $a = array();
        }
        foreach (array_keys($a) as $key){
            $child = $a[$key];
            unset($a[$key]);
            if($child instanceof EntityProxy){
                $child = $child->getDelegate();
            }
            $id = $this->daoFactory->getDaoFromEntity($child)->getIdValue($child);
            if($id == null){
                $id = uniqid(self::TRANSIENT_PREFIX);
            }
            $a[$id] = $child;
        }

        $b = $this->table->getPropertyValue($this->getCache($parentEntity),$parentField);
        if($b == null){
            $b = array();
        }
        foreach (array_keys($b) as $key){
            $child = $b[$key];
            unset($b[$key]);
            if($child instanceof EntityProxy){
                $child = $child->getDelegate();
            }
            $id = $this->daoFactory->getDaoFromEntity($child)->getIdValue($child);
            if($id == null){
                $id = uniqid(self::TRANSIENT_PREFIX);
            }
            $b[$id] = $child;
        }

        $valuesAdded = array_diff_key($a,$b);
        $valuesDeleted = array_diff_key($b,$a);

        foreach($valuesAdded as $value){
            $this->persistReferencedEntity($parentEntity,$value,$parentField);
        }

        foreach ($valuesDeleted as $value){
            $dao = $this->daoFactory->getDaoFromEntity($value);
            $dao->delete($value);
        }
    }

    /**
     * @param $parent
     * @param $referencedValue
     * @param $referencedField
     */
    private function persistReference($parent, $referencedValue, $referencedField) {
        if ($this->isTransient($referencedValue)) {
            $this->persistReferencedEntity($parent, $referencedValue, $referencedField);
        } else {
            //FIXME handle this case?
            //throw new \RuntimeException("STUB");
        }
    }
}