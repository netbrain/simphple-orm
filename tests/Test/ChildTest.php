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
    private $data;

    /**
     * @return int
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getData() {
        return $this->data;
    }

    /**
     * @param string $data
     */
    public function setData($data) {
        $this->data = $data;
    }


}