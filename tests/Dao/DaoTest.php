<?php

namespace SimphpleOrm\Dao;


use SimphpleOrm\Test\AnnotatedTest;
use SimphpleOrm\Test\AnnotatedTestDao;
use SimphpleOrm\Test\ChildTest;
use SimphpleOrm\Test\ChildTestDao;
use Symfony\Component\Process\Exception\RuntimeException;

class DaoTest extends DaoTestCase {
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

    public function testCreateEntity() {
        $this->assertTrue($this->annotatedTestDao->isTransient($this->entity));
        $this->annotatedTestDao->create($this->entity);
        $this->assertFalse($this->annotatedTestDao->isTransient($this->entity));
        $this->assertNotNull($this->entity->_version);
    }

    public function testFindEntity() {
        $this->annotatedTestDao->create($this->entity);
        $this->entity = $this->annotatedTestDao->find($this->entity->getId());
        $this->assertNotNull($this->entity);
    }

    public function testCannotFindNonExistingEntity() {
        $this->entity = $this->annotatedTestDao->find(-10);
        $this->assertNull($this->entity);
    }

    public function testCreateEntityWithData() {
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

        $this->assertEquals($this->entity->getId(), $dbEntity->getId());
        $this->assertEquals($this->entity->getOneToOneChild()->getId(), $dbEntity->getOneToOneChild()->getId());
        $this->assertEquals($this->entity->getOneToOneChild()->getString(), $dbEntity->getOneToOneChild()->getString());
        $this->assertEquals($this->entity->isBoolean(), $dbEntity->isBoolean());
        $this->assertEquals($this->entity->getFloat(), $dbEntity->getFloat());
        $this->assertEquals($this->entity->getString(), $dbEntity->getString());
        $this->assertNotEquals($this->entity->getTransient(), $dbEntity->getTransient());
        $this->assertEquals($this->entity->getInt(), $dbEntity->getInt());
        $dbEntity->setOneToManyChild($this->annotatedTestDao->initializeCollection($dbEntity->getOneToManyChild()));
        $this->assertEquals($this->entity->getOneToManyChild(),$dbEntity->getOneToManyChild());
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testCannotUpdateIfWorkingOnOldEntity() {
        $this->annotatedTestDao->create($this->entity);
        $entity2 = clone $this->entity;
        $this->entity->setString("some change");
        $this->annotatedTestDao->update($this->entity);
        $entity2->setString("some other failing change");
        $this->annotatedTestDao->update($entity2);
    }

    public function testCanDeleteEntity(){
        $this->annotatedTestDao->create($this->entity);
        $this->annotatedTestDao->delete($this->entity);
        $this->assertNull($this->annotatedTestDao->find($this->entity->getId()));
    }


    /**
     * @expectedException RuntimeException
     */
    public function testCannotDeleteNonExistingEntity(){
        $this->annotatedTestDao->delete($this->entity);
    }

    public function testCanDeleteWhereEntityIsPreviouslyUpdated(){
        $this->annotatedTestDao->create($this->entity);
        $this->entity->setString("some updated value");
        $this->annotatedTestDao->update($this->entity);
        $this->annotatedTestDao->delete($this->entity);
        $this->assertNull($this->annotatedTestDao->find($this->entity->getId()));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testCannotDeletIfWorkingOnOldEntity(){
        $this->annotatedTestDao->create($this->entity);
        $oldEntity = clone $this->entity;
        $this->entity->setString("some updated value");
        $this->annotatedTestDao->update($this->entity);
        $this->annotatedTestDao->delete($oldEntity);
    }


    public function testCanRefreshEntity(){
        $this->annotatedTestDao->create($this->entity);
        $oldEntity = clone $this->entity;
        $this->entity->setString("some updated value");
        $this->annotatedTestDao->update($this->entity);


        $this->annotatedTestDao->refresh($oldEntity);
        $this->assertEquals($this->entity->getString(),$oldEntity->getString());
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testCanotRefreshDeletedEntity(){
        $this->annotatedTestDao->create($this->entity);
        $oldEntity = clone $this->entity;
        $this->annotatedTestDao->delete($this->entity);

        $this->annotatedTestDao->refresh($oldEntity);
    }


    /**
     * @expectedException \RuntimeException
     */
    public function testCannotInsertTheSameEntityTwice(){
        $this->annotatedTestDao->create($this->entity);
        $this->annotatedTestDao->create($this->entity);;
    }



    public function testCanFetchAllEntites(){
        /**
         * @var $entities ChildTest[]
         * @var $all ChildTest[]
         */
        $entities = array(
            new ChildTest(),
            new ChildTest()
        );

        foreach($entities as $entity){
            $this->childTestDao->create($entity);
        }

        $all = $this->childTestDao->all();
        $this->assertEquals(count($entities),count($all));

        for($x = 0 ; $x < count($entities); $x++){
            $this->assertEquals($entities[$x]->getId(),$all[$x]->getId());
        }
    }



}







