<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\Bonus\GlobalSales\Lib\Service\Calc\Sub;

use Praxigento\Bonus\GlobalSales\Lib\Entity\Cfg\Param;
use Praxigento\Bonus\GlobalSales\Lib\Entity\Qualification;
use Praxigento\BonusBase\Repo\Entity\Data\Compress;

include_once(__DIR__ . '/../../../../phpunit_bootstrap.php');

class Bonus_UnitTest extends \Praxigento\Core\Test\BaseCase\Mockery {
    /** @var  \Mockery\MockInterface */
    private $mToolFormat;
    /** @var  Bonus */
    private $sub;

    protected function setUp() {
        parent::setUp();
        $this->mToolFormat = $this->_mock(\Praxigento\Core\Api\Helper\Format::class);
        $this->sub = new Bonus(
            $this->mToolFormat
        );
    }

    public function test_calc() {
        /** === Test Data === */
        $RANK_1 = 1;
        $RANK_2 = 2;
        $CUST_1 = 10;
        $CUST_2 = 20;
        $TREE = [
            [
                Compress::ATTR_CUSTOMER_ID  => $CUST_1,
                Qualification::ATTR_GV      => 100,
                Qualification::ATTR_RANK_ID => $RANK_1
            ],
            [
                Compress::ATTR_CUSTOMER_ID  => $CUST_2,
                Qualification::ATTR_GV      => 200,
                Qualification::ATTR_RANK_ID => $RANK_2
            ]
        ];
        $PV_TOTAL = 2100;
        $PARAMS = [
            $RANK_1 => [
                Param::ATTR_RANK_ID     => $RANK_1,
                Param::ATTR_GV          => 100,
                Param::ATTR_PERCENT     => 0.01,
                Param::ATTR_IS_PRORATED => true
            ],
            $RANK_2 => [
                Param::ATTR_RANK_ID     => $RANK_2,
                Param::ATTR_GV          => 200,
                Param::ATTR_PERCENT     => 0.02,
                Param::ATTR_IS_PRORATED => false
            ]
        ];

        /** === Setup Mocks === */
        // $mapAmounts = $this->_mapPayoutsByRank($pvTotal, $params);
        // $bonus = $this->_toolFormat->roundBonus($pvTotal * $percent);
        $this->mToolFormat
            ->shouldReceive('roundBonus')->times(4)
            ->andReturn($PV_TOTAL * 0.01);
        $this->mToolFormat
            ->shouldReceive('roundBonus')->once()
            ->andReturn($PV_TOTAL * 0.02);
        /** === Call and asserts  === */
        $this->sub->calc($TREE, $PV_TOTAL, $PARAMS);
    }

}