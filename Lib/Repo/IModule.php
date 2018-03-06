<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\Bonus\GlobalSales\Lib\Repo;

interface IModule
{

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
     * Decorator for \Praxigento\BonusLoyalty\Repo\Def\Module::getQualificationData
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

}