<?php


namespace SimphpleOrm\Test;

/**
 * Class AnnotatedTest
 * @package SimphpleOrm\Dao
 * @table Test
 */
class AnnotatedTest {

    /**
     * @field myId
     * @fieldType VARCHAR(13)
     * @var string
     * @id
     * @noAuto
     */
    private $id;

    /**
     * @field some-string
     * @fieldType VARCHAR(60)
     * @var string
     * @notNull
     */
    private $string;

    /**
     * @field some-integer
     * @fieldType INT
     * @var integer
     * @unique
     */
    private $int;

    /**
     * @field some-bool
     * @fieldType BOOL
     * @var boolean
     */
    private $boolean;

    /**
     * @field some-float
     * @fieldType FLOAT
     * @var float
     */
    private $float;

    /**
     * @transient
     * @var string
     */
    private $transient;

    /**
     * @var ChildTest
     */
    private $one_to_one_child;

    /**
     * @var ChildTest[]
     */
    private $one_to_many_child;

    function __construct() {
        $this->id = uniqid();
    }


    /**
     * @return boolean
     */
    public function isBoolean() {
        return $this->boolean;
    }

    /**
     * @param boolean $boolean
     */
    public function setBoolean($boolean) {
        $this->boolean = $boolean;
    }

    /**
     * @return float
     */
    public function getFloat() {
        return $this->float;
    }

    /**
     * @param float $float
     */
    public function setFloat($float) {
        $this->float = $float;
    }

    /**
     * @return string
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @param string $id
     */
    public function setId($id) {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getInt() {
        return $this->int;
    }

    /**
     * @param int $int
     */
    public function setInt($int) {
        $this->int = $int;
    }

    /**
     * @return ChildTest[]
     */
    public function getOneToManyChild() {
        return $this->one_to_many_child;
    }

    /**
     * @param ChildTest[] $one_to_many_child
     */
    public function setOneToManyChild($one_to_many_child) {
        $this->one_to_many_child = $one_to_many_child;
    }

    /**
     * @return ChildTest
     */
    public function getOneToOneChild() {
        return $this->one_to_one_child;
    }

    /**
     * @param ChildTest $one_to_one_child
     */
    public function setOneToOneChild($one_to_one_child) {
        $this->one_to_one_child = $one_to_one_child;
    }

    /**
     * @return string
     */
    public function getString() {
        return $this->string;
    }

    /**
     * @param string $string
     */
    public function setString($string) {
        $this->string = $string;
    }

    /**
     * @return string
     */
    public function getTransient() {
        return $this->transient;
    }

    /**
     * @param string $transient
     */
    public function setTransient($transient) {
        $this->transient = $transient;
    }
    
}