<?php

namespace SimphpleOrm\Dao;


use SimphpleOrm\Test\AnnotatedTest;
use SimphpleOrm\Test\AnnotatedTestDao;
use SimphpleOrm\Test\ChildTest;
use SimphpleOrm\Test\ChildTestDao;

class DaoTest extends DaoTestFramework {
    /**
     * @var AnnotatedTestDao
     */
    private $annotatedTestDao;

    /**
     * @var ChildTestDao
     */
    private $childTestDao;

    /**
     * @var AnnotatedTest
     */
    private $entity;

    protected function setUp() {
        DaoFactory::build(self::$db);
        $this->annotatedTestDao = DaoFactory::getDao("\\SimphpleOrm\\Test\\AnnotatedTestDao");
        $this->childTestDao = DaoFactory::getDao("\\SimphpleOrm\\Test\\ChildTestDao");
        $this->entity = new AnnotatedTest();
        $this->entity->setString("some-string-value");
    }

    public function testCreateAnnotatedTest() {
        $this->assertTrue($this->annotatedTestDao->isTransient($this->entity));
        $this->annotatedTestDao->create($this->entity);
        $this->assertFalse($this->annotatedTestDao->isTransient($this->entity));
        $this->assertNotNull($this->entity->_version);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testCreateOptimisticLockingFailure() {
        $this->annotatedTestDao->create($this->entity);
        $entity2 = clone $this->entity;
        $this->entity->setString("some change");
        $this->annotatedTestDao->update($this->entity);
        $entity2->setString("some other failing change");
        $this->annotatedTestDao->update($entity2);
    }

    public function testFindAnnotatedTest() {
        $this->annotatedTestDao->create($this->entity);
        $this->entity = $this->annotatedTestDao->find($this->entity->getId());
        $this->assertNotNull($this->entity);
        self::$logger->info($this->entity);
    }

    public function testCreateAnnotatedTestWithData() {
        $this->entity->setOneToOneChild(new ChildTest());
        $this->entity->setBoolean(true);
        $this->entity->setFloat(0.1);
        $this->entity->setInt(1);
        $this->entity->setTransient("this is never stored to db");
        $this->entity->setOneToManyChild(array(new ChildTest(), new ChildTest()));
        $this->annotatedTestDao->create($this->entity);
        /**
         * @var $dbEntity AnnotatedTest
         */
        $dbEntity = $this->annotatedTestDao->find($this->entity->getId());

        $this->assertEquals($this->entity->getId(),$dbEntity->getId());
        $this->assertEquals($this->entity->getOneToOneChild()->getId(),$dbEntity->getOneToOneChild()->getId());
        $this->assertEquals($this->entity->getOneToOneChild()->getString(),$dbEntity->getOneToOneChild()->getString());
        $this->assertEquals($this->entity->isBoolean(),$dbEntity->isBoolean());
        $this->assertEquals($this->entity->getFloat(),$dbEntity->getFloat());
        $this->assertEquals($this->entity->getString(),$dbEntity->getString());
        $this->assertNotEquals($this->entity->getTransient(),$dbEntity->getTransient());
        $this->assertEquals($this->entity->getInt(),$dbEntity->getInt());
        //$this->assertEquals($this->entity->getOneToManyChild(),$dbEntity->getOneToManyChild());

        $this->entity->setString("Whaaat?!");
        $this->annotatedTestDao->update($this->entity);


    }



}







