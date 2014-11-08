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
     * @var ReferenceTest[]
     */
    private $references;

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

    /**
     * @return ReferenceTest[]
     */
    public function getReferences() {
        return $this->references;
    }

    /**
     * @param $references ReferenceTest[]
     */
    public function setReferences($references) {
        $this->references = $references;
    }



} 