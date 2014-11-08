<?php

namespace SimphpleOrm\Dao;


class ProxyUtils {
    /**
     * @param $owner object
     * @param $delegate object|array
     * @param $field TableField
     */
    public static function swap($owner,$delegate,$field){
        $reflectedOwner = new \ReflectionClass($owner);
        $property = $reflectedOwner->getProperty($field->getPropertyName());

        if(!$property->isPublic()){
            $property->setAccessible(true);
        }
        $property->setValue($owner,$delegate);
        $cache = $delegate;
        if(is_object($cache)){
            $cache = clone $cache;
        }
        $property->setValue($owner->{Dao::CACHE},$cache);
        if(!$property->isPublic()){
            $property->setAccessible(false);
        }

    }
} 