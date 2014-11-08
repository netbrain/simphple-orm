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


    public function initialize(){
        if(!$this->isInitialized()){
            $this->initializing = true;
            $table = $this->field->getTable();
            $id = $table->getPropertyValue($this->owner,$table->getPrimaryKeyField());
            $this->dao->__refreshByFK($this,$id,$this->field->getForeignKeyConstraint());
            $this->initialized = true;
            $this->initializing = false;
            ProxyUtils::swap($this->owner,$this->getArrayCopy(),$this->field);
        }
    }

    public function offsetExists($index) {
        $this->initializeIfNotAlreadyStarted();
        parent::offsetExists($index);
    }

    public function offsetGet($index) {
        $this->initializeIfNotAlreadyStarted();
        parent::offsetGet($index);
    }

    public function offsetSet($index, $newval) {
        $this->initializeIfNotAlreadyStarted();
        parent::offsetSet($index, $newval);
    }

    public function offsetUnset($index) {
        $this->initializeIfNotAlreadyStarted();
        parent::offsetUnset($index);
    }

    public function append($value) {
        $this->initializeIfNotAlreadyStarted();
        parent::append($value);
    }

    public function getArrayCopy() {
        $this->initializeIfNotAlreadyStarted();
        return parent::getArrayCopy();
    }

    public function count() {
        $this->initializeIfNotAlreadyStarted();
        return parent::count();
    }

    public function asort() {
        $this->initializeIfNotAlreadyStarted();
        parent::asort(); 
    }

    public function ksort() {
        $this->initializeIfNotAlreadyStarted();
        parent::ksort(); 
    }

    public function uasort($cmp_function) {
        $this->initializeIfNotAlreadyStarted();
        parent::uasort($cmp_function); 
    }

    public function uksort($cmp_function) {
        $this->initializeIfNotAlreadyStarted();
        parent::uksort($cmp_function); 
    }

    public function natsort() {
        $this->initializeIfNotAlreadyStarted();
        parent::natsort(); 
    }

    public function natcasesort() {
        $this->initializeIfNotAlreadyStarted();
        parent::natcasesort(); 
    }

    public function getIterator() {
        $this->initializeIfNotAlreadyStarted();
        return parent::getIterator();
    }

    private function initializeIfNotAlreadyStarted() {
        if (!$this->initialized && !$this->initializing) {
            $this->initialize();
        }
    }

} 