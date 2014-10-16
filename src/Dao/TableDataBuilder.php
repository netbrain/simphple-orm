<?php

namespace SimphpleOrm\Dao;

use Exception;
use ReflectionClass;
use ReflectionProperty;

class TableDataBuilder {


    private static $tables = array();

    private function __construct(){}

    /**
     * @param $class string
     * @return TableData
     */
    public static function build($class){
	    if(!is_string($class)){
		    throw new \InvalidArgumentException("argument needs to be a class string");
	    }
        if(array_key_exists($class,self::$tables)){
            return self::$tables[$class];
        }

        $reflectedEntity = new ReflectionClass($class);
        $tableData = new TableData($reflectedEntity);
        self::$tables[$class] = $tableData;
        $tableData->setName(self::getTableName($reflectedEntity));
        self::addTableFields($reflectedEntity, $tableData);
        return $tableData;
    }

    private static function convertAnnotationsToObject($annotations){
        $obj = new \stdClass();
        foreach($annotations as $annotation){
            $annotation = explode(' ',$annotation,2);
            $obj->{$annotation[0]} = isset($annotation[1]) ? $annotation[1] : null;
        }
        return $obj;
    }

    private static function getTableName(ReflectionClass $reflectedEntity){
        $entityAnnotations = self::getAnnotationsFromCommentBlock($reflectedEntity->getDocComment());
        if(property_exists($entityAnnotations,'table')){
            return $entityAnnotations->table;
        }

        $nameParts = explode('\\',$reflectedEntity->getName());
        return end($nameParts);
    }

    /**
     * @param $doc
     * @return \stdClass
     */
    private static function getAnnotationsFromCommentBlock($doc) {
        $annotations = array();
        preg_match_all('#@(.*?)\n#s', $doc, $annotations);
        return self::convertAnnotationsToObject($annotations[1]);
    }

    /**
     * @param ReflectionProperty $property
     * @param $propertyAnnotations
     * @return string
     */
    private static function getFieldName(ReflectionProperty $property, $propertyAnnotations) {
        if (property_exists($propertyAnnotations, 'field')) {
            return $propertyAnnotations->field;
        }
        return $property->getName();
    }

    /**
     * @param $propertyAnnotations
     * @throws \Exception
     * @return string
     */
    private static function getFieldType($propertyAnnotations) {
        if(property_exists($propertyAnnotations, 'fieldType')){
            return $propertyAnnotations->fieldType;
        }elseif(property_exists($propertyAnnotations,'var')){
            switch($propertyAnnotations->var){
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
                    throw new Exception('Unknown type: '.$propertyAnnotations->var.', cannot match to a mysqli primitive.');
            }
        }
        throw new Exception("Field needs to have a type, either through the @var annotation or @fieldType");
    }

    private static function isPropertyPrimitive($propertyAnnotations) {
        if(property_exists($propertyAnnotations, 'fieldType')){
            return true;
        }elseif(property_exists($propertyAnnotations,'var')){
            switch($propertyAnnotations->var){
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

    /**
     * @param $propertyAnnotations
     * @return bool
     */
    private static function isPropertyTransient($propertyAnnotations) {
        return property_exists($propertyAnnotations, 'transient');
    }

    /**
     * @param $propertyAnnotations
     * @return bool
     */
    private static function isPropertyPrimaryKey($propertyAnnotations) {
        return property_exists($propertyAnnotations, 'id');
    }


    /**
     * @param $propertyAnnotations
     * @return bool
     */
    private static function isPropertyAutoIncrement($propertyAnnotations) {
        return self::isPropertyPrimaryKey($propertyAnnotations) && !property_exists($propertyAnnotations,'noAuto');
    }

    /**
     * @param $propertyAnnotations
     * @return bool
     */
    private static function isPropertyNotNull($propertyAnnotations) {
        return property_exists($propertyAnnotations, 'notNull');
    }

    /**
     * @param $propertyAnnotations
     * @return bool
     */
    private static function isPropertyUnique($propertyAnnotations) {
        return property_exists($propertyAnnotations, 'unique');
    }

    /**
     * @param \ReflectionProperty $property
     * @param $tableData
     * @throws \Exception
     */
    private static function addTableField(ReflectionProperty $property, $tableData) {
        $propertyAnnotations = self::getAnnotationsFromCommentBlock($property->getDocComment());
        if (!self::isPropertyTransient($propertyAnnotations)) {
            if(!self::isPropertyPrimitive($propertyAnnotations)){
                if(self::isOneToOne($property,$propertyAnnotations)){
                    self::addOneToOneReference($property, $tableData, $propertyAnnotations);
                }else{
                    self::addOneToManyReference($property, $tableData, $propertyAnnotations);
                }
            }else{
                self::addFieldFromAnnotations($property, $propertyAnnotations,$tableData);
            }
        }
    }

    private static function isOneToOne(ReflectionProperty $property,$propertyAnnotations) {
        $name = self::getFieldName($property,$propertyAnnotations);
        $namespace = $property->getDeclaringClass()->getNamespaceName();
        $type = $namespace.'\\'.$propertyAnnotations->var;
        if(strrpos($type,'[]') === strlen($type)-2){
            return false;
        }
        return true;
    }

    /**
     * @param ReflectionProperty $property
     * @param $tableData
     * @param $propertyAnnotations
     */
    private static function addFieldFromAnnotations(ReflectionProperty $property, $propertyAnnotations, TableData $tableData) {
        $fieldName = self::getFieldName($property, $propertyAnnotations);
        $propertyName = $property->getName();
        $type = self::getFieldType($propertyAnnotations);
        $flags = 0;

        if (self::isPropertyPrimaryKey($propertyAnnotations)) {
            $flags |= TableField::PRIMARY;
        }

        if (self::isPropertyAutoIncrement($propertyAnnotations)) {
            $flags |= TableField::AUTO_INCREMENT;
        }

        if (self::isPropertyUnique($propertyAnnotations)) {
            $flags |= TableField::UNIQUE;
        }

        if (self::isPropertyNotNull($propertyAnnotations)) {
            $flags |= TableField::NOT_NULL;
        }

        $tableData->addField(new TableField($fieldName,$propertyName, $type, $flags));
    }

    /**
     * @param ReflectionProperty $property
     * @param $tableData
     * @param $propertyAnnotations
     * @throws \Exception
     */
    private static function addOneToOneReference(ReflectionProperty $property, $tableData, $propertyAnnotations) {
        $namespace = $property->getDeclaringClass()->getNamespaceName();
        $type = $namespace . '\\' . $propertyAnnotations->var;

        if (!class_exists($type)) {
            throw new Exception("Could not find entity reference class: " . $type);
        }

        $otherTable = TableDataBuilder::build($type);

        $fieldName = self::getFieldName($property, $propertyAnnotations);
        $propertyName = $property->getName();
        $type = $otherTable->getPrimaryKey()->getType();
        $flags = 0;

        if (self::isPropertyNotNull($propertyAnnotations)) {
            $flags |= TableField::NOT_NULL;
        }

        $tableData->addField(new TableField($fieldName,$propertyName, $type, $flags, null, $otherTable));
    }


    /**
     * @param ReflectionProperty $property
     * @param $tableData
     * @param $propertyAnnotations
     * @throws \Exception
     */
    private static function addOneToManyReference(ReflectionProperty $property, TableData $tableData, $propertyAnnotations) {
        $namespace = $property->getDeclaringClass()->getNamespaceName();
        $type = $namespace . '\\' . $propertyAnnotations->var;
        $type = substr($type,0,strrpos($type,'[]'));


        if (!class_exists($type)) {
            throw new Exception("Could not find entity reference class: " . $type);
        }

        $otherTable = TableDataBuilder::build($type);

        $joinTable = new TableData($property->getDeclaringClass());
        $joinTable->setName($tableData->getName().'_'.$otherTable->getName());
        $joinTable->addField(new TableField($joinTable->getName(),$property->getName(),'INT',
            TableField::PRIMARY|
            TableField::AUTO_INCREMENT|
            TableField::NOT_NULL));

        $tableFieldId = clone $tableData->getPrimaryKey();
        $otherFieldId = clone $otherTable->getPrimaryKey();

        $tableFieldId->setFieldName($tableData->getName().'_'.$tableFieldId->getFieldName());
        $otherFieldId->setFieldName($otherTable->getName().'_'.$otherFieldId->getFieldName());

        $tableFieldId->removeFlag(TableField::PRIMARY|TableField::AUTO_INCREMENT);
        $otherFieldId->removeFlag(TableField::PRIMARY|TableField::AUTO_INCREMENT);

        $joinTable->addField($tableFieldId);
        $joinTable->addField($otherFieldId);

        $fieldName = self::getFieldName($property, $propertyAnnotations);
        $propertyName = $property->getName();
        $type = $otherFieldId->getType();
        $flags = 0;

        if (self::isPropertyNotNull($propertyAnnotations)) {
            $flags |= TableField::NOT_NULL;
        }

        $tableFieldId = new TableField($fieldName,$propertyName, $type, $flags, null, $joinTable);
        $tableData->addField($tableFieldId);
    }

    /**
     * @param $reflectedEntity
     * @param $tableData
     */
    private static function addTableFields($reflectedEntity, $tableData) {
        foreach ($reflectedEntity->getProperties() as $property) {
            self::addTableField($property, $tableData);
        }
    }
} 