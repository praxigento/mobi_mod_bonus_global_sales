<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\Bonus\GlobalSales\Lib\Repo;

interface IModule
{

    /**
     * Decorator for \Praxigento\BonusBase\Lib\Repo\IModule::getCompressedTree
     *
     * @param $calcId
     *
     * @return array [[Compress/*], ...]
     */
    public function getCompressedTree($calcId);

    /**
     * Get compressed tree with qualification data (GV, rank).
     *
     * @param $calcId
     *
     * @return array [[Compress/customerId+parentId, Qualification/gv+rank_id], ...]
     */
    public function getCompressedTreeWithQualifications($calcId);

    /**
     * Get configuration parameters ordered by rank asc
     * @return array [$rankId => [Cfg\Param/*], ...]
     */
    public function getConfigParams();

    /**
     * Adapter for \Praxigento\BonusBase\Lib\Repo\Def\Module::getCalcsForPeriod
     *
     * @param int $calcTypeId
     * @param string $dsBegin 'YYYYMMDD'
     * @param string $dsEnd 'YYYYMMDD'
     *
     * @return array [Calculation/*]
     */
    public function getLatestCalcForPeriod($calcTypeId, $dsBegin, $dsEnd);

    /**
     * Decorator for \Praxigento\Bonus\Loyalty\Lib\Repo\Def\Module::getQualificationData
     *
     * @param string $dsFrom 'YYYYMMDD'
     * @param string $dsTo 'YYYYMMDD'
     *
     * @return array [$custId => $pvSummary, ...]
     */
    function getQualificationData($dsFrom, $dsTo);

    /**
     * Get summary PV for all orders for period.
     *
     * @param string $dsFrom 'YYYYMMDD'
     * @param string $dsTo 'YYYYMMDD'
     *
     * @return decimal
     */
    function getSalesOrdersPvForPeriod($dsFrom, $dsTo);

    /**
     * Decorator for \Praxigento\BonusBase\Lib\Repo\IModule::getTypeCalcIdByCode
     *
     * @param string $calcTypeCode
     *
     * @return int
     */
    public function getTypeCalcIdByCode($calcTypeCode);

    /**
     * Save relations between transactions and ranks into log.
     *
     * @param array $logs
     */
    public function saveLogRanks($logs);

    /**
     * @param $updates array [[Qualification/*], ...]
     *
     */
    public function saveQualificationParams($updates);

    /**
     * Decorator for \Praxigento\BonusBase\Lib\Repo\IModule::updateCalcSetComplete
     *
     * @param $calcId
     */
    public function updateCalcSetComplete($calcId);

}