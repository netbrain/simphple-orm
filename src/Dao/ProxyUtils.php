<?php

namespace SimphpleOrm\Dao;


class ProxyUtils {
    /**
     * @param $owner object
     * @param $delegate object|array
     * @param $field TableField
     * @param $dao Dao
     */
    public static function swap($owner,$delegate,$field, $dao){
        $dao->setCache($owner);
        $reflectedOwner = new \ReflectionClass($owner);
        $property = $reflectedOwner->getProperty($field->getPropertyName());
        $property->setAccessible(true);
        $property->setValue($owner,$delegate);
    }
} 