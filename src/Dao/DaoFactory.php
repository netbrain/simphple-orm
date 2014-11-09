<?php

namespace SimphpleOrm\Dao;

class DaoFactory {


    /**
     * @var Dao[]
     */
    private $daos = array();

    /**
     * @var Dao[]
     */
    private $daosByEntity = array();

    /**
     * @var Database
     */
    private $database;

    /**
     * @param Database $database
     */
    public  function __construct(Database $database) {
        $this->database = $database;
    }

    /**
     * @param $class
     *
     * @return bool
     */
    private function isDao($class) {
        return is_subclass_of($class, "\\SimphpleOrm\\Dao\\Dao");
    }

    /**
     * @param $class
     */
    private function registerDao($class) {
        if (!array_key_exists($class, $this->daos)) {
            if (!$this->isDao($class)) {
                throw new \RuntimeException("This is not a dao class");
            }
            $dao = $this->createDao($class);
            $this->daos[get_class($dao)] = $dao;
            $this->daosByEntity[ltrim($dao->getEntityClass(), '\\')] = $dao;
        }
    }

    /**
     * @param $class
     *
     * @return Dao
     */
    private function createDao($class) {
        return (new \ReflectionClass($class))->newInstance($this->database,$this);
    }


    /**
     * @deprecated Use Dao::getInstance() instead.
     * @param $class
     * @throws \Exception
     * @return Dao
     */
    public function getDao($class) {
        $class = ltrim($class, '\\');
        if ($this->isDao($class)) {
            $this->registerDao($class);
        }
        if (array_key_exists($class, $this->daos)) {
            return $this->daos[$class];
        }
        throw new \Exception("No DAO class found for '$class'");
    }

    /**
     * @param $class string|object
     * @return null|Dao
     */
    public function getDaoFromEntity($class) {
        if (!is_string($class)) {
            $class = get_class($class);
        }
        $class = ltrim($class, '\\');
        if (array_key_exists($class, $this->daosByEntity)) {
            return $this->daosByEntity[$class];
        }
        throw new \RuntimeException("cannot fetch dao from unknown entity: $class");
    }

    public function createTables() {
        foreach($this->daos as $dao){
            $dao->createTable();
        }
    }
}