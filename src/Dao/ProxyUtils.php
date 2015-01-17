<?php

namespace SimphpleOrm\Dao;


use Closure;

class ProxyUtils {
    /**
     * @param $owner object
     * @param $delegate object|array
     * @param $field TableField
     * @param $dao Dao
     */
    public static function swap($owner,&$delegate,$field, $dao){
        $setValue = Closure::bind(function($owner) use  ($field,&$delegate){
           $owner->{$field->getPropertyName()} = &$delegate;
        }, null, $owner);
        $setValue($owner);
    }
} 