<?php

namespace SimphpleOrm\Dao;

use SimphpleOrm\Test\NotAnnotatedTest;
use SimphpleOrm\Test\NotAnnotatedTestDao;

class NotAnnotatedEntityTableDataTest extends DaoTestCase {

    /**
     * @var NotAnnotatedTestDao
     */
    private $notAnnotatedTestDao;

    /**
     * @var NotAnnotatedTest
     */
    private $notAnnotatedTest;

    /**
     * @var TableData
     */
    private $notAnnotatedTableData;

    protected function setUp() {
        $this->notAnnotatedTest = new NotAnnotatedTest();
        $this->notAnnotatedTestDao = new NotAnnotatedTestDao(self::$db);
        $this->notAnnotatedTableData = TableDataBuilder::build($this->notAnnotatedTestDao->getEntityClass());
    }

    public function testGetCreateTableSQLFromNotAnnotatedEntity() {
        $sql = $this->notAnnotatedTableData->getCreateTableSQL();
        $this->assertEqualsFixture($sql,__FUNCTION__);
        $this->runQuery($sql);
    }

    public function testGetCreateSQLWithStringDataFromNotAnnotatedEntity() {
        $this->notAnnotatedTest->setString("some string");
        $sql = $this->notAnnotatedTableData->getCreateSQL($this->notAnnotatedTest);
        $this->assertEqualsFixture($sql,__FUNCTION__);
        $this->runQuery($sql);;
    }

    public function testGetCreateSQLWithIntDataFromNotAnnotatedEntity() {
        $this->notAnnotatedTest->setString("some string");
        $this->notAnnotatedTest->setInt(10);
        $sql = $this->notAnnotatedTableData->getCreateSQL($this->notAnnotatedTest);
        $this->assertEqualsFixture($sql,__FUNCTION__);
        $this->runQuery($sql);
    }


    public function testGetCreateSQLWithBooleanTrueFromNotAnnotatedEntity() {
        $this->notAnnotatedTest->setString("some string");
        $this->notAnnotatedTest->setBoolean(true);
        $sql = $this->notAnnotatedTableData->getCreateSQL($this->notAnnotatedTest);
        $this->assertEqualsFixture($sql,__FUNCTION__);
        $this->runQuery($sql);
    }

    public function testGetCreateSQLWithBooleanFalseFromNotAnnotatedEntity() {
        $this->notAnnotatedTest->setString("some string");
        $this->notAnnotatedTest->setBoolean(false);
        $sql = $this->notAnnotatedTableData->getCreateSQL($this->notAnnotatedTest);
        $this->assertEqualsFixture($sql,__FUNCTION__);
        $this->runQuery($sql);
    }


    public function testGetCreateSQLWithFloatDataFromNotAnnotatedEntity() {
        $this->notAnnotatedTest->setString("some string");
        $this->notAnnotatedTest->setFloat(0.019284);
        $sql = $this->notAnnotatedTableData->getCreateSQL($this->notAnnotatedTest);
        $this->assertEqualsFixture($sql,__FUNCTION__);
        $this->runQuery($sql);
    }

    public function testDropNotAnnotatedTestTableIfExistsSQL() {
        $sql = $this->notAnnotatedTableData->getDropTableSQL();
        $this->assertEqualsFixture($sql,__FUNCTION__);
        $this->runQuery($sql);
    }

    public function testGetFindSQLOnNotAnnotatedEntity() {
        $sql = $this->notAnnotatedTableData->getFindSQL(1);
        $this->assertEqualsFixture($sql,__FUNCTION__);
    }

    public function testGetDeleteSQLOnNotAnnotatedEntity() {
        $this->notAnnotatedTest->setId(1);
        $sql = $this->notAnnotatedTableData->getDeleteSQL($this->notAnnotatedTest);
        $this->assertEqualsFixture($sql,__FUNCTION__);
        $this->runQuery($sql);
    }
}







