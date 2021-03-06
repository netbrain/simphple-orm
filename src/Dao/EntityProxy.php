<?php

namespace SimphpleOrm\Dao;

class EntityProxy implements Proxy{

    private $delegate;
    private $field;

    function __construct($owner,$delegate, Dao $dao, TableField $field) {
        $this->owner = $owner;
        $this->delegate = $delegate;
        $this->dao = $dao;
        $this->reflectionClass = new \ReflectionClass($dao->getEntityClass());
        $this->field = $field;
        }

    function __get($name) {
        if($name == Dao::CACHE || $name == Dao::VERSION){
            $this->initialize();
            return $this->getDelegate()->{$name};
        }else{
            $property = $this->reflectionClass->getProperty($name);
            $value = $property->getValue($this->delegate);
            return $value;
        }
    }


    function __call($name, $arguments) {
        if (!method_exists($this->delegate, $name)) {
            throw new \Exception("Method '$name' doesn't exist on class: " . get_class($this->delegate));
        }

        if(!$this->isInitialized()){
            $this->initialize();
        }

        if (strpos($name, 'get') === 0) {
            $propertyName = lcfirst(substr($name, 3));
            $tableField = $this->field->getTable()->getTableFieldByPropertyName($propertyName);

            if ($tableField->isForeignKey()) {
                /**
                 * @var $referencedEntity EntityProxy
                 */
                $referencedEntity = $this->getDelegatePropertyValue($propertyName);
                if ($referencedEntity != null && !$referencedEntity->isInitialized()) {
                    $referencedEntity->initialize();
                }
            }
        }
        return call_user_func_array(array($this->delegate, $name), $arguments);
    }

    /**
     * @param $name string
     * @return mixed
     */
    private function getDelegatePropertyValue($name) {
        $property = $this->reflectionClass->getProperty($name);
        $property->setAccessible(true);
        $value = $property->getValue($this->delegate);
        $property->setAccessible(false);
        return $value;
    }

    public function isInitialized() {
        if(!isset($this->delegate->{Dao::VERSION})){
            return false;
        }
        return true;
    }


    public function initialize(){
        if(!$this->isInitialized()){
            $table = $this->field->getTable();
            $id = $table->getPropertyValue($this->owner,$table->getPrimaryKeyField());
            $this->dao->__refreshByFK($this->delegate,$id,$this->field->getForeignKeyConstraint());
            ProxyUtils::swap($this->owner,$this->delegate,$this->field,$this->dao);
        }
    }

    /**
     * @return mixed
     */
    public function getDelegate() {
        return $this->delegate;
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
        return $this->getDelegate();
    }
}