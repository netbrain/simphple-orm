<?php

namespace SimphpleOrm\Test;


use SimphpleOrm\Dao\Dao;

class ReferenceTestDao extends Dao{

    /**
     * Class name for entity this dao class handles.
     * @return string
     */
    public function getEntityClass() {
        return "\\SimphpleOrm\\Test\\ReferenceTest";
    }
}