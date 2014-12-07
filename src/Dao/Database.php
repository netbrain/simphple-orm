<?php

namespace SimphpleOrm\Dao;

use Exception;
use ReflectionClass;
use ReflectionProperty;

class Database {

    /**
     * @var \mysqli
     */
    private $mysqli;

    /**
     * @var Table[]
     */
    private $tables = array();

    /**
     * @var array map of child->parent relations
     */
    private $tableReferences = array();

    /**
     * @var array map of parent->child relations
     */
    private $tableReferencesInverse = array();

    /**
     * @param $mysqli
     */
    public function __construct(\mysqli $mysqli) {
        $this->mysqli = $mysqli;
    }

    /**
     * Builds a mysqli table metadata from an entity class
     * @param $class string
     * @return Table
     */
    public function build($class) {
        $class = $this->trimClassName($class);
        if (!is_string($class)) {
            throw new \InvalidArgumentException("argument needs to be a class string");
        }
        if (array_key_exists($class, $this->tables)) {
            return $this->tables[$class];
        }

        $reflectedEntity = new ReflectionClass($class);
        $table = new Table($reflectedEntity,$this);
        $this->tables[$class] = $table;
        $table->setName($this->getTableName($reflectedEntity));
        $this->addTableFields($reflectedEntity, $table);
        return $table;
    }

    private function getTableName(ReflectionClass $reflectedEntity) {
        $entityAnnotations = $this->getAnnotationsFromCommentBlock($reflectedEntity->getDocComment());
        if (property_exists($entityAnnotations, 'table')) {
            return $entityAnnotations->table;
        }

        $nameParts = explode('\\', $reflectedEntity->getName());
        return end($nameParts);
    }

    /**
     * @param $doc
     * @return \stdClass
     */
    private function getAnnotationsFromCommentBlock($doc) {
        $annotations = array();
        preg_match_all('#@(.*?)\n#s', $doc, $annotations);
        return $this->convertAnnotationsToObject($annotations[1]);
    }

    private function convertAnnotationsToObject($annotations) {
        $obj = new \stdClass();
        foreach ($annotations as $annotation) {
            $annotation = explode(' ', $annotation, 2);
            $obj->{$annotation[0]} = isset($annotation[1]) ? $annotation[1] : null;
        }
        return $obj;
    }

    /**
     * @param $reflectedEntity ReflectionClass
     * @param $table Table
     */
    private function addTableFields($reflectedEntity, $table) {
        foreach ($reflectedEntity->getProperties() as $property) {
            $this->addTableField($property, $table);
        }
    }

    /**
     * @param \ReflectionProperty $property
     * @param $table Table
     * @throws \Exception
     */
    private function addTableField(ReflectionProperty $property, $table) {
        $propertyAnnotations = $this->getAnnotationsFromCommentBlock($property->getDocComment());
        if (!$this->isPropertyTransient($propertyAnnotations)) {
            if (!$this->isPropertyPrimitive($propertyAnnotations)) {
                if ($this->isOneToOne($property, $propertyAnnotations)) {
                    $this->addOneToOneReference($property, $table, $propertyAnnotations);
                } else {
                    $this->addOneToManyReference($property, $table, $propertyAnnotations);
                }
            } else {
                $this->addFieldFromAnnotations($property, $propertyAnnotations, $table);
            }
        }
    }

    /**
     * @param $propertyAnnotations
     * @return bool
     */
    private function isPropertyTransient($propertyAnnotations) {
        return property_exists($propertyAnnotations, 'transient');
    }

    private function isPropertyPrimitive($propertyAnnotations) {
        if (property_exists($propertyAnnotations, 'fieldType')) {
            return true;
        } elseif (property_exists($propertyAnnotations, 'var')) {
            switch ($propertyAnnotations->var) {
                case 'string':
                case 'bool':
                case 'boolean':
                case 'int':
                case 'integer':
                case 'float':
                    return true;
                default:
                    return false;
            }
        }
        throw new Exception("Field needs to have a type, either through the @var annotation or @fieldType");
    }

    private function isOneToOne(ReflectionProperty $property, $propertyAnnotations) {
        $namespace = $property->getDeclaringClass()->getNamespaceName();
        $type = $namespace . '\\' . $propertyAnnotations->var;
        if (strrpos($type, '[]') === strlen($type) - 2) {
            return false;
        }
        return true;
    }

    /**
     * @param ReflectionProperty $property
     * @param $table Table
     * @param $propertyAnnotations
     * @throws \Exception
     */
    private function addOneToOneReference(ReflectionProperty $property, $table, $propertyAnnotations) {
        $namespace = $property->getDeclaringClass()->getNamespaceName();
        $type = $namespace . '\\' . $propertyAnnotations->var;

        $otherTable = $this->getReferencedTableFromEntityClass($table, $type);
        $fieldName = 'parent_'.$this->getFieldName($property, $propertyAnnotations).'_fk';
        $propertyName = $property->getName();
        $type = $table->getPrimaryKeyField()->getType();

        $fkField = new TableField($fieldName, $propertyName, $type, TableField::UNIQUE, null);
        $otherTable->addField($fkField);

        $foreignKeyConstraint = ForeignKeyConstraint::createOneToOneConstraint($fkField,$table->getPrimaryKeyField());
        $table->addForeignKeyConstraint($foreignKeyConstraint);
        $otherTable->addForeignKeyConstraint($foreignKeyConstraint);
    }

    /**
     * @param ReflectionProperty $property
     * @param $table
     * @param $propertyAnnotations
     * @throws \Exception
     */
    private function addOneToManyReference(ReflectionProperty $property, Table $table, $propertyAnnotations) {
        $namespace = $property->getDeclaringClass()->getNamespaceName();
        $type = $namespace . '\\' . $propertyAnnotations->var;
        $type = substr($type, 0, strrpos($type, '[]'));

        $otherTable = $this->getReferencedTableFromEntityClass($table, $type);
        $fieldName = 'parent_'.$this->getFieldName($property, $propertyAnnotations).'_fk';
        $propertyName = $property->getName();
        $type = $table->getPrimaryKeyField()->getType();


        $fkField = new TableField($fieldName, $propertyName, $type, 0, null, $table);
        $otherTable->addField($fkField);

        $foreignKeyConstraint = ForeignKeyConstraint::createOneToManyConstraint($fkField,$table->getPrimaryKeyField());
        $table->addForeignKeyConstraint($foreignKeyConstraint);
        $otherTable->addForeignKeyConstraint($foreignKeyConstraint);
    }

    /**
     * @param ReflectionProperty $property
     * @param $propertyAnnotations
     * @return string
     */
    private function getFieldName(ReflectionProperty $property, $propertyAnnotations) {
        if (property_exists($propertyAnnotations, 'field')) {
            return $propertyAnnotations->field;
        }
        return $property->getName();
    }

    /**
     * @param $propertyAnnotations
     * @return bool
     */
    private function isPropertyNotNull($propertyAnnotations) {
        return property_exists($propertyAnnotations, 'notNull');
    }

    /**
     * @param ReflectionProperty $property
     * @param $table Table
     * @param $propertyAnnotations
     */
    private function addFieldFromAnnotations(ReflectionProperty $property, $propertyAnnotations, $table) {
        $fieldName = $this->getFieldName($property, $propertyAnnotations);
        $propertyName = $property->getName();
        $type = $this->getFieldType($propertyAnnotations);
        $flags = 0;

        if ($this->isPropertyPrimaryKey($propertyAnnotations)) {
            $flags |= TableField::PRIMARY;
        }

        if ($this->isPropertyAutoIncrement($propertyAnnotations)) {
            $flags |= TableField::AUTO_INCREMENT;
        }

        if ($this->isPropertyUnique($propertyAnnotations)) {
            $flags |= TableField::UNIQUE;
        }

        if ($this->isPropertyNotNull($propertyAnnotations)) {
            $flags |= TableField::NOT_NULL;
        }

        $table->addField(new TableField($fieldName, $propertyName, $type, $flags));
    }

    /**
     * @param $propertyAnnotations
     * @throws \Exception
     * @return string
     */
    private function getFieldType($propertyAnnotations) {
        if (property_exists($propertyAnnotations, 'fieldType')) {
            return $propertyAnnotations->fieldType;
        } elseif (property_exists($propertyAnnotations, 'var')) {
            switch ($propertyAnnotations->var) {
                case 'string':
                    return 'VARCHAR(255)';
                case 'bool':
                case 'boolean':
                    return 'BOOL';
                case 'int':
                case 'integer':
                    return 'INT';
                case 'float':
                    return 'FLOAT';
                default:
                    throw new Exception('Unknown type: ' . $propertyAnnotations->var . ', cannot match to a mysqli primitive.');
            }
        }
        throw new Exception("Field needs to have a type, either through the @var annotation or @fieldType");
    }

    /**
     * @param $propertyAnnotations
     * @return bool
     */
    private function isPropertyPrimaryKey($propertyAnnotations) {
        return property_exists($propertyAnnotations, 'id');
    }

    /**
     * @param $propertyAnnotations
     * @return bool
     */
    private function isPropertyAutoIncrement($propertyAnnotations) {
        return $this->isPropertyPrimaryKey($propertyAnnotations) && !property_exists($propertyAnnotations, 'noAuto');
    }

    /**
     * @param $propertyAnnotations
     * @return bool
     */
    private function isPropertyUnique($propertyAnnotations) {
        return property_exists($propertyAnnotations, 'unique');
    }

    /**
     * @param $table Table
     * @param $type
     * @return Table
     * @throws Exception
     */
    private function getReferencedTableFromEntityClass($table, $type) {
        if (!class_exists($type)) {
            throw new Exception("Could not find entity reference class: " . $type . " regarding model: " . $table->getEntityClassName());
        }
        $otherTable = $this->build($type);

        $this->addReferencedTable($table->getEntityClassName(),$type);
        return $otherTable;
    }

    /**
     * @param $parent string
     * @param $child string
     */
    private function addReferencedTable($parent,$child) {
        $parent = $this->trimClassName($parent);
        $child = $this->trimClassName($child);
        if(!array_key_exists($child,$this->tableReferences)){
            $this->tableReferences[$child] = array();
        }
        $this->tableReferences[$child][$parent] = null;


        if(!array_key_exists($parent,$this->tableReferencesInverse)){
            $this->tableReferencesInverse[$parent] = array();
        }
        $this->tableReferencesInverse[$parent][$child] = null;
    }

    /**
     * @param $class
     * @return string
     */
    private function trimClassName($class) {
        return ltrim($class, '\\');
    }

    /**
     * @return \mysqli
     */
    public function getMysqli() {
        return $this->mysqli;
    }

    /**
     * @return array
     */
    public function getTableReferences() {
        return $this->tableReferences;
    }

    /**
     * @return array
     */
    public function getTableReferencesInverse() {
        return $this->tableReferencesInverse;
    }

    /**
     * @return Table[]
     */
    public function getTables() {
        return $this->tables;
    }


} 