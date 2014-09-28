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
     * @var string
     */
    private $string;

    /**
     * @return int
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getString() {
        return $this->string;
    }


}