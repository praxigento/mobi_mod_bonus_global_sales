<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\GlobalSales\Lib\Service\Calc;

use Praxigento\Bonus\GlobalSales\Lib\Service\ICalc;
use Praxigento\BonusBase\Data\Entity\Calculation;
use Praxigento\BonusBase\Data\Entity\Period;
use Praxigento\BonusBase\Service\Period\Request\GetForDependentCalc as PeriodGetForDependentCalcRequest;
use Praxigento\BonusGlobalSales\Config as Cfg;
use Praxigento\Core\Service\Base\Call as BaseCall;
use Praxigento\Wallet\Service\Operation\Request\AddToWalletActive as WalletOperationAddToWalletActiveRequest;

class Call extends BaseCall implements ICalc
{
    /** @var  \Praxigento\BonusBase\Service\IPeriod */
    protected $_callBasePeriod;
    /** @var  \Praxigento\Wallet\Service\IOperation */
    protected $_callWalletOperation;
    /** @var \Psr\Log\LoggerInterface */
    protected $_logger;
    /** @var  \Praxigento\Core\Transaction\Database\IManager */
    protected $_manTrans;
    /** @var  \Praxigento\BonusBase\Repo\Entity\ICompress */
    protected $_repoBonusCompress;
    /** @var \Praxigento\BonusBase\Repo\Service\IModule */
    protected $_repoBonusService;
    /** @var \Praxigento\Bonus\GlobalSales\Lib\Repo\IModule */
    protected $_repoMod;
    /** @var  Sub\Bonus */
    protected $_subBonus;
    /** @var Sub\Qualification */
    protected $_subQualification;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Praxigento\Core\Transaction\Database\IManager $manTrans,
        \Praxigento\Bonus\GlobalSales\Lib\Repo\IModule $repoMod,
        \Praxigento\BonusBase\Repo\Service\IModule $repoBonusService,
        \Praxigento\BonusBase\Repo\Entity\ICompress $repoBonusCompress,
        \Praxigento\BonusBase\Service\IPeriod $callBasePeriod,
        \Praxigento\Wallet\Service\IOperation $callWalletOperation,
        Sub\Bonus $subBonus,
        Sub\Qualification $subQual
    ) {
        $this->_logger = $logger;
        $this->_manTrans = $manTrans;
        $this->_repoMod = $repoMod;
        $this->_repoBonusService = $repoBonusService;
        $this->_repoBonusCompress = $repoBonusCompress;
        $this->_callBasePeriod = $callBasePeriod;
        $this->_callWalletOperation = $callWalletOperation;
        $this->_subBonus = $subBonus;
        $this->_subQualification = $subQual;
    }

    /**
     * @param $updates [$custId=>[$rankId=>$bonus, ...], ...]
     *
     * @return \Praxigento\Wallet\Service\Operation\Response\AddToWalletActive
     */
    private function _createBonusOperation($updates)
    {
        $asCustId = 'asCid';
        $asAmount = 'asAmnt';
        $asRef = 'asRef';
        $transData = [];
        foreach ($updates as $custId => $ranks) {
            foreach ($ranks as $rankId => $amount) {
                $item = [$asCustId => $custId, $asAmount => $amount, $asRef => $rankId];
                $transData[] = $item;
            }
        }
        $req = new WalletOperationAddToWalletActiveRequest();
        $req->setAsCustomerId($asCustId);
        $req->setAsAmount($asAmount);
        $req->setAsRef($asRef);
        $req->setOperationTypeCode(Cfg::CODE_TYPE_OPER_BONUS);
        $req->setTransData($transData);
        $result = $this->_callWalletOperation->addToWalletActive($req);
        return $result;
    }

    /**
     * @param Request\Bonus $req
     *
     * @return Response\Bonus
     */
    public function bonus(Request\Bonus $req)
    {
        $result = new Response\Bonus();
        $datePerformed = $req->getDatePerformed();
        $dateApplied = $req->getDateApplied();
        $this->_logger->info("'Global Sales Bonus' calculation is started. Performed at: $datePerformed, applied at: $dateApplied.");
        $reqGetPeriod = new PeriodGetForDependentCalcRequest();
        $calcTypeBase = Cfg::CODE_TYPE_CALC_QUALIFICATION;
        $calcType = Cfg::CODE_TYPE_CALC_BONUS;
        $reqGetPeriod->setBaseCalcTypeCode($calcTypeBase);
        $reqGetPeriod->setDependentCalcTypeCode($calcType);
        $respGetPeriod = $this->_callBasePeriod->getForDependentCalc($reqGetPeriod);
        if ($respGetPeriod->isSucceed()) {
            $def = $this->_manTrans->begin();
            try {
                $periodDataDepend = $respGetPeriod->getDependentPeriodData();
                $calcDataDepend = $respGetPeriod->getDependentCalcData();
                $calcIdDepend = $calcDataDepend[Calculation::ATTR_ID];
                $dsBegin = $periodDataDepend[Period::ATTR_DSTAMP_BEGIN];
                $dsEnd = $periodDataDepend[Period::ATTR_DSTAMP_END];
                /* collect data to process bonus */
                $calcTypeIdCompress = $this->_repoMod->getTypeCalcIdByCode(Cfg::CODE_TYPE_CALC_COMPRESSION);
                $calcDataCompress = $this->_repoMod->getLatestCalcForPeriod($calcTypeIdCompress, $dsBegin, $dsEnd);
                $calcIdCompress = $calcDataCompress[Calculation::ATTR_ID];
                $params = $this->_repoMod->getConfigParams();
                $treeCompressed = $this->_repoMod->getCompressedTreeWithQualifications($calcIdCompress);
                $pvTotal = $this->_repoMod->getSalesOrdersPvForPeriod($dsBegin, $dsEnd);
                /* calculate bonus */
                $updates = $this->_subBonus->calc($treeCompressed, $pvTotal, $params);
                /* create new operation with bonus transactions and save sales log */
                $respAdd = $this->_createBonusOperation($updates);
                $transLog = $respAdd->getTransactionsIds();
                $this->_repoMod->saveLogRanks($transLog);
                /* mark calculation as completed and finalize bonus */
                $this->_repoBonusService->markCalcComplete($calcIdDepend);
                $this->_manTrans->commit($def);
                $result->setPeriodId($periodDataDepend[Period::ATTR_ID]);
                $result->setCalcId($calcIdDepend);
                $result->markSucceed();
            } finally {
                $this->_manTrans->end($def);
            }
        }
        $this->_logger->info("'Global Sales Bonus' calculation is complete.");
        return $result;
    }

    public function qualification(Request\Qualification $req)
    {
        $result = new Response\Qualification();
        $datePerformed = $req->getDatePerformed();
        $dateApplied = $req->getDateApplied();
        $gvMaxLevels = $req->getGvMaxLevels();
        $this->_logger->info("'Qualification for Global Sales' calculation is started. Performed at: $datePerformed, applied at: $dateApplied.");
        $reqGetPeriod = new PeriodGetForDependentCalcRequest();
        $calcTypeBase = Cfg::CODE_TYPE_CALC_COMPRESSION;
        $calcType = Cfg::CODE_TYPE_CALC_QUALIFICATION;
        $reqGetPeriod->setBaseCalcTypeCode($calcTypeBase);
        $reqGetPeriod->setDependentCalcTypeCode($calcType);
        $respGetPeriod = $this->_callBasePeriod->getForDependentCalc($reqGetPeriod);
        if ($respGetPeriod->isSucceed()) {
            $def = $this->_manTrans->begin();
            try {
                $periodDataDepend = $respGetPeriod->getDependentPeriodData();
                $calcDataDepend = $respGetPeriod->getDependentCalcData();
                $calcIdDepend = $calcDataDepend[Calculation::ATTR_ID];
                $calcDataBase = $respGetPeriod->getBaseCalcData();
                $dsBegin = $periodDataDepend[Period::ATTR_DSTAMP_BEGIN];
                $dsEnd = $periodDataDepend[Period::ATTR_DSTAMP_END];
                $calcIdBase = $calcDataBase[Calculation::ATTR_ID];
                $tree = $this->_repoBonusCompress->getTreeByCalcId($calcIdBase);
                $qualData = $this->_repoMod->getQualificationData($dsBegin, $dsEnd);
                $cfgParams = $this->_repoMod->getConfigParams();
                $updates = $this->_subQualification->calcParams($tree, $qualData, $cfgParams, $gvMaxLevels);
                $this->_repoMod->saveQualificationParams($updates);
                $this->_repoBonusService->markCalcComplete($calcIdDepend);
                $this->_manTrans->commit($def);
                $result->setPeriodId($periodDataDepend[Period::ATTR_ID]);
                $result->setCalcId($calcIdDepend);
                $result->markSucceed();
            } finally {
                $this->_manTrans->end($def);
            }
        }
        $this->_logger->info("'Qualification for Global Sales' calculation is complete.");
        return $result;
    }
}