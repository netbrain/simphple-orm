<?php
/**
 * Created by PhpStorm.
 * User: netbrain
 * Date: 10/21/14
 * Time: 11:58 PM
 */

namespace SimphpleOrm\Dao;


use SimphpleOrm\Test\ReferenceTest;
use SimphpleOrm\Test\ReferenceTestDao;

/**
 * Class ReferenceDaoTest
 * @package SimphpleOrm\Dao
 */
class ReferenceDaoTest extends DaoTestCase{

    protected function setUp() {
        parent::setUp();
    }

    public function testCanCreateReferenceTable(){
        $dao = ReferenceTestDao::getInstance($this->daoFactory);
        $table = $this->database->build($dao->getEntityClass());
        $sql = $table->getCreateTableSQL();
        $this->assertEqualsFixture($sql,__FUNCTION__);
        $this->runQuery($sql);
    }


    public function testCanCreateReference(){
        $dao = ReferenceTestDao::getInstance($this->daoFactory);
        $dao->createTable();

        $reference = new ReferenceTest();
        $dao->create($reference);
        $reference = $dao->find($reference->getId());
        $this->assertNotNull($reference);
    }

    public function testCanCreateReferenceWithOneToOneRelation(){
        $dao = ReferenceTestDao::getInstance($this->daoFactory);
        $dao->createTable();

        $reference = new ReferenceTest();
        $reference->setReference(new ReferenceTest());
        $dao->create($reference);
        $reference = $dao->find($reference->getId());
        $this->assertNotNull($reference);
        $this->assertNotNull($reference->getReference());
    }




    public function testCanFetchReferenceFromReference(){
        $dao = ReferenceTestDao::getInstance($this->daoFactory);
        $dao->createTable();
        $reference1 = new ReferenceTest();
        $reference2 = new ReferenceTest();
        $reference3 = new ReferenceTest();

        $reference1->setReference($reference2);
        $reference2->setReference($reference3);

        $dao->create($reference1);

        /**
         * @var $ref ReferenceTest
         */
        $ref = $dao->find($reference1->getId());
        $dao->initializeDeep($ref);
        $this->assertEquals($reference1->getId(),$ref->getId());
        $ref = $ref->getReference();
        $this->assertNotNull($ref);

        $this->assertEquals($reference2->getId(),$ref->getId());
        $ref = $ref->getReference();
        $this->assertNotNull($ref);

        $this->assertEquals($reference3->getId(),$ref->getId());
        $this->assertNull($ref->getReference());
    }

    public function testThatReferencedEntitiesHasCache(){
        $dao = ReferenceTestDao::getInstance($this->daoFactory);
        $dao->createTable();
        $reference1 = new ReferenceTest();
        $reference2 = new ReferenceTest();
        $reference3 = new ReferenceTest();

        $reference1->setReference($reference2);
        $reference2->setReference($reference3);

        $dao->create($reference1);
        $this->assertNotNull($reference1->{Dao::CACHE});
        $this->assertNotNull($reference2->{Dao::CACHE});
        $this->assertNotNull($reference3->{Dao::CACHE});

        /**
         * @var $ref ReferenceTest
         */
        $ref = $dao->find($reference1->getId());
        $this->assertNotNull($ref->{Dao::CACHE});
        $ref = $ref->getReference();
        $this->assertNotNull($ref->{Dao::CACHE});
        $ref = $ref->getReference();
        $this->assertNotNull($ref->{Dao::CACHE});
        $dao->initialize($ref->getReference());
        $ref = $ref->getReference();
        $this->assertNull($ref);
    }


    public function testOneToOneReferencesShouldBeInitializedWhenFetched(){
        $dao = ReferenceTestDao::getInstance($this->daoFactory);
        $dao->createTable();
        $reference1 = new ReferenceTest();
        $reference2 = new ReferenceTest();

        $reference1->setReference($reference2);

        $dao->create($reference1);


        /**
         * @var $ref ReferenceTest
         */
        $ref = $dao->find($reference1->getId());
        $this->assertTrue($dao->isInitialized($ref));
        $this->assertFalse($dao->isInitialized($ref->getReference()));
        $dao->initialize($ref->getReference());
        $this->assertTrue($dao->isInitialized($ref->getReference()));
    }

    public function testOneToManyReferencesShouldBeInitializedWhenFetched(){
        $dao = ReferenceTestDao::getInstance($this->daoFactory);
        $dao->createTable();
        $reference1 = new ReferenceTest();
        $reference2 = new ReferenceTest();
        $reference3 = new ReferenceTest();

        $reference1->setReferences(array($reference2,$reference3));
        $dao->create($reference1);


        /**
         * @var $ref ReferenceTest
         */
        $ref = $dao->find($reference1->getId());
        $this->assertTrue($dao->isInitialized($ref));
        $this->assertFalse($dao->isInitialized($ref->getReferences()));
        $dao->initialize($ref->getReferences());
        $this->assertTrue($dao->isInitialized($ref->getReferences()));
    }
} 