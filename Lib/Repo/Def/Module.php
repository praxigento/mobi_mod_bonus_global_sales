<?php
/**
 * Facade for current module for dependent modules repos.
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\GlobalSales\Lib\Repo\Def;

use Praxigento\Bonus\GlobalSales\Lib\Entity\Cfg\Param;
use Praxigento\Bonus\GlobalSales\Lib\Entity\Qualification;
use Praxigento\Bonus\GlobalSales\Lib\Repo\decimal;
use Praxigento\Bonus\GlobalSales\Lib\Repo\IModule;
use Praxigento\BonusBase\Repo\Data\Compress;
use Praxigento\BonusLoyalty\Repo\IModule as BonusLoyaltyRepo;
use Praxigento\Core\App\Repo\Db;
use Praxigento\Pv\Repo\Data\Sale as PvSale;

class Module extends Db implements IModule
{
    /** @var \Praxigento\Core\Api\App\Repo\Transaction\Manager */
    protected $_manTrans;
    /** @var \Praxigento\Core\Api\App\Repo\Generic */
    protected $_repoBasic;
    /** @var \Praxigento\BonusBase\Repo\Dao\Log\Rank */
    protected $_repoBonusLogRank;
    /** @var BonusLoyaltyRepo */
    protected $_repoBonusLoyalty;
    /** @var \Praxigento\Core\Api\Helper\Period */
    protected $_toolPeriod;

    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\Core\Api\App\Repo\Transaction\Manager $manTrans,
        \Praxigento\Core\Api\App\Repo\Generic $daoBasic,
        BonusLoyaltyRepo $daoBonusLoyalty,
        \Praxigento\BonusBase\Repo\Dao\Log\Rank $daoBonusLogRank,
        \Praxigento\Core\Api\Helper\Period $toolPeriod
    ) {
        parent::__construct($resource);
        $this->_manTrans = $manTrans;
        $this->_repoBasic = $daoBasic;
        $this->_repoBonusLoyalty = $daoBonusLoyalty;
        $this->_repoBonusLogRank = $daoBonusLogRank;
        $this->_toolPeriod = $toolPeriod;
    }

    /**
     * SELECT
     * pbbc.customer_id,
     * pbbc.parent_id,
     * pbgq.gv,
     * pbgq.rank_id
     * FROM prxgt_bon_globsal_qual pbgq
     * LEFT JOIN prxgt_bon_base_compress pbbc
     * ON pbbc.id = pbgq.compress_id
     * WHERE pbbc.calc_id = 1
     *
     * @param $calcId
     */
    public function getCompressedTreeWithQualifications($calcId)
    {
        /* aliases and tables */
        $asQual = 'pbgq';
        $asCompress = 'pbbc';
        $tblQual = $this->resource->getTableName(Qualification::ENTITY_NAME);
        $tblCompress = $this->resource->getTableName(Compress::ENTITY_NAME);
        // SELECT FROM prxgt_bon_globsal_qual pbgq
        $query = $this->conn->select();
        $query->from([$asQual => $tblQual], [Qualification::A_GV, Qualification::A_RANK_ID]);
        // LEFT JOIN prxgt_bon_base_compress pbbc ON pbbc.id = pbgq.compress_id
        $on = "$asCompress." . Compress::A_ID . "=$asQual." . Qualification::A_COMPRESS_ID;
        $cols = [
            Compress::A_CUSTOMER_ID,
            Compress::A_PARENT_ID
        ];
        $query->joinLeft([$asCompress => $tblCompress], $on, $cols);
        // where
        $where = $asCompress . '.' . Compress::A_CALC_ID . '=' . (int)$calcId;
        $query->where($where);
        // $sql = (string)$query;
        $result = $this->conn->fetchAll($query);
        return $result;
    }

    public function getConfigParams()
    {
        $result = [];
        $order = Param::A_GV . ' ASC';
        $data = $this->_repoBasic->getEntities(Param::ENTITY_NAME, null, null, $order);
        foreach ($data as $item) {
            $rankId = $item[Param::A_RANK_ID];
            $result[$rankId] = $item;
        }
        return $result;
    }

    function getQualificationData($dsFrom, $dsTo)
    {
        $result = $this->_repoBonusLoyalty->getQualificationData($dsFrom, $dsTo);
        return $result;
    }

    /**
     * SELECT
     * SUM(pps.total)
     * FROM `prxgt_pv_sale` AS `pps`
     * WHERE (pps.date_paid >= '2016-01-01 08:00:00')
     * AND (pps.date_paid <= '2017-01-01 07:59:59')
     *
     * @param string $dsFrom
     * @param string $dsTo
     */
    function getSalesOrdersPvForPeriod($dsFrom, $dsTo)
    {
        $tsFrom = $this->_toolPeriod->getTimestampFrom($dsFrom);
        $tsTo = $this->_toolPeriod->getTimestampTo($dsTo);
        /* aliases and tables */
        $asSummary = 'summary';
        $asPv = 'pps';
        $tblPv = $this->resource->getTableName(PvSale::ENTITY_NAME);
        // SELECT FROM prxgt_pv_sale pps
        $query = $this->conn->select();
        $query->from([$asPv => $tblPv], [$asSummary => 'SUM(' . PvSale::A_TOTAL . ')']);
        // where
        $whereFrom = $asPv . '.' . PvSale::A_DATE_PAID . '>=' . $this->conn->quote($tsFrom);
        $whereTo = $asPv . '.' . PvSale::A_DATE_PAID . '<=' . $this->conn->quote($tsTo);
        $query->where("$whereFrom AND $whereTo");
        // $sql = (string)$query;
        $result = $this->conn->fetchOne($query);
        return $result;
    }

    public function saveLogRanks($logs)
    {
        foreach ($logs as $transRef => $rankRef) {
            $data = [
                \Praxigento\BonusBase\Repo\Data\Log\Rank::A_TRANS_REF => $transRef,
                \Praxigento\BonusBase\Repo\Data\Log\Rank::A_RANK_REF => $rankRef
            ];
            $this->_repoBonusLogRank->create($data);
        }
    }

    public function saveQualificationParams($updates)
    {
        $def = $this->_manTrans->begin();
        try {
            foreach ($updates as $item) {
                $this->_repoBasic->addEntity(Qualification::ENTITY_NAME, $item);
            }
            $this->_manTrans->commit($def);
        } finally {
            $this->_manTrans->end($def);
        }
    }

}