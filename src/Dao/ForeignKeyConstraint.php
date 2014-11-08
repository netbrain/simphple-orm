<?php

namespace SimphpleOrm\Dao;

class ForeignKeyConstraint {
    const ONE_TO_ONE = 1;
    const ONE_TO_MANY = 2;

    private $foreignKeyField;
    private $primaryKeyField;

    private function __construct($foreignKeyField, $primaryKeyField, $type) {
        $this->foreignKeyField = $foreignKeyField;
        $this->primaryKeyField = $primaryKeyField;
        $this->type = $type;
    }

    public static function createOneToOneConstraint($foreignKeyField, $primaryKeyField){
        return new ForeignKeyConstraint($foreignKeyField,$primaryKeyField, self::ONE_TO_ONE);
    }

    public static function createOneToManyConstraint($foreignKeyField, $primaryKeyField){
        return new ForeignKeyConstraint($foreignKeyField,$primaryKeyField, self::ONE_TO_MANY);
    }

    /**
     * @return TableField
     */
    public function getForeignKeyField() {
        return $this->foreignKeyField;
    }

    /**
     * @return TableField
     */
    public function getPrimaryKeyField() {
        return $this->primaryKeyField;
    }

    /**
     * @return mixed
     */
    public function isOneToMany() {
        return $this->type === self::ONE_TO_MANY;
    }

    /**
     * @return mixed
     */
    public function isOneToOne() {
        return $this->type === self::ONE_TO_ONE;
    }





} 