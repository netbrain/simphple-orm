<?php


namespace SimphpleOrm\Test;


use SimphpleOrm\Dao\Dao;

class NotAnnotatedTestDao extends Dao {

    public function getEntityClass() {
        return "\\SimphpleOrm\\Test\\NotAnnotatedTest";
    }
}
