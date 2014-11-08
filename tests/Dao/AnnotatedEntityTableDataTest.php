<?php

namespace SimphpleOrm\Dao;


use SimphpleOrm\Test\AnnotatedTest;
use SimphpleOrm\Test\AnnotatedTestDao;

class AnnotatedEntityTableDataBuilderTest extends DaoTestCase {

    /**
     * @var AnnotatedTestDao
     */
    private $annotatedTestDao;

    /**
     * @var AnnotatedTest
     */
    private $annotatedTest;

    /**
     * @var Table
     */
    private $annotatedTableData;


    protected function setUp() {
        parent::setUp();
        $this->annotatedTest = new AnnotatedTest();
        $this->annotatedTestDao = new AnnotatedTestDao($this->database,$this->daoFactory);
        $this->annotatedTableData = $this->database->build($this->annotatedTestDao->getEntityClass());
        $this->annotatedTestDao->createTable();
    }

    public function testGetCreateTableSQLFromAnnotatedEntity() {
        $sql = $this->annotatedTableData->getCreateTableSQL();
        $this->assertEqualsFixture($sql,__FUNCTION__);
        $this->runQuery($sql);
    }

    public function testGetCreateSQLWithStringDataFromAnnotatedEntity() {
        $this->annotatedTest->setString("some string");
        $sql = $this->annotatedTableData->getCreateSQL($this->annotatedTest);
        $this->assertEqualsFixture($sql,__FUNCTION__,$this->annotatedTest->getId());
        $this->runQuery($sql);
    }

    public function testGetCreateSQLWithIntDataFromAnnotatedEntity() {
        $this->annotatedTest->setString("some string");
        $this->annotatedTest->setInt(10);
        $sql = $this->annotatedTableData->getCreateSQL($this->annotatedTest);
        $this->assertEqualsFixture($sql,__FUNCTION__,$this->annotatedTest->getId());
        $this->runQuery($sql);
    }


    public function testGetCreateSQLWithBooleanTrueFromAnnotatedEntity() {
        $this->annotatedTest->setString("some string");
        $this->annotatedTest->setBoolean(true);
        $sql = $this->annotatedTableData->getCreateSQL($this->annotatedTest);
        $this->assertEqualsFixture($sql,__FUNCTION__,$this->annotatedTest->getId());
        $this->runQuery($sql);
    }

    public function testGetCreateSQLWithBooleanFalseFromAnnotatedEntity() {
        $this->annotatedTest->setString("some string");
        $this->annotatedTest->setBoolean(false);
        $sql = $this->annotatedTableData->getCreateSQL($this->annotatedTest);
        $this->assertEqualsFixture($sql,__FUNCTION__,$this->annotatedTest->getId());
        $this->runQuery($sql);
    }


    public function testGetCreateSQLWithFloatDataFromAnnotatedEntity() {
        $this->annotatedTest->setString("some string");
        $this->annotatedTest->setFloat(0.019284);
        $sql = $this->annotatedTableData->getCreateSQL($this->annotatedTest);
        $this->assertEqualsFixture($sql,__FUNCTION__,$this->annotatedTest->getId());
        $this->runQuery($sql);
    }

    public function testDropAnnotatedTestTableIfExistsSQL() {
        $sql = $this->annotatedTableData->getDropTableSQL();
        $this->assertEqualsFixture($sql,__FUNCTION__);
        $this->runQuery($sql);
    }

    public function testGetFindSQLOnAnnotatedEntity() {
        $sql = $this->annotatedTableData->getFindSQL(1);
        $this->assertEqualsFixture($sql,__FUNCTION__);
    }

    public function testGetDeleteSQLOnAnnotatedEntity() {
        $this->annotatedTest->setId(1);
        $sql = $this->annotatedTableData->getDeleteSQL($this->annotatedTest);
        $this->assertEqualsFixture($sql,__FUNCTION__);
        $this->runQuery($sql);
    }
}







