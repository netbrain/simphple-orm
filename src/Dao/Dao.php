<?php

namespace SimphpleOrm\Dao;

use Logger;
use ReflectionClass;
use ReflectionObject;
use stdClass;

abstract class Dao {

    const CACHE = '_cache';
    const TYPE = '_type';
    const ID = '_id';
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

    public function dropTable(){
        $this->runQuery($this->table->getDropTableSQL());
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
     * @param $query
     * @param $parameters
     * @return array|mixed
     */
    protected function findBySQL($query, $parameters = array()) {
        $parameters = array_map(function($e){
            return mysqli_real_escape_string($this->database->getMysqli(),$e);
        },$parameters);
        array_unshift($parameters,$query);

        $query = call_user_func_array('sprintf', $parameters);
        $result = $this->runQuery($query);

        if (!$result || mysqli_num_rows($result) == 0) {
            return null;
        }else if(mysqli_num_rows($result) == 1){
            $obj = $result->fetch_object();
            return $this->cast($obj);
        }else{
            $entities = array();
            while ($obj = $result->fetch_object()) {
                $entities[] = $this->cast($obj);
            }
            return $entities;
        }
    }

    /**
     * Deletes an entity from the database
     * @param $entityOrId
     * @return null|object
     */
    public function delete($entityOrId) {
        $query = is_object($entityOrId) ? $this->table->getDeleteSQL($entityOrId) : $this->table->getDeleteByIdSQL($entityOrId);
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

        if($this->isDirty($obj)) {
            $query = $this->table->getUpdateSQL($obj);
            $this->runQuery($query);

            if (!mysqli_affected_rows($this->database->getMysqli()) > 0) {
                throw new \RuntimeException("No rows updated by update query! either row has been deleted or another version was committed");
            } else {
                //increment version
                $obj->{Dao::VERSION}++;
            }
        }
        $this->setCache($obj);
    }

    /**
     * @param $entityOrCollection
     * @return array|mixed
     */
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
                    if($entityOrCollection instanceof CollectionProxy){
                        $entityOrCollection = $entityOrCollection->getArrayCopy();
                    }else if($entityOrCollection instanceof EntityProxy){
                        $entityOrCollection = $entityOrCollection->getDelegate();
                    }
                }
            }
        }
        return $entityOrCollection;
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
     * @return array|mixed
     */
    public function initializeDeep($entity){
        $output = $this->initialize($entity);
        foreach($this->table->getIncomingForeignKeyFields() as $field){
            $value = $this->table->getPropertyValue($entity,$field);
            if($value == null){
                continue;
            }
            $this->initializeDeep($value);
        }
        return $output;
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
        $this->persistReferences($obj);
        $this->setCache($obj);
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

    public function setCache($obj) {
        $cache = $this->createCache($obj);
        if(isset($cache->{self::CACHE})){
            unset($cache->{self::CACHE});
        }
        $obj->{self::CACHE} = $cache;

    }

    private function getCache($obj){
        return $obj->{self::CACHE};
    }

    private function createCache($entity) {
        if (!is_object($entity)) {
            throw new \InvalidArgumentException();
        }
        $reflectedEntity = new \ReflectionClass($entity);
        $entityCopy = $reflectedEntity->newInstanceWithoutConstructor();

        $properties = $reflectedEntity->getProperties();
        foreach ($properties as $property) {
            $property->setAccessible(true);
            $property->setValue($entityCopy,$this->getCacheValue($property->getValue($entity)));
        }

        $dynamicProperties = get_object_vars($entity);
        foreach ($dynamicProperties as $name => $dynamicProperty) {
            $entityCopy->{$name} = $this->getCacheValue($dynamicProperty);
        }
        return $entityCopy;

    }

    /**
     * @param $value
     * @return mixed
     */
    private function getCacheValue($value) {
        if (is_object($value)) {
            if ($value instanceof CollectionProxy) {
                return array();
            } else if ($value instanceof EntityProxy) {
                return null;
            } else {
                if($this->isTransient($value)){
                    return spl_object_hash($value);
                }else{
                    return (object) array(
                        Dao::TYPE => get_class($value),
                        DAO::ID => $this->daoFactory->getDaoFromEntity($value)->getIdValue($value)
                    );
                }
            }
        } else if (is_array($value)) {
            $arrayCopy = array();
            foreach($value as $k => $v){
                $arrayCopy[$k] = $this->getCacheValue($v);
            }
            return $arrayCopy;
        } else {
            return $value;
        }
    }

    /**
     * @param $parentEntity object
     * @param $parentField TableField
     * @return bool
     */
    private function handleOneToManyChanges($parentEntity, $parentField) {

        $newEntityValues = $this->table->getPropertyValue($parentEntity,$parentField);
        if($newEntityValues == null || $newEntityValues instanceof CollectionProxy){
            $newEntityValues = array();
        }

        $buffer = array();
        foreach ($newEntityValues as $key => $child){
            $id = $this->daoFactory->getDaoFromEntity($child)->getIdValue($child);
            if($id == null){
                $id = uniqid(self::TRANSIENT_PREFIX);
            }
            $buffer[$id] = $child;
        }
        $newEntityValues = $buffer;

        $oldEntityValues = $this->table->getPropertyValue($this->getCache($parentEntity),$parentField);
        if($oldEntityValues == null){
            $oldEntityValues = array();
        }

        $buffer = array();
        foreach ($oldEntityValues as $key => $child){
            unset($oldEntityValues[$key]);
            $buffer[$child->{DAO::ID}] = $child;
        }
        $oldEntityValues = $buffer;

        $valuesAdded = array_diff_key($newEntityValues,$oldEntityValues);
        $valuesDeleted = array_diff_key($oldEntityValues,$newEntityValues);

        foreach($valuesAdded as $value){
            $this->persistReferencedEntity($parentEntity,$value,$parentField);
        }

        foreach ($valuesDeleted as $value){
            $dao = $this->daoFactory->getDaoFromEntity($value->{DAO::TYPE});
            $dao->delete($value->{DAO::ID});
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

    /**
     * @param $obj
     * @return bool
     */
    public function isDirty($obj) {
        $clone = $this->createCache($obj);
        if(isset($clone->{Dao::CACHE})){
            unset($clone->{Dao::CACHE});
        }
        return serialize($clone) != serialize($this->getCache($obj));
    }
}