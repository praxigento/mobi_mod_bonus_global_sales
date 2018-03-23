<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\Bonus\GlobalSales\Lib\Repo\Def;

use Praxigento\Bonus\GlobalSales\Lib\Entity\Cfg\Param;

include_once(__DIR__ . '/../../../phpunit_bootstrap.php');

class Module_UnitTest extends \Praxigento\Core\Test\BaseCase\Mockery
{
    /** @var  \Mockery\MockInterface */
    private $mConn;
    /** @var  \Mockery\MockInterface */
    private $mDba;
    /** @var  \Mockery\MockInterface */
    private $mRepoGeneric;
    /** @var  \Mockery\MockInterface */
    private $mRepoBonusLoyalty;
    /** @var  \Mockery\MockInterface */
    private $mToolPeriod;
    /** @var  Module */
    private $repo;

    protected function setUp()
    {
        parent::setUp();
        $this->markTestSkipped('Test is deprecated after M1 & M2 merge is done.');
        $this->mConn = $this->_mockDba();
        $this->mDba = $this->_mockResourceConnection($this->mConn);
        $this->mRepoGeneric = $this->_mockRepoGeneric($this->mDba);
        $this->mRepoBonusLoyalty = $this->_mock(\Praxigento\BonusLoyalty\Repo\IModule::class);
        $this->mToolPeriod = $this->_mock(\Praxigento\Core\Api\Helper\Period::class);
        $this->repo = new Module(
            $this->mRepoGeneric,
            $this->mRepoBonusLoyalty,
            $this->mToolPeriod
        );
    }

    public function test_getCompressedTreeWithQualifications()
    {
        /** === Test Data === */
        $CALC_ID = 2;
        $RESULT = 'result';

        /** === Setup Mocks === */
        $this->mDba
            ->shouldReceive('getTableName');
        // $query = $conn->select();
        $mQuery = $this->_mockDbSelect();
        $this->mConn
            ->shouldReceive('select')->once()
            ->andReturn($mQuery);
        // $query->...
        $mQuery->shouldReceive('from');
        $mQuery->shouldReceive('joinLeft');
        $mQuery->shouldReceive('where');
        // $result = $conn->fetchAll($query);
        $this->mConn
            ->shouldReceive('fetchAll')
            ->andReturn($RESULT);

        /** === Call and asserts  === */
        $resp = $this->repo->getCompressedTreeWithQualifications($CALC_ID);
        $this->assertEquals($RESULT, $resp);
    }

    public function test_getConfigParams()
    {
        /** === Test Data === */
        $RANK_ID = 2;
        $DATA = [
            [Param::A_RANK_ID => $RANK_ID]
        ];

        /** === Setup Mocks === */
        // $data = $this->_repoBasic->getEntities(Param::ENTITY_NAME, null, null, $order);
        $this->mRepoGeneric
            ->shouldReceive('getEntities')
            ->andReturn($DATA);

        /** === Call and asserts  === */
        $resp = $this->repo->getConfigParams();
        $this->assertEquals($RANK_ID, $resp[$RANK_ID][Param::A_RANK_ID]);
    }

    public function test_getQualificationData()
    {
        /** === Test Data === */
        $DS_BEGIN = 'ds begin';
        $DS_END = 'ds end';
        $RESULT = 'result';

        /** === Setup Mocks === */
        // $result = $this->_repoBonusLoyalty->getQualificationData($dsFrom, $dsTo);
        $this->mRepoBonusLoyalty
            ->shouldReceive('getQualificationData')
            ->with($DS_BEGIN, $DS_END)
            ->andReturn($RESULT);

        /** === Call and asserts  === */
        $resp = $this->repo->getQualificationData($DS_BEGIN, $DS_END);
        $this->assertEquals($RESULT, $resp);
    }

    public function test_getSalesOrdersPvForPeriod()
    {
        /** === Test Data === */
        $DS_BEGIN = 'ds begin';
        $DS_END = 'ds end';
        $RESULT = 'result';
        $TS_FROM = 'ts from';
        $TS_TO = 'ts to';

        /** === Setup Mocks === */
        // $tsFrom = $this->_toolPeriod->getTimestampFrom($dsFrom);
        $this->mToolPeriod
            ->shouldReceive('getTimestampFrom')->once()
            ->with($DS_BEGIN)
            ->andReturn($TS_FROM);
        // $tsTo = $this->_toolPeriod->getTimestampTo($dsTo);
        $this->mToolPeriod
            ->shouldReceive('getTimestampTo')->once()
            ->with($DS_END)
            ->andReturn($TS_TO);
        // $tblPv = $this->_getTableName(PvSale::ENTITY_NAME);
        $this->mDba
            ->shouldReceive('getTableName');
        // $conn->quote(...)
        $this->mConn->shouldReceive('quote');
        // $query = $conn->select();
        $mQuery = $this->_mockDbSelect();
        $this->mConn
            ->shouldReceive('select')->once()
            ->andReturn($mQuery);
        // $query->...
        $mQuery->shouldReceive('from');
        $mQuery->shouldReceive('where');
        // $result = $conn->fetchOne($query);
        $this->mConn
            ->shouldReceive('fetchOne')
            ->andReturn($RESULT);

        /** === Call and asserts  === */
        $resp = $this->repo->getSalesOrdersPvForPeriod($DS_BEGIN, $DS_END);
        $this->assertEquals($RESULT, $resp);
    }

    public function test_saveLogRanks()
    {
        /** === Test Data === */
        $LOGS = [1 => 11, 2 => 22];

        /** === Setup Mocks === */
        $this->mRepoBonusBase
            ->shouldReceive('logRank')->once()
            ->with(1, 11);
        $this->mRepoBonusBase
            ->shouldReceive('logRank')->once()
            ->with(2, 22);

        /** === Call and asserts  === */
        $this->repo->saveLogRanks($LOGS);
    }

    public function test_saveQualificationParams_commit()
    {
        /** === Test Data === */
        $UPDATES = [[]];

        /** === Setup Mocks === */
        $this->mConn->shouldReceive('beginTransaction')->once();
        $this->mRepoGeneric
            ->shouldReceive('addEntity')->once();
        $this->mConn->shouldReceive('commit')->once();

        /** === Call and asserts  === */
        $this->repo->saveQualificationParams($UPDATES);
    }

    /**
     * @expectedException \Exception
     */
    public function test_saveQualificationParams_rollback()
    {
        /** === Test Data === */
        $UPDATES = [[]];

        /** === Setup Mocks === */
        $this->mConn->shouldReceive('beginTransaction')->once();
        $this->mRepoGeneric
            ->shouldReceive('addEntity')->andThrow(new \Exception());
        $this->mConn->shouldReceive('rollBack')->once();

        /** === Call and asserts  === */
        $this->repo->saveQualificationParams($UPDATES);
    }


}