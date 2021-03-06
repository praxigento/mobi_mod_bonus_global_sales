<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\Bonus\GlobalSales\Lib\Service\Calc\Sub;

use Praxigento\Bonus\GlobalSales\Lib\Entity\Cfg\Param;
use Praxigento\BonusBase\Repo\Data\Compress;

class Bonus {
    const AS_AMOUNT_TOTAL = 'amountTotal';
    const AS_IS_PRORATED = 'isProrated';
    const AS_MEMBERS = 'members';
    const AS_TOTAL_GV = 'totalGv';
    /** @var  \Praxigento\Core\Api\Helper\Format */
    protected $_toolFormat;

    /**
     * Bonus constructor.
     */
    public function __construct(
        \Praxigento\Core\Api\Helper\Format $toolFormat

    ) {
        $this->_toolFormat = $toolFormat;
    }

    private function _mapPayoutsByRank($pvTotal, $params)
    {
        $result = [];
        foreach ($params as $param) {
            $rankId = $param[Param::A_RANK_ID];
            $percent = $param[Param::A_PERCENT];
            $isProrated = $param[Param::A_IS_PRORATED];
            $bonus = $this->_toolFormat->roundBonus($pvTotal * $percent);
            $result[$rankId][self::AS_AMOUNT_TOTAL] = $bonus;
            $result[$rankId][self::AS_IS_PRORATED] = (bool)$isProrated;
        }
        return $result;
    }

    private function _mapRankMaxGv($params) {
        $result = [ ];
        $prevRank = null;
        foreach($params as $param) {
            $rank = $param[Param::A_RANK_ID];
            $gv = $param[Param::A_GV];
            if(!is_null($prevRank)) {
                $result[$prevRank] = $gv;
            }
            $prevRank = $rank;
        }
        $result[$prevRank] = PHP_INT_MAX;
        return $result;
    }

    /**
     */
    public function calc($tree, $pvTotal, $params) {
        $mapMaxGvPerRank = $this->_mapRankMaxGv($params);
        $mapAmounts = $this->_mapPayoutsByRank($pvTotal, $params);
        /** @var array $mapRanks [$rankId=>[members=>[$custId=>GV, ...], totalGv=>$gv], ...] */
        $mapRanks = [ ];
        foreach($tree as $customer) {
            $custId = $customer[Compress::A_CUSTOMER_ID];
            $gvQual = $customer[\Praxigento\Bonus\GlobalSales\Lib\Entity\Qualification::A_GV];
            $rankQual = $customer[\Praxigento\Bonus\GlobalSales\Lib\Entity\Qualification::A_RANK_ID];
            foreach($params as $rankId => $param) {
                $gvMax = $mapMaxGvPerRank[$rankId];
                $gv = ($gvQual > $gvMax) ? $gvMax : $gvQual;
                $mapRanks[$rankId][self::AS_MEMBERS][$custId] = $gv;
                if(!isset($mapRanks[$rankId][self::AS_TOTAL_GV])) {
                    $mapRanks[$rankId][self::AS_TOTAL_GV] = 0;
                }
                $mapRanks[$rankId][self::AS_TOTAL_GV] += $gv;
                /* break the loop if this is the MAX qualified rank */
                if($rankId == $rankQual) {
                    break;
                }
            }
        }
        $result = [ ];
        foreach($mapRanks as $rankId => $data) {
            $gvTotal = $data[self::AS_TOTAL_GV];
            $members = $data[self::AS_MEMBERS];
            $membersCount = count($members);
            $amountTotal = $mapAmounts[$rankId][self::AS_AMOUNT_TOTAL];
            $isProrated = $mapAmounts[$rankId][self::AS_IS_PRORATED];
            if($isProrated) {
                /* share pro-rated */
                foreach($members as $custId => $gv) {
                    $bonusProRated = $this->_toolFormat->roundBonus($amountTotal / $gvTotal * $gv);
                    $result[$custId][$rankId] = $bonusProRated;
                }
            } else {
                /* share equally */
                $bonusEqual = $this->_toolFormat->roundBonus($amountTotal / $membersCount);
                foreach($members as $custId => $gv) {
                    $result[$custId][$rankId] = $bonusEqual;
                }
            }
        }

        return $result;
    }
}