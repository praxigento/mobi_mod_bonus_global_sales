<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\GlobalSales\Lib\Test\Story01;

use Praxigento\Accounting\Data\Entity\Account;
use Praxigento\Bonus\GlobalSales\Lib\Entity\Cfg\Param;
use Praxigento\Bonus\GlobalSales\Lib\Entity\Qualification;
use Praxigento\Bonus\GlobalSales\Lib\Service\Calc\Request\Bonus as GlobalSalesCalcBonusRequest;
use Praxigento\Bonus\GlobalSales\Lib\Service\Calc\Request\Qualification as GlobalSalesCalcQualificationRequest;
use Praxigento\BonusBase\Data\Entity\Calculation;
use Praxigento\BonusBase\Data\Entity\Compress;
use Praxigento\BonusBase\Data\Entity\Rank;
use Praxigento\BonusGlobalSales\Config as Cfg;
use Praxigento\BonusLoyalty\Service\Calc\Request\Compress as LoyaltyCalcCompressRequest;
use Praxigento\Core\Test\BaseIntegrationTest;
use Praxigento\Pv\Data\Entity\Sale as PvSale;
use Praxigento\Pv\Service\Sale\Request\AccountPv as PvSaleAccountPvRequest;

include_once(__DIR__ . '/../phpunit_bootstrap.php');

class Main_IntegrationTest extends BaseIntegrationTest
{
    const DS_DOWNLINE_SNAP_UP_TO = '20160201';
    const DS_ORDERS_CREATED = '20160115';
    const DS_PERIOD_BEGIN = '20160101';
    const MAX_PERCENT_FROM_ONE_LEG = 0.60;
    const RANK_EQUAL = 'RANK_EQUAL';
    const RANK_MAX = 'RANK_MAX';
    const RANK_PRORATED = 'RANK_PRORATED';
    /**
     * Hardcoded data for orders: [$custNdx => $pv, ...]
     * @var array
     */
    private $DEF_ORDERS = [
        1 => 5000,
        2 => 500,
        3 => 5000,
        4 => 120,
        5 => 200,
        6 => 200,
        7 => null,
        8 => 200,
        9 => 200,
        10 => null,
        11 => 200,
        12 => 120,
        13 => 100
    ];
    /** @var \Praxigento\Bonus\GlobalSales\Lib\Service\ICalc */
    private $_callGlobalSalesCalc;
    /** @var \Praxigento\BonusLoyalty\Service\ICalc */
    private $_callLoyaltyCalc;
    /** @var  \Praxigento\Pv\Service\ISale */
    private $_callPvSale;
    /** @var   \Praxigento\Accounting\Repo\IModule */
    private $_repoAcc;
    /** @var \Praxigento\BonusBase\Repo\Entity\Rank */
    private $_repoBonusRank;
    /** @var  \Praxigento\Accounting\Repo\Entity\Type\Asset */
    private $_repoTypeAsset;
    /** @var \Praxigento\Core\Repo\IGeneric */
    private $repoBasic;

    public function __construct()
    {
        parent::__construct();
        $this->_callGlobalSalesCalc = $this->_manObj->get(\Praxigento\Bonus\GlobalSales\Lib\Service\ICalc::class);
        $this->_callLoyaltyCalc = $this->_manObj->get(\Praxigento\BonusLoyalty\Service\ICalc::class);
        $this->_callPvSale = $this->_manObj->get(\Praxigento\Pv\Service\ISale::class);
        $this->repoBasic = $this->_manObj->get(\Praxigento\Core\Repo\IGeneric::class);
        $this->_repoTypeAsset = $this->_manObj->get(\Praxigento\Accounting\Repo\Entity\Type\Asset::class);
        $this->_repoBonusRank = $this->_manObj->get(\Praxigento\BonusBase\Repo\Entity\Rank::class);
        $this->_repoAcc = $this->_manObj->get(\Praxigento\Accounting\Repo\IModule::class);
    }

    private function _calcBonus()
    {
        $req = new GlobalSalesCalcBonusRequest();
        $resp = $this->_callGlobalSalesCalc->bonus($req);
        $this->assertTrue($resp->isSucceed());
        $calcId = $resp->getCalcId();
        /* validate calculation state  */
        $data = $this->repoBasic->getEntityByPk(Calculation::ENTITY_NAME, [Calculation::ATTR_ID => $calcId]);
        $this->assertEquals(Cfg::CALC_STATE_COMPLETE, $data[Calculation::ATTR_STATE]);
        return $calcId;
    }

    private function _calcCompression()
    {
        $req = new LoyaltyCalcCompressRequest();
        $resp = $this->_callLoyaltyCalc->compress($req);
        $this->assertTrue($resp->isSucceed());
        $calcId = $resp->getCalcId();
        /* validate calculation state  */
        $data = $this->repoBasic->getEntityByPk(Calculation::ENTITY_NAME, [Calculation::ATTR_ID => $calcId]);
        $this->assertEquals(Cfg::CALC_STATE_COMPLETE, $data[Calculation::ATTR_STATE]);
        return $calcId;
    }

    private function _calcQualification()
    {
        $req = new GlobalSalesCalcQualificationRequest();
        $req->setGvMaxLevels(2);
        $resp = $this->_callGlobalSalesCalc->qualification($req);
        $this->assertTrue($resp->isSucceed());
        $calcId = $resp->getCalcId();
        /* validate calculation state  */
        $data = $this->repoBasic->getEntityByPk(Calculation::ENTITY_NAME, [Calculation::ATTR_ID => $calcId]);
        $this->assertEquals(Cfg::CALC_STATE_COMPLETE, $data[Calculation::ATTR_STATE]);
        return $calcId;
    }

    private function _createOrders()
    {
        foreach ($this->DEF_ORDERS as $custNdx => $pv) {
            if (is_null($pv)) {
                continue;
            }
            $custId = $this->_mapCustomerMageIdByIndex[$custNdx];
            $ts = $this->_toolPeriod->getTimestampTo(self::DS_ORDERS_CREATED);
            $bindOrder = [
                Cfg::E_SALE_ORDER_A_CUSTOMER_ID => $custId,
                Cfg::E_SALE_ORDER_A_BASE_GRAND_TOTAL => $pv,
                Cfg::E_SALE_ORDER_A_CREATED_AT => $ts,
                Cfg::E_SALE_ORDER_A_UPDATED_AT => $ts
            ];
            $orderId = $this->repoBasic->addEntity(Cfg::ENTITY_MAGE_SALES_ORDER, $bindOrder);
            $bindPv = [
                PvSale::ATTR_SALE_ID => $orderId,
                PvSale::ATTR_SUBTOTAL => $pv,
                PvSale::ATTR_TOTAL => $pv,
                PvSale::ATTR_DATE_PAID => $ts
            ];
            $this->repoBasic->addEntity(PvSale::ENTITY_NAME, $bindPv);
            $this->_logger->debug("New PV sale on $pv PV paid at $ts is registered for order #$orderId and customer #$custId .");
            $this->_createPvTransaction($custId, $orderId, $ts);
        }
    }

    /**
     * Register PV transaction for sale order.
     */
    private function _createPvTransaction($custId, $orderId, $dateApplied)
    {
        $req = new PvSaleAccountPvRequest();
        $req->setCustomerId($custId);
        $req->setSaleOrderId($orderId);
        $req->setDateApplied($dateApplied);
        $resp = $this->_callPvSale->accountPv($req);
        if ($resp->isSucceed()) {
            $this->_logger->debug("New PV transaction is registered for order #$orderId and customer #$custId .");
        }
    }

    private function _repoGetBalances()
    {
        $assetTypeId = $this->_repoTypeAsset->getIdByCode(Cfg::CODE_TYPE_ASSET_WALLET_ACTIVE);
        $where = Account::ATTR_ASSET_TYPE_ID . '=' . (int)$assetTypeId;
        $result = $this->repoBasic->getEntities(Account::ENTITY_NAME, null, $where);
        return $result;
    }

    /**
     * SELECT
     * pbbc.customer_id,
     * pbbc.parent_id,
     * pbgq.*
     * FROM prxgt_bon_base_compress pbbc
     * LEFT JOIN prxgt_bon_globsal_qual pbgq
     * ON pbbc.id = pbgq.compress_id
     * WHERE pbbc.calc_id = 1
     *
     * @param $calcId
     */
    private function _repoGetQualificationData($calcId)
    {
        /* aliases and tables */
        $asCompress = 'pbbc';
        $asQual = 'pbgq';
        $tblCompress = $this->_resource->getTableName(Compress::ENTITY_NAME);
        $tblQual = $this->_resource->getTableName(Qualification::ENTITY_NAME);
        /* SELECT  FROM prxgt_bon_base_compress pbbc */
        $query = $this->_conn->select();
        $query->from([$asCompress => $tblCompress], [Compress::ATTR_CUSTOMER_ID]);
        /* LEFT JOIN prxgt_bon_loyal_qual pblq ON pbbc.id = pblq.compress_id */
        $on = $asCompress . '.' . Compress::ATTR_ID . "=$asQual." . Qualification::ATTR_COMPRESS_ID;
        $cols = '*';
        $query->joinLeft([$asQual => $tblQual], $on, $cols);
        /* where  */
        $where = $asCompress . '.' . Compress::ATTR_CALC_ID . '=' . (int)$calcId;
        $query->where($where);
        // $sql = (string)$query;
        $result = $this->_conn->fetchAll($query);
        return $result;
    }

    private function _setParams()
    {
        $PARAMS = [
            self::RANK_EQUAL => [
                Param::ATTR_GV => 500,
                Param::ATTR_LEG_MAX_PERCENT => 0.6,
                Param::ATTR_IS_PRORATED => false,
                Param::ATTR_PERCENT => 0.01
            ],
            self::RANK_PRORATED => [
                Param::ATTR_GV => 1000,
                Param::ATTR_LEG_MAX_PERCENT => 0.6,
                Param::ATTR_IS_PRORATED => true,
                Param::ATTR_PERCENT => 0.02
            ],
            self::RANK_MAX => [
                Param::ATTR_GV => 1100,
                Param::ATTR_LEG_MAX_PERCENT => 1,
                Param::ATTR_IS_PRORATED => true,
                Param::ATTR_PERCENT => 0.03
            ]
        ];
        foreach ($PARAMS as $rank => $bind) {
            $rankId = $this->_repoBonusRank->getIdByCode($rank);
            $bind [Param::ATTR_RANK_ID] = $rankId;
            $this->repoBasic->addEntity(Param::ENTITY_NAME, $bind);
        }
        $this->_logger->debug("Configuration parameters for Global Sales bonus are set.");
    }

    private function _setRanks()
    {
        $this->repoBasic->addEntity(Rank::ENTITY_NAME, [
            Rank::ATTR_CODE => self::RANK_MAX,
            Rank::ATTR_NOTE => 'Maximal rank.'
        ]);
        $this->repoBasic->addEntity(Rank::ENTITY_NAME, [
            Rank::ATTR_CODE => self::RANK_EQUAL,
            Rank::ATTR_NOTE => 'This rank has equal sharing of the bonus.'
        ]);
        $this->repoBasic->addEntity(Rank::ENTITY_NAME, [
            Rank::ATTR_CODE => self::RANK_PRORATED,
            Rank::ATTR_NOTE => 'This rank has prorated sharing of the bonus.'
        ]);
        $this->_logger->debug("Ranks for Global Sales bonus are set.");
    }

    private function _validateBonus($calcId)
    {
        $EXP_COUNT = 3; // 4 customers + 1 representative
        $EXP_REPR = -710.4000;
        /* [$custNdx => [$pv, $gv, $psaa], ... ] */
        $EXP_TREE = [
            1 => 537.2700,
            3 => 173.1300
        ];
        $data = $this->_repoGetBalances();
        $this->assertEquals($EXP_COUNT, count($data));
        foreach ($data as $item) {
            $custId = $item[Account::ATTR_CUST_ID];
            $balance = $item[Account::ATTR_BALANCE];
            if ($balance < 0) {
                /* representative */
                $this->assertEquals($EXP_REPR, $balance);
            } else {
                $custNdx = $this->_mapCustomerIndexByMageId[$custId];
                $this->assertEquals($EXP_TREE[$custNdx], $balance);
            }
        }
    }

    private function _validateCompression($calcId)
    {
        $EXP_COUNT = 11;
        $EXP_TREE = [
            1 => 1,
            2 => 1,
            3 => 1,
            4 => 2,
            5 => 2,
            6 => 3,
            8 => 6,
            9 => 6,
            11 => 3,
            12 => 3,
            13 => 3
        ];
        $where = Compress::ATTR_CALC_ID . '=' . $calcId;
        $data = $this->repoBasic->getEntities(Compress::ENTITY_NAME, null, $where);
        $this->assertEquals($EXP_COUNT, count($data));
        foreach ($data as $item) {
            $custId = $item[Compress::ATTR_CUSTOMER_ID];
            $parentId = $item[Compress::ATTR_PARENT_ID];
            $custNdx = $this->_mapCustomerIndexByMageId[$custId];
            $parentNdx = $this->_mapCustomerIndexByMageId[$parentId];
            $this->assertEquals($EXP_TREE[$custNdx], $parentNdx);
        }
    }

    private function _validateQualification($calcId)
    {
        $EXP_COUNT = 11;
        /* [$custNdx => [$pv, $gv, $psaa], ... ] */
        $EXP_TREE = [
            1 => [1920, self::RANK_MAX],
            2 => [0, 0],
            3 => [1020, self::RANK_PRORATED],
            4 => [0, 0],
            5 => [0, 0],
            6 => [0, 0],
            8 => [0, 0],
            9 => [0, 0],
            11 => [0, 0],
            12 => [0, 0],
            13 => [0, 0]
        ];
        $data = $this->_repoGetQualificationData($calcId);
        $this->assertEquals($EXP_COUNT, count($data));
        foreach ($data as $item) {
            $custId = $item[Compress::ATTR_CUSTOMER_ID];
            $gv = +$item[Qualification::ATTR_GV];
            $rankId = +$item[Qualification::ATTR_RANK_ID];
            $custNdx = $this->_mapCustomerIndexByMageId[$custId];
            $this->assertEquals($EXP_TREE[$custNdx][0], $gv);
            $rankIdExp = ($EXP_TREE[$custNdx][1]) ? $this->_repoBonusRank->getIdByCode($EXP_TREE[$custNdx][1]) : 0;
            $this->assertEquals($rankIdExp, $rankId);
        }
    }

    public function test_main()
    {
        $this->_logger->debug('Story01 in Global Sales Bonus Integration tests is started.');
        $this->_conn->beginTransaction();
        try {
            /* set up configuration parameters */
            $this->_setRanks();
            $this->_setParams();
            /* create customers and orders */
            $this->_createMageCustomers(13);
            $this->_createDownlineCustomers(self::DS_PERIOD_BEGIN, true);
            $this->_createDownlineSnapshots(self::DS_DOWNLINE_SNAP_UP_TO);
            $this->_createOrders();
            /* compress downline tree for the bonus */
            $calcIdCompress = $this->_calcCompression();
            $this->_validateCompression($calcIdCompress);
            /* calculate qualification parameters (GV & Rank) */
            $this->_calcQualification();
            $this->_validateQualification($calcIdCompress);
            /* calculate bonus */
            $calcIdBonus = $this->_calcBonus();
            $this->_validateBonus($calcIdBonus);
        } finally {
            // $this->_conn->commit();
            $this->_conn->rollBack();
        }
        $this->_logger->debug('Story01 in Global Sales Bonus Integration tests is completed, all transactions are rolled back.');
    }
}