<?php


namespace SimphpleOrm\Test;

use SimphpleOrm\Dao\Dao;

class ChildTestDao extends Dao {
    public function getEntityClass(){
        return "\\SimphpleOrm\\Test\\ChildTest";
    }
}