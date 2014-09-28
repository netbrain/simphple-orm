<?php

namespace SimphpleOrm\Test;


class NotAnnotatedTest{
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
     * @var integer
     */
    private $int;

    /**
     * @var boolean
     */
    private $boolean;

    /**
     * @var float
     */
    private $float;

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

}