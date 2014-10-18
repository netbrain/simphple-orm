<?php

namespace SimphpleOrm\Dao;

class EntityProxy {

    private $delegate;
    private $tableData;
    private $handledTables;
    private $initialized;

    function __construct($delegate, Dao $dao, TableData $tableData, $initialized = true) {
        $this->initialized = $initialized;
        $this->delegate = $delegate;
        $this->dao = $dao;
        $this->reflectionClass = new \ReflectionClass($this->delegate);
        $this->tableData = $tableData;
        $this->handledTables = array();
    }

    function __call($name, $arguments) {
        if (!method_exists($this->delegate, $name)) {
            throw new \Exception("Method '$name' doesn't exist on class: " . get_class($this->delegate));
        }

        if (!$this->initialized) {
            $this->initialize();
        }

        if (strpos($name, 'get') === 0) {
            $propertyName = lcfirst(substr($name, 3));
            $tableField = $this->tableData->getTableFieldByPropertyName($propertyName);

            if ($tableField->isReference()) {
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

    public function initialize() {
        $this->dao->refresh($this->delegate);
        $this->initialized = true;
    }

    /**
     * @param $name string
     * @return mixed
     */
    public function getDelegatePropertyValue($name) {
        $property = $this->reflectionClass->getProperty($name);
        $property->setAccessible(true);
        $value = $property->getValue($this->delegate);
        $property->setAccessible(false);
        return $value;
    }

    private function isInitialized() {
        return $this->initialized;
    }

    /**
     * @return mixed
     * FIXME should this also initialize the entity?
     */
    public function getDelegate() {
        return $this->delegate;
    }
} 