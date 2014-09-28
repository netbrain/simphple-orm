<?php


namespace SimphpleOrm\Test;

use SimphpleOrm\Dao\Dao;

class AnnotatedTestDao extends Dao {

    public function getEntityClass() {
        return "\\SimphpleOrm\\Test\\AnnotatedTest";
    }
}