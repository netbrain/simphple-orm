<?php

namespace SimphpleOrm\Dao;

class DaoFactory {

    /**
     * @var DaoFactory
     */
    private static $instance;

    /**
     * @var Dao[]
     */
    private $daos = array();

    /**
     * @var Dao[]
     */
    private $daosByEntity = array();

    /**
     * @var \mysqli
     */
    private $mysqli;

    private function __construct(\mysqli $mysqli = null) {
        $this->mysqli = $mysqli;
        foreach (get_declared_classes() as $class) {
            if ($this->isDao($class)) {
                $this->registerDao($class);
            }
        }
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
        return (new \ReflectionClass($class))->newInstance($this->mysqli);
    }

    public static function build(\mysqli $mysqli) {
        if (!isset(self::$instance)) {
            self::$instance = new DaoFactory($mysqli);
        }

        return self::$instance;
    }

    /**
     * @deprecated Use Dao::getInstance() instead.
     * @param $class
     * @throws \Exception
     * @return Dao
     */
    public static function getDao($class) {
        $class = ltrim($class, '\\');
        if (self::$instance->isDao($class)) {
            self::$instance->registerDao($class);
        }
        if (array_key_exists($class, self::$instance->daos)) {
            return self::$instance->daos[$class];
        }
        throw new \Exception("No DAO class found for '$class'");
    }

    /**
     * @param $class string|object
     * @return null|Dao
     */
    public static function getDaoFromEntity($class) {
        if (!is_string($class)) {
            $class = get_class($class);
        }
        $class = ltrim($class, '\\');
        if (array_key_exists($class, self::$instance->daosByEntity)) {
            return self::$instance->daosByEntity[$class];
        }
        return null;
    }

    public static function createTables() {
        /**
         * @var $dao Dao
         */
        foreach(self::$instance->daos as $dao){
            $dao->createTable();
        }
    }

    public static function dropTables() {
        /**
         * @var $dao Dao
         */
        foreach(self::$instance->daos as $dao){
            $dao->dropTable();
        }
    }

} 