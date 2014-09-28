<?php

namespace SimphpleOrm\Dao;


use SimphpleOrm\Test\AnnotatedTest;
use SimphpleOrm\Test\AnnotatedTestDao;
use SimphpleOrm\Test\NotAnnotatedTest;
use SimphpleOrm\Test\NotAnnotatedTestDao;

class TableDataBuilderTest extends DaoTestFramework {

    /**
     * @var AnnotatedTestDao
     */
    private $annotatedTestDao;

    /**
     * @var NotAnnotatedTestDao
     */
    private $notAnnotatedTestDao;

    /**
     * @var AnnotatedTest
     */
    private $annotatedTest;

    /**
     * @var NotAnnotatedTest
     */
    private $notAnnotatedTest;

    /**
     * @var TableData
     */
    private $annotatedTableData;

    /**
     * @var TableData
     */
    private $notAnnotatedTableData;


    protected function setUp() {
        $this->annotatedTest = new AnnotatedTest();
        $this->notAnnotatedTest = new NotAnnotatedTest();
        $this->annotatedTestDao = new AnnotatedTestDao(self::$db);
        $this->notAnnotatedTestDao = new NotAnnotatedTestDao(self::$db);
        $this->annotatedTableData = TableDataBuilder::build($this->annotatedTestDao->getEntityClass());
        $this->notAnnotatedTableData = TableDataBuilder::build($this->notAnnotatedTestDao->getEntityClass());
    }


    public function testGetCreateTableSQLFromAnnotatedEntity() {
        $sql = $this->annotatedTableData->getCreateTableSQL();

        $expected = 'CREATE TABLE IF NOT EXISTS Test (' .
            '`myId` VARCHAR(13) PRIMARY KEY, ' .
            '`some-bool` BOOL, ' .
            '`some-float` FLOAT, ' .
            '`some-integer` INT UNIQUE, ' .
            '`some-string` VARCHAR(60) NOT NULL, ' .
            '`one_to_one_child` INT, ' .
            '`one_to_many_child` INT, ' .
            '`_version` INT NOT NULL DEFAULT 1'.
            ')';

        $this->assertEquals($expected, $sql);
        $this->runQuery($sql);
    }

    public function testGetCreateTableSQLFromNotAnnotatedEntity() {
        $sql = $this->notAnnotatedTableData->getCreateTableSQL();
        $expected = 'CREATE TABLE IF NOT EXISTS NotAnnotatedTest (' .
            '`id` INT PRIMARY KEY AUTO_INCREMENT, ' .
            '`boolean` BOOL, ' .
            '`float` FLOAT, ' .
            '`int` INT, ' .
            '`string` VARCHAR (255), ' .
            '`_version` INT NOT NULL DEFAULT 1'.
            ')';
        $this->assertEquals($expected, $sql);
        $this->runQuery($sql);
    }

    public function testGetCreateSQLWithStringDataFromAnnotatedEntity() {
        $this->annotatedTest->setString("some string");
        $sql = $this->annotatedTableData->getCreateSQL($this->annotatedTest);
        $this->assertEquals("INSERT INTO Test (`myId`,`some-bool`,`some-float`,`some-integer`,`some-string`,`one_to_one_child`,`one_to_many_child`,`_version`) VALUES ('{$this->annotatedTest->getId()}',NULL,NULL,NULL,'some string',NULL,NULL,1)", $sql);
        $this->runQuery($sql);
    }


    public function testGetCreateSQLWithIntDataFromAnnotatedEntity() {
        $this->annotatedTest->setString("some string");
        $this->annotatedTest->setInt(10);
        $sql = $this->annotatedTableData->getCreateSQL($this->annotatedTest);
        $this->assertEquals("INSERT INTO Test (`myId`,`some-bool`,`some-float`,`some-integer`,`some-string`,`one_to_one_child`,`one_to_many_child`,`_version`) VALUES ('{$this->annotatedTest->getId()}',NULL,NULL,10,'some string',NULL,NULL,1)", $sql);
        $this->runQuery($sql);
    }

    public function testGetCreateSQLWithBooleanTrueFromAnnotatedEntity() {
        $this->annotatedTest->setString("some string");
        $this->annotatedTest->setBoolean(true);
        $sql = $this->annotatedTableData->getCreateSQL($this->annotatedTest);
        $this->assertEquals("INSERT INTO Test (`myId`,`some-bool`,`some-float`,`some-integer`,`some-string`,`one_to_one_child`,`one_to_many_child`,`_version`) VALUES ('{$this->annotatedTest->getId()}',true,NULL,NULL,'some string',NULL,NULL,1)", $sql);
        $this->runQuery($sql);
    }


    public function testGetCreateSQLWithBooleanFalseFromAnnotatedEntity() {
        $this->annotatedTest->setString("some string");
        $this->annotatedTest->setBoolean(false);
        $sql = $this->annotatedTableData->getCreateSQL($this->annotatedTest);
        $this->assertEquals("INSERT INTO Test (`myId`,`some-bool`,`some-float`,`some-integer`,`some-string`,`one_to_one_child`,`one_to_many_child`,`_version`) VALUES ('{$this->annotatedTest->getId()}',false,NULL,NULL,'some string',NULL,NULL,1)", $sql);
        $this->runQuery($sql);
    }


    public function testGetCreateSQLWithFloatDataFromAnnotatedEntity() {
        $this->annotatedTest->setString("some string");
        $this->annotatedTest->setFloat(0.019284);
        $sql = $this->annotatedTableData->getCreateSQL($this->annotatedTest);
        $this->assertEquals("INSERT INTO Test (`myId`,`some-bool`,`some-float`,`some-integer`,`some-string`,`one_to_one_child`,`one_to_many_child`,`_version`) VALUES ('{$this->annotatedTest->getId()}',NULL,0.019284,NULL,'some string',NULL,NULL,1)", $sql);
        $this->runQuery($sql);
    }

    public function testGetCreateSQLWithStringDataFromNotAnnotatedEntity() {
        $this->notAnnotatedTest->setString("some string");
        $sql = $this->notAnnotatedTableData->getCreateSQL($this->notAnnotatedTest);
        $this->assertEquals("INSERT INTO NotAnnotatedTest (`id`,`boolean`,`float`,`int`,`string`,`_version`) VALUES (DEFAULT,NULL,NULL,NULL,'some string',1)", $sql);
        $this->runQuery($sql);
    }


    public function testGetCreateSQLWithIntDataFromNotAnnotatedEntity() {
        $this->notAnnotatedTest->setString("some string");
        $this->notAnnotatedTest->setInt(10);
        $sql = $this->notAnnotatedTableData->getCreateSQL($this->notAnnotatedTest);
        $this->assertEquals("INSERT INTO NotAnnotatedTest (`id`,`boolean`,`float`,`int`,`string`,`_version`) VALUES (DEFAULT,NULL,NULL,10,'some string',1)", $sql);
        $this->runQuery($sql);
    }

    public function testGetCreateSQLWithBooleanTrueFromNotAnnotatedEntity() {
        $this->notAnnotatedTest->setString("some string");
        $this->notAnnotatedTest->setBoolean(true);
        $sql = $this->notAnnotatedTableData->getCreateSQL($this->notAnnotatedTest);
        $this->assertEquals("INSERT INTO NotAnnotatedTest (`id`,`boolean`,`float`,`int`,`string`,`_version`) VALUES (DEFAULT,true,NULL,NULL,'some string',1)", $sql);
        $this->runQuery($sql);
    }


    public function testGetCreateSQLWithBooleanFalseFromNotAnnotatedEntity() {
        $this->notAnnotatedTest->setString("some string");
        $this->notAnnotatedTest->setBoolean(false);
        $sql = $this->notAnnotatedTableData->getCreateSQL($this->notAnnotatedTest);
        $this->assertEquals("INSERT INTO NotAnnotatedTest (`id`,`boolean`,`float`,`int`,`string`,`_version`) VALUES (DEFAULT,false,NULL,NULL,'some string',1)", $sql);
        $this->runQuery($sql);
    }


    public function testGetCreateSQLWithFloatDataFromNotAnnotatedEntity() {
        $this->notAnnotatedTest->setString("some string");
        $this->notAnnotatedTest->setFloat(0.019284);
        $sql = $this->notAnnotatedTableData->getCreateSQL($this->notAnnotatedTest);
        $this->assertEquals("INSERT INTO NotAnnotatedTest (`id`,`boolean`,`float`,`int`,`string`,`_version`) VALUES (DEFAULT,NULL,0.019284,NULL,'some string',1)", $sql);
        $this->runQuery($sql);
    }

    public function testDropAnnotatedTestTableIfExistsSQL() {
        $sql = $this->annotatedTableData->getDropTableSQL();
        $this->assertEquals("DROP TABLE IF EXISTS Test", $sql);
        $this->runQuery($sql);
    }

    public function testDropNotAnnotatedTestTableIfExistsSQL() {
        $sql = $this->notAnnotatedTableData->getDropTableSQL();
        $this->assertEquals("DROP TABLE IF EXISTS NotAnnotatedTest", $sql);
        $this->runQuery($sql);
    }

    public function testCreateTableAndFieldsInDatabase() {
        $sql = $this->annotatedTableData->getCreateTableSQL();
        $this->runQuery($sql);
    }

    public function testGetFindSQL() {
        $sql = $this->annotatedTableData->getFindSQL(1);
        //$this->assertEquals("asd",$sql);
    }


}







