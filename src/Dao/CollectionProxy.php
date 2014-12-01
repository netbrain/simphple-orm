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
    
    public function initialize($swap = true){
        if(!$this->isInitialized() && !$this->initializing){
            $this->initializing = true;
            $table = $this->field->getTable();
            $id = $table->getPropertyValue($this->owner,$table->getPrimaryKeyField());
            $this->dao->__refreshByFK($this,$id,$this->field->getForeignKeyConstraint());
            $this->initialized = true;
            $this->initializing = false;
            if($swap){
                $this->swap();
            }
        }
    }

    private function swap(){
        ProxyUtils::swap($this->owner,$this->getArrayCopy(),$this->field);
    }

    public function offsetExists($index) {
       $this->initialize();
        parent::offsetExists($index);
    }

    public function offsetGet($index) {
       $this->initialize();
        parent::offsetGet($index);
    }

    public function offsetSet($index, $newval) {
        $this->initialize(false);
        parent::offsetSet($index, $newval);
        $this->swap();
    }

    public function offsetUnset($index) {
       $this->initialize();
        parent::offsetUnset($index);
    }

    public function append($value) {
       $this->initialize();
        parent::append($value);
    }

    public function getArrayCopy() {
       $this->initialize();
        return parent::getArrayCopy();
    }

    public function count() {
       $this->initialize();
        return parent::count();
    }

    public function asort() {
       $this->initialize();
        parent::asort(); 
    }

    public function ksort() {
       $this->initialize();
        parent::ksort(); 
    }

    public function uasort($cmp_function) {
       $this->initialize();
        parent::uasort($cmp_function); 
    }

    public function uksort($cmp_function) {
       $this->initialize();
        parent::uksort($cmp_function); 
    }

    public function natsort() {
       $this->initialize();
        parent::natsort(); 
    }

    public function natcasesort() {
       $this->initialize();
        parent::natcasesort(); 
    }

    public function getIterator() {
       $this->initialize();
        return parent::getIterator();
    }
    
    /**
     * (PHP 5 &gt;= 5.4.0)<br/>
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     */
    function jsonSerialize() {
        return $this->getArrayCopy();
    }
}