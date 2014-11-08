<?php

namespace SimphpleOrm\Test;


/**
 * Class ChildTest
 * @package SimphpleOrm\Dao
 * @table child
 */
class ChildTest {
    /**
     * @var integer
     * @id
     */
    private $id;

    /**
     * @return int
     */
    public function getId() {
        return $this->id;
    }
}