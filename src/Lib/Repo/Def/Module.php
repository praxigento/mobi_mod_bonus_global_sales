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
use Praxigento\BonusBase\Data\Entity\Compress;
use Praxigento\BonusBase\Repo\IModule as BonusBaseRepo;
use Praxigento\BonusLoyalty\Repo\IModule as BonusLoyaltyRepo;
use Praxigento\Core\Repo\Def\Db;
use Praxigento\Pv\Data\Entity\Sale as PvSale;

class Module extends Db implements IModule
{
    /** @var \Praxigento\Core\Transaction\Database\IManager */
    protected $_manTrans;
    /** @var \Praxigento\Core\Repo\IGeneric */
    protected $_repoBasic;
    /** @var BonusBaseRepo */
    protected $_repoBonusBase;
    /** @var \Praxigento\BonusBase\Repo\Entity\Log\IRank */
    protected $_repoBonusLogRank;
    /** @var BonusLoyaltyRepo */
    protected $_repoBonusLoyalty;
    /** @var \Praxigento\Core\Tool\IPeriod */
    protected $_toolPeriod;

    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\Core\Transaction\Database\IManager $manTrans,
        \Praxigento\Core\Repo\IGeneric $repoBasic,
        BonusBaseRepo $repoBonusBase,
        BonusLoyaltyRepo $repoBonusLoyalty,
        \Praxigento\BonusBase\Repo\Entity\Log\IRank $repoBonusLogRank,
        \Praxigento\Core\Tool\IPeriod $toolPeriod
    ) {
        parent::__construct($resource);
        $this->_manTrans = $manTrans;
        $this->_repoBasic = $repoBasic;
        $this->_repoBonusBase = $repoBonusBase;
        $this->_repoBonusLoyalty = $repoBonusLoyalty;
        $this->_repoBonusLogRank = $repoBonusLogRank;
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
        $tblQual = $this->_resource->getTableName(Qualification::ENTITY_NAME);
        $tblCompress = $this->_resource->getTableName(Compress::ENTITY_NAME);
        // SELECT FROM prxgt_bon_globsal_qual pbgq
        $query = $this->_conn->select();
        $query->from([$asQual => $tblQual], [Qualification::ATTR_GV, Qualification::ATTR_RANK_ID]);
        // LEFT JOIN prxgt_bon_base_compress pbbc ON pbbc.id = pbgq.compress_id
        $on = "$asCompress." . Compress::ATTR_ID . "=$asQual." . Qualification::ATTR_COMPRESS_ID;
        $cols = [
            Compress::ATTR_CUSTOMER_ID,
            Compress::ATTR_PARENT_ID
        ];
        $query->joinLeft([$asCompress => $tblCompress], $on, $cols);
        // where
        $where = $asCompress . '.' . Compress::ATTR_CALC_ID . '=' . (int)$calcId;
        $query->where($where);
        // $sql = (string)$query;
        $result = $this->_conn->fetchAll($query);
        return $result;
    }

    public function getConfigParams()
    {
        $result = [];
        $order = Param::ATTR_GV . ' ASC';
        $data = $this->_repoBasic->getEntities(Param::ENTITY_NAME, null, null, $order);
        foreach ($data as $item) {
            $rankId = $item[Param::ATTR_RANK_ID];
            $result[$rankId] = $item;
        }
        return $result;
    }

    public function getLatestCalcForPeriod($calcTypeId, $dsBegin, $dsEnd)
    {
        $shouldGetLatestCalc = true;
        $result = $this->_repoBonusBase->getCalcsForPeriod($calcTypeId, $dsBegin, $dsEnd, $shouldGetLatestCalc);
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
        $tblPv = $this->_resource->getTableName(PvSale::ENTITY_NAME);
        // SELECT FROM prxgt_pv_sale pps
        $query = $this->_conn->select();
        $query->from([$asPv => $tblPv], [$asSummary => 'SUM(' . PvSale::ATTR_TOTAL . ')']);
        // where
        $whereFrom = $asPv . '.' . PvSale::ATTR_DATE_PAID . '>=' . $this->_conn->quote($tsFrom);
        $whereTo = $asPv . '.' . PvSale::ATTR_DATE_PAID . '<=' . $this->_conn->quote($tsTo);
        $query->where("$whereFrom AND $whereTo");
        // $sql = (string)$query;
        $result = $this->_conn->fetchOne($query);
        return $result;
    }

    public function getTypeCalcIdByCode($calcTypeCode)
    {
        $result = $this->_repoBonusBase->getTypeCalcIdByCode($calcTypeCode);
        return $result;
    }

    public function saveLogRanks($logs)
    {
        foreach ($logs as $transRef => $rankRef) {
            $data = [
                \Praxigento\BonusBase\Data\Entity\Log\Rank::ATTR_TRANS_REF => $transRef,
                \Praxigento\BonusBase\Data\Entity\Log\Rank::ATTR_RANK_REF => $rankRef
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