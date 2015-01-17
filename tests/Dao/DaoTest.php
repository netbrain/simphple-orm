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
        parent::setUp();
        $this->annotatedTestDao = AnnotatedTestDao::getInstance($this->daoFactory);
        $this->childTestDao = ChildTestDao::getInstance($this->daoFactory);
        $this->daoFactory->createTables();

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

    public function testCreateEntityHasCacheProperty() {
        $this->annotatedTestDao->create($this->entity);
        $this->assertNotNull($this->entity->{Dao::CACHE});
        /**
         * @var $dbEntity AnnotatedTest
         */
        $dbEntity = $this->annotatedTestDao->find($this->entity->getId());
        $this->assertNotNull($dbEntity->{Dao::CACHE});
    }


    public function testCreateEntityIsDirty() {
        $this->annotatedTestDao->create($this->entity);
        $this->assertFalse($this->annotatedTestDao->isDirty($this->entity));
        $this->entity->setString("Adding this string should cause entity to be dirty");
        $this->assertTrue($this->annotatedTestDao->isDirty($this->entity));
    }

    public function testCreateEntityIsDirtyWhenAddingToCollection() {
        $this->annotatedTestDao->create($this->entity);
        $this->assertFalse($this->annotatedTestDao->isDirty($this->entity));
        $this->entity->setOneToManyChild(array(new ChildTest()));
        $this->assertTrue($this->annotatedTestDao->isDirty($this->entity));
        $this->annotatedTestDao->update($this->entity);
        $this->assertFalse($this->annotatedTestDao->isDirty($this->entity));
        $this->entity->setOneToManyChild(array(new ChildTest()));
        $this->assertTrue($this->annotatedTestDao->isDirty($this->entity));
        $this->annotatedTestDao->update($this->entity);
        $this->assertFalse($this->annotatedTestDao->isDirty($this->entity));

    }

    public function testCreateEntityIsDirtyWhenSettingOneToOneRelationship() {
        $this->annotatedTestDao->create($this->entity);
        $this->assertFalse($this->annotatedTestDao->isDirty($this->entity));
        $this->entity->setOneToOneChild(new ChildTest());
        $this->assertTrue($this->annotatedTestDao->isDirty($this->entity));
        $this->annotatedTestDao->update($this->entity);
        $this->assertFalse($this->annotatedTestDao->isDirty($this->entity));

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
        $this->assertEquals($this->entity->isBoolean(), $dbEntity->isBoolean());
        $this->assertEquals($this->entity->getFloat(), $dbEntity->getFloat());
        $this->assertEquals($this->entity->getString(), $dbEntity->getString());
        $this->assertNotEquals($this->entity->getTransient(), $dbEntity->getTransient());
        $this->assertEquals($this->entity->getInt(), $dbEntity->getInt());
        $this->annotatedTestDao->initializeDeep($dbEntity);
        $this->assertEquals($this->entity->getOneToOneChild(),$dbEntity->getOneToOneChild());
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

    public function testCanRemoveOneElementFromOneToManyCollection(){
        $this->entity->setOneToManyChild(array(new ChildTest()));
        $this->annotatedTestDao->create($this->entity);

        $this->entity = $this->annotatedTestDao->find($this->entity->getId());
        $this->assertEquals(1,count($this->entity->getOneToManyChild()));

        $this->entity->setOneToManyChild(null);
        $this->annotatedTestDao->update($this->entity);

        $this->entity = $this->annotatedTestDao->find($this->entity->getId());
        $this->assertEquals(0,count($this->entity->getOneToManyChild()));
    }

    public function testCanAddOneElementFromOneToManyCollection(){
        $this->entity->setOneToManyChild(null);
        $this->annotatedTestDao->create($this->entity);

        $this->entity = $this->annotatedTestDao->find($this->entity->getId());
        $this->assertEquals(0,count($this->entity->getOneToManyChild()));

        $this->entity->setOneToManyChild(array(new ChildTest()));
        $this->annotatedTestDao->update($this->entity);

        $this->entity = $this->annotatedTestDao->find($this->entity->getId());
        $this->assertEquals(1,count($this->entity->getOneToManyChild()));
    }

    public function testCanUpdateChildElementWithoutLoosingParentFK(){
        $this->entity->setOneToManyChild(array(new ChildTest()));
        $this->annotatedTestDao->create($this->entity);

        $this->entity = $this->annotatedTestDao->find($this->entity->getId());
        $this->assertEquals(1,count($this->entity->getOneToManyChild()));

        $child = current($this->entity->getOneToManyChild());
        $child->setData('something updated');
        $this->childTestDao->update($child);

        $this->annotatedTestDao->refresh($this->entity);
        $this->assertEquals(1,count($this->entity->getOneToManyChild()));
    }
}







