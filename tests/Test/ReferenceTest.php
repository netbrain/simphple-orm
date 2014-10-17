<?php

namespace SimphpleOrm\Test;

/**
 * Class ReferenceTest
 * @package SimphpleOrm\Test
 * @table reference
 */
class ReferenceTest {
    /**
     * @var integer
     * @id
     */
    private $id;

    /**
     * @var ReferenceTest
     */
    private $reference;

    /**
     * @return int
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id) {
        $this->id = $id;
    }

    /**
     * @return ReferenceTest
     */
    public function getReference() {
        return $this->reference;
    }

    /**
     * @param ReferenceTest $reference
     */
    public function setReference($reference) {
        $this->reference = $reference;
    }


} 