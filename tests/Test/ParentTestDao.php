<?php


namespace SimphpleOrm\Test;

use SimphpleOrm\Dao\Dao;

class ParentTestDao extends Dao {
    public function getEntityClass() {
        return "\\SimphpleOrm\\Test\\ParentTest";
    }
}