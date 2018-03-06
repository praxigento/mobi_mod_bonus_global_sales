<?php
/**
 * This qualifier is used in \Praxigento\BonusBase\Service\ICompress::qualifyByUserData operation.
 *
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\Bonus\GlobalSales\Lib\Service\Calc\Sub;

use Praxigento\Bonus\GlobalSales\Lib\Entity\Cfg\Param;
use Praxigento\Bonus\GlobalSales\Lib\Entity\Qualification as EntityQual;
use Praxigento\BonusBase\Repo\Entity\Data\Compress;
use Praxigento\Downline\Repo\Entity\Data\Snap;
use Praxigento\Downline\Service\Map\Request\ById as DownlineMapByIdRequest;
use Praxigento\Downline\Service\Map\Request\TreeByDepth as DownlineMapTreeByDepthRequest;
use Praxigento\Downline\Service\Snap\Request\ExpandMinimal as DownlineSnapExtendMinimalRequest;

class Qualification {

    /** @var  \Praxigento\Downline\Service\IMap */
    protected $_callDownlineMap;
    /** @var   \Praxigento\Downline\Service\ISnap */
    protected $_callDownlineSnap;
    /** @var \Praxigento\Downline\Api\Helper\Downline */
    protected $_toolDownlineTree;

    public function __construct(
        \Praxigento\Downline\Service\IMap $callDownlineMap,
        \Praxigento\Downline\Service\ISnap $callDownlineSnap,
        \Praxigento\Downline\Api\Helper\Downline $toolDownlineTree
    ) {
        $this->_callDownlineMap = $callDownlineMap;
        $this->_callDownlineSnap = $callDownlineSnap;
        $this->_toolDownlineTree = $toolDownlineTree;
    }

    private function _expandTree($data) {
        $req = new DownlineSnapExtendMinimalRequest();
        $req->setKeyCustomerId(Compress::ATTR_CUSTOMER_ID);
        $req->setKeyParentId(Compress::ATTR_PARENT_ID);
        $req->setTree($data);
        $resp = $this->_callDownlineSnap->expandMinimal($req);
        return $resp->getSnapData();
    }

    private function _mapById($tree) {
        $req = new DownlineMapByIdRequest();
        $req->setDataToMap($tree);
        $req->setAsId(Compress::ATTR_CUSTOMER_ID);
        $resp = $this->_callDownlineMap->byId($req);
        return $resp->getMapped();
    }

    private function _mapByTreeDepthDesc($tree) {
        $req = new DownlineMapTreeByDepthRequest();
        $req->setDataToMap($tree);
        $req->setAsCustomerId(Compress::ATTR_CUSTOMER_ID);
        $req->setAsDepth(Snap::ATTR_DEPTH);
        $req->setShouldReversed(true);
        $resp = $this->_callDownlineMap->treeByDepth($req);
        return $resp->getMapped();
    }

    public function calcParams($tree, $qData, $cfgParams, $gvMaxLevels) {
        $treeExpanded = $this->_expandTree($tree);
        $mapByDepth = $this->_mapByTreeDepthDesc($treeExpanded);
        $mapById = $this->_mapById($tree);
        /* GV tree contains GV by legs for every customer: [$custId=>[$legCustId=>$gv, ...], ...] */
        $gvTree = [ ];
        foreach($mapByDepth as $depth => $level) {
            foreach($level as $custId) {
                /* init node if not exist */
                if(!isset($gvTree[$custId])) {
                    $gvTree[$custId] = [ ];
                }
                $pv = $qData[$custId];
                $path = $treeExpanded[$custId][Snap::ATTR_PATH];
                $parents = $this->_toolDownlineTree->getParentsFromPathReversed($path);
                $lvl = 1;
                $legId = $custId;
                foreach($parents as $parentId) {
                    /* break on max level for GV calculation is exceeded */
                    if($lvl > $gvMaxLevels) {
                        break;
                    }
                    /* init node if not exist */
                    if(!isset($gvTree[$parentId])) {
                        $gvTree[$parentId] = [ ];
                    }
                    /* add current PV to the parent on the current leg */
                    if(isset($gvTree[$parentId][$legId])) {
                        $gvTree[$parentId][$legId] += $pv;
                    } else {
                        $gvTree[$parentId][$legId] = $pv;
                    }
                    /* increase levels counter and switch current leg id */
                    $lvl++;
                    $legId = $parentId;
                }
            }
        }
        /* process intermediary GV values and calculate final results */
        $result = [ ];
        foreach($gvTree as $custId => $legs) {
            /* skip nodes without legs */
            if(count($legs) == 0) {
                continue;
            }
            /* qualify max rank; for all ranks from min to max */
            $rankIdQualified = null;
            $gvQualified = 0;
            foreach($cfgParams as $rankId => $params) {
                $maxPercent = $params[Param::ATTR_LEG_MAX_PERCENT];
                $gvRequired = $params[Param::ATTR_GV];
                $gvMax = $gvRequired * $maxPercent;
                $gvTotal = 0;
                foreach($legs as $gvLeg) {
                    $gvTotal += ($gvLeg > $gvMax) ? $gvMax : $gvLeg;
                }
                /* total GV should not be less then required  */
                if($gvTotal >= $gvRequired) {
                    $rankIdQualified = $rankId;
                    $gvQualified = $gvTotal;
                } else {
                    break;
                }
            }
            /* add values to results */
            if(!is_null($rankIdQualified)) {
                $compressId = $mapById[$custId][Compress::ATTR_ID];
                $result[$custId] = [
                    EntityQual::ATTR_COMPRESS_ID => $compressId,
                    EntityQual::ATTR_RANK_ID     => $rankIdQualified,
                    EntityQual::ATTR_GV          => $gvQualified
                ];
            }
        }
        return $result;
    }
}