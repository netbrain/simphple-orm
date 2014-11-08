<?php

namespace SimphpleOrm\Dao;

use SimphpleOrm\Test\ChildTest;
use SimphpleOrm\Test\ChildTestDao;
use SimphpleOrm\Test\ParentTest;
use SimphpleOrm\Test\ParentTestDao;

class ParentTestDaoTest extends DaoTestCase{

    /**
     * @var ParentTestDao
     */
    private $parentDao;

    /**
     * @var ChildTestDao
     */
    private $childDao;

    protected function setUp() {
        parent::setUp();
        $this->parentDao = ParentTestDao::getInstance($this->daoFactory);
        $this->childDao = ChildTestDao::getInstance($this->daoFactory);
        $this->daoFactory->createTables();
    }

    public function testCanCreateParentTable(){
        $table = $this->database->build($this->parentDao->getEntityClass());
        $sql = $table->getCreateTableSQL();
        $this->assertEqualsFixture($sql,__FUNCTION__);
        $this->runQuery($sql);
    }

    public function testCanCreateChildTable(){
        $this->database->build($this->parentDao->getEntityClass());
        $table = $this->database->build($this->childDao->getEntityClass());
        $sql = $table->getCreateTableSQL();
        $this->assertEqualsFixture($sql,__FUNCTION__);
        $this->runQuery($sql);
    }

    public function testCanCreateParentWithChild(){
        $parent = new ParentTest();
        $child = new ChildTest();
        $parent->setChild($child);
        $pid = $this->parentDao->create($parent);

        $child2 = $this->childDao->find($parent->getChild()->getId());
        $this->assertEquals($child,$child2);

        $parent2 = $this->parentDao->find($pid);
        $this->parentDao->initializeDeep($parent2);
        $this->assertEquals($parent,$parent2);
    }

} 