<?php

namespace SimphpleOrm\Dao;


class CollectionProxy extends \ArrayObject implements Proxy {

    /**
     * @var TableField
     */
    private $field;

    /**
     * Is initialized
     * @var bool
     */
    private $initialized = false;

    /**
     * In the process of initialization
     * @var bool
     */
    private $initializing = false;

    /**
     * @var object
     */
    private $owner;

    /**
     * @var Dao
     */
    private $dao;

    /**
     * @var array
     */
    private $collection;

    /**
     * @param $owner
     * @param Dao $dao
     * @param TableField $field
     */
    function __construct($owner, Dao $dao, $field) {
        parent::__construct();
        $this->owner = $owner;
        $this->dao = $dao;
        $this->reflectionClass = new \ReflectionClass($dao->getEntityClass());
        $this->field = $field;
    }

    public function isInitialized() {
        return $this->initialized;
    }
    
    public function initialize(){
        if(!$this->isInitialized() && !$this->initializing){
            $this->initializing = true;
            $this->dao->setCache($this->owner);
            $table = $this->field->getTable();
            $id = $table->getPropertyValue($this->owner,$table->getPrimaryKeyField());
            $this->collection = array();
            ProxyUtils::swap($this->owner,$this->collection,$this->field, $this->dao);
            $this->dao->__refreshByFK($this->collection,$id,$this->field->getForeignKeyConstraint());
            $cacheCopy = $this->getArrayCopy();
            ProxyUtils::swap($this->owner->{Dao::CACHE},$cacheCopy,$this->field, $this->dao);
            $this->initialized = true;
            $this->initializing = false;
        }
    }

    public function offsetExists($index) {
        $this->initialize();
        return array_key_exists($index,$this->collection);
    }

    public function offsetGet($index) {
        $this->initialize();
        return $this->collection[$index];
    }

    public function offsetSet($index, $newval) {
        $this->initialize();
        if($index === null){
            $this->append($newval);
        }else{
            $this->collection[$index] = $newval;
        }
    }

    public function offsetUnset($index) {
        $this->initialize();
        unset($this->collection[$index]);
    }

    public function append($value) {
       $this->initialize();
       $this->collection[] = $value;
    }

    public function getArrayCopy() {
        $this->initialize();
        $array = array();
        foreach($this->collection as $key => $val){
            $array[$key] = $val;
        }
        return $array;
    }

    public function count() {
        $this->initialize();
        return sizeof($this->collection);
    }

    public function asort() {
        $this->initialize();
        asort($this->collection);
    }

    public function ksort() {
        $this->initialize();
        ksort($this->collection);
    }

    public function uasort($cmp_function) {
        $this->initialize();
        uasort($this->collection,$cmp_function);
    }

    public function uksort($cmp_function) {
        $this->initialize();
        uksort($this->collection,$cmp_function);
    }

    public function natsort() {
        $this->initialize();
        natsort($this->collection);
    }

    public function natcasesort() {
        $this->initialize();
        natcasesort($this->collection);
    }

    public function getIterator() {
       $this->initialize();
       return new \ArrayIterator($this->collection);
    }

    /**
     * (PHP 5 &gt;= 5.4.0)<br/>
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     */
    function jsonSerialize() {
        $this->initialize();
        return $this->collection;
    }

    public function getArray(){
        return $this->collection;
    }
}