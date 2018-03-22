<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\Bonus\GlobalSales\Lib\Service\Calc\Sub;

use Flancer32\Lib\DataObject;
use Praxigento\Bonus\GlobalSales\Lib\Entity\Cfg\Param;
use Praxigento\BonusBase\Repo\Data\Compress;
use Praxigento\Downline\Repo\Data\Snap;


include_once(__DIR__ . '/../../../../phpunit_bootstrap.php');

class Qualification_UnitTest extends \Praxigento\Core\Test\BaseCase\Mockery {
    /** @var  \Mockery\MockInterface */
    private $mCallDownlineMap;
    /** @var  \Mockery\MockInterface */
    private $mCallDownlineSnap;
    /** @var  \Mockery\MockInterface */
    private $mToolDownlineTree;
    /** @var  Qualification */
    private $sub;

    protected function setUp() {
        parent::setUp();
        $this->mCallDownlineMap = $this->_mock(\Praxigento\Downline\Service\IMap::class);
        $this->mCallDownlineSnap = $this->_mock(\Praxigento\Downline\Service\ISnap::class);
        $this->mToolDownlineTree = $this->_mock(\Praxigento\Downline\Api\Helper\Downline::class);
        $this->sub = new Qualification(
            $this->mCallDownlineMap,
            $this->mCallDownlineSnap,
            $this->mToolDownlineTree
        );
    }

    /**
     * Tree:
     * 1 -> (2, 3)
     * 2 -> (4)
     * 4 -> (5)
     */
    public function test_calcParams() {
        /** === Test Data === */
        $CUST_1 = 1;
        $CUST_2 = 2;
        $CUST_3 = 3;
        $CUST_4 = 4;
        $CUST_5 = 5;
        $RANK_1 = 11;
        $RANK_2 = 22;
        $RANK_3 = 33;
        $TREE = [ ];
        $Q_DATA = [ $CUST_1 => 400, $CUST_2 => 300, $CUST_3 => 200, $CUST_4 => 100, $CUST_5 => 50 ];
        $CFG_PARAMS = [
            $RANK_1 = [ Param::ATTR_LEG_MAX_PERCENT => 0.80, Param::ATTR_GV => 1 ]
        ];
        $GV_MAX_LEVELS = 2;
        $TREE_EXP = [
            $CUST_1 => [ Snap::ATTR_PATH => '/' ],
            $CUST_2 => [ Snap::ATTR_PATH => '/1/' ],
            $CUST_3 => [ Snap::ATTR_PATH => '/1/' ],
            $CUST_4 => [ Snap::ATTR_PATH => '/1/2/' ],
            $CUST_5 => [ Snap::ATTR_PATH => '/1/2/4/' ]
        ];
        $TREE_DEPTH = [
            3 => [ $CUST_5 ],
            2 => [ $CUST_4 ],
            1 => [ $CUST_2, $CUST_3 ],
            0 => [ $CUST_1 ]
        ];
        $TREE_ID = [
            $CUST_1 => [ Compress::ATTR_ID => 'id' ]
        ];
        /** === Setup Mocks === */
        // $treeExpanded = $this->_expandTree($tree);
        // $resp = $this->_callDownlineSnap->expandMinimal($req);
        $mRespExpand = new DataObject();
        $this->mCallDownlineSnap
            ->shouldReceive('expandMinimal')->once()
            ->andReturn($mRespExpand);
        // return $resp->getSnapData();
        $mRespExpand->setSnapData($TREE_EXP);
        // $mapByDepth = $this->_mapByTreeDepthDesc($treeExpanded);
        // $resp = $this->_callDownlineMap->treeByDepth($req);
        $mRespDepth = new DataObject();
        $this->mCallDownlineMap
            ->shouldReceive('treeByDepth')->once()
            ->andReturn($mRespDepth);
        // return $resp->getMapped();
        $mRespDepth->setMapped($TREE_DEPTH);
        // $mapById = $this->_mapById($tree);
        // $resp = $this->_callDownlineMap->byId($req);
        $mRespId = new DataObject();
        $this->mCallDownlineMap
            ->shouldReceive('byId')->once()
            ->andReturn($mRespId);
        // return $resp->getMapped();
        $mRespId->setMapped($TREE_ID);
        // $parents = $this->_toolDownlineTree->getParentsFromPathReversed($path);
        $this->mToolDownlineTree
            ->shouldReceive('getParentsFromPathReversed')->once()
            ->with('/1/2/4/')
            ->andReturn([ 4, 2, 1 ]);
        $this->mToolDownlineTree
            ->shouldReceive('getParentsFromPathReversed')->once()
            ->with('/1/2/')
            ->andReturn([ 2, 1 ]);
        $this->mToolDownlineTree
            ->shouldReceive('getParentsFromPathReversed')->twice()
            ->with('/1/')
            ->andReturn([ 1 ]);
        $this->mToolDownlineTree
            ->shouldReceive('getParentsFromPathReversed')->once()
            ->with('/')
            ->andReturn([ ]);

        /** === Call and asserts  === */
        $this->sub->calcParams($TREE, $Q_DATA, $CFG_PARAMS, $GV_MAX_LEVELS);
    }

}