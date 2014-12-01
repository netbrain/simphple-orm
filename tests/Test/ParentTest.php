<?php

namespace SimphpleOrm\Test;

/**
 * Class ParentTest
 * @package SimphpleOrm\Test
 * @table parent
 */
class ParentTest {
    /**
     * @var integer
     * @id
     */
    private $id;

    /**
     * @var ChildTest
     */
    private $child;

    /**
     * @var ChildTest[]
     */
    private $children = array();


    /**
     * @return int
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @return ChildTest
     */
    public function getChild() {
        return $this->child;
    }

    public function setChild($child) {
        $this->child = $child;
    }

    public function addToChildren($child){
        $this->children[] = $child;
    }

    /**
     * @return ChildTest[]
     */
    public function getChildren() {
        return $this->children;
    }

    /**
     * @param ChildTest[] $children
     */
    public function setChildren($children) {
        $this->children = $children;
    }



} 