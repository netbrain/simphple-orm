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

    public function testCanCreateParentWithChilddren(){
        $parent = new ParentTest();
        $child = new ChildTest();
        $parent->setChildren(array($child));
        $this->parentDao->create($parent);
        $this->assertNotNull($child->getId());
    }

    public function testCanUpdateParentWithChildren(){
        $parent = new ParentTest();
        $child = new ChildTest();
        $this->parentDao->create($parent);
        $parent->setChildren(array($child));
        $this->parentDao->update($parent);
        $this->assertNotNull($child->getId());
    }

    public function testUpdateWillBeSkippedIfNoFieldsWereModified(){
        $parent = new ParentTest();
        $child = new ChildTest();
        $this->parentDao->create($parent);
        $this->assertEquals(1,$parent->{Dao::VERSION});
        $parent->setChild($child);

        //First update, increments version to 2
        $this->parentDao->update($parent);
        $this->assertEquals(2,$parent->{Dao::VERSION});

        //Second update with no changes, does not increment version.
        $this->parentDao->update($parent);
        $this->assertEquals(2,$parent->{Dao::VERSION});
    }

    public function testCanAddToCollectionProxy(){
        $parent = new ParentTest();
        $id = $this->parentDao->create($parent);

        /**
         * fetch lazy object
         * @var $parent ParentTest
         */
        $parent = $this->parentDao->find($id);
        $this->assertTrue($parent->getChildren() instanceof CollectionProxy);
        $parent->addToChildren(new ChildTest());
        $this->parentDao->update($parent);
        $children = $parent->getChildren();
        $this->assertTrue(is_array($children));
        $this->assertEquals(1,count($children));
        $this->assertFalse($this->childDao->isTransient(current($children)));
        $this->assertTrue(is_numeric(current($children)->getId()));

    }

} 