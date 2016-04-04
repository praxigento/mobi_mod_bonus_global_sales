<?php
/**
 * Facade for current module for dependent modules repos.
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\GlobalSales\Lib\Repo\Def;

use Praxigento\Bonus\Base\Lib\Entity\Compress;
use Praxigento\Bonus\Base\Lib\Repo\IModule as BonusBaseRepo;
use Praxigento\Bonus\GlobalSales\Lib\Entity\Cfg\Param;
use Praxigento\Bonus\GlobalSales\Lib\Entity\Qualification;
use Praxigento\Bonus\GlobalSales\Lib\Repo\decimal;
use Praxigento\Bonus\GlobalSales\Lib\Repo\IModule;
use Praxigento\Bonus\Loyalty\Lib\Repo\IModule as BonusLoyaltyRepo;
use Praxigento\Core\Repo\Def\Base;
use Praxigento\Pv\Data\Entity\Sale as PvSale;

class Module extends Base implements IModule
{
    /** @var BonusBaseRepo */
    protected $_repoBonusBase;
    /** @var BonusLoyaltyRepo */
    protected $_repoBonusLoyalty;
    /** @var  \Praxigento\Core\Lib\Tool\Period */
    protected $_toolPeriod;

    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        BonusBaseRepo $repoBonusBase,
        BonusLoyaltyRepo $repoBonusLoyalty,
        \Praxigento\Core\Lib\Tool\Period $toolPeriod
    ) {
        parent::__construct($resource);
        $this->_toolPeriod = $toolPeriod;
        $this->_repoBonusBase = $repoBonusBase;
        $this->_repoBonusLoyalty = $repoBonusLoyalty;
    }

    public function getCompressedTree($calcId)
    {
        $result = $this->_repoBonusBase->getCompressedTree($calcId);
        return $result;
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
        $conn = $this->_getConn();
        /* aliases and tables */
        $asQual = 'pbgq';
        $asCompress = 'pbbc';
        $tblQual = $this->_getTableName(Qualification::ENTITY_NAME);
        $tblCompress = $this->_getTableName(Compress::ENTITY_NAME);
        // SELECT FROM prxgt_bon_globsal_qual pbgq
        $query = $conn->select();
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
        $result = $conn->fetchAll($query);
        return $result;
    }

    public function getConfigParams()
    {
        $result = [];
        $order = Param::ATTR_GV . ' ASC';
        $data = $this->_resource->getEntities(Param::ENTITY_NAME, null, null, $order);
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
        $conn = $this->_getConn();
        /* aliases and tables */
        $asSummary = 'summary';
        $asPv = 'pps';
        $tblPv = $this->_getTableName(PvSale::ENTITY_NAME);
        // SELECT FROM prxgt_pv_sale pps
        $query = $conn->select();
        $query->from([$asPv => $tblPv], [$asSummary => 'SUM(' . PvSale::ATTR_TOTAL . ')']);
        // where
        $whereFrom = $asPv . '.' . PvSale::ATTR_DATE_PAID . '>=' . $conn->quote($tsFrom);
        $whereTo = $asPv . '.' . PvSale::ATTR_DATE_PAID . '<=' . $conn->quote($tsTo);
        $query->where("$whereFrom AND $whereTo");
        // $sql = (string)$query;
        $result = $conn->fetchOne($query);
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
            $this->_repoBonusBase->logRank($transRef, $rankRef);
        }
    }

    public function saveQualificationParams($updates)
    {
        $conn = $this->_getConn();
        $conn->beginTransaction();
        $isCommited = false;
        try {
            foreach ($updates as $item) {
                $this->_resource->addEntity(Qualification::ENTITY_NAME, $item);
            }
            $conn->commit();
            $isCommited = true;
        } finally {
            if (!$isCommited) {
                $conn->rollBack();
            }
        }
    }

    public function updateCalcSetComplete($calcId)
    {
        $result = $this->_repoBonusBase->updateCalcSetComplete($calcId);
        return $result;
    }
}