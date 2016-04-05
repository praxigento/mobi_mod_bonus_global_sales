<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\GlobalSales\Lib\Service\Calc;

use Praxigento\Bonus\Base\Lib\Entity\Calculation;
use Praxigento\Bonus\Base\Lib\Entity\Period;
use Praxigento\Bonus\Base\Lib\Service\Period\Request\GetForDependentCalc as PeriodGetForDependentCalcRequest;
use Praxigento\Bonus\GlobalSales\Lib\Service\ICalc;
use Praxigento\BonusGlobalSales\Config as Cfg;
use Praxigento\Core\Lib\Service\Base\NeoCall as NeoCall;
use Praxigento\Wallet\Lib\Service\Operation\Request\AddToWalletActive as WalletOperationAddToWalletActiveRequest;

class Call extends NeoCall implements ICalc
{
    /** @var  \Praxigento\Bonus\Base\Lib\Service\IPeriod */
    protected $_callBasePeriod;
    /** @var  \Praxigento\Wallet\Lib\Service\IOperation */
    protected $_callWalletOperation;
    /** @var \Psr\Log\LoggerInterface */
    protected $_logger;
    /** @var \Praxigento\Bonus\GlobalSales\Lib\Repo\IModule */
    protected $_repoMod;
    /** @var  Sub\Bonus */
    protected $_subBonus;
    /** @var Sub\Qualification */
    protected $_subQualification;
    /** @var  \Praxigento\Core\Repo\ITransactionManager */
    protected $_manTrans;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Praxigento\Core\Repo\ITransactionManager $manTrans,
        \Praxigento\Bonus\GlobalSales\Lib\Repo\IModule $repoMod,
        \Praxigento\Bonus\Base\Lib\Service\IPeriod $callBasePeriod,
        \Praxigento\Wallet\Lib\Service\IOperation $callWalletOperation,
        Sub\Bonus $subBonus,
        Sub\Qualification $subQual
    ) {
        $this->_logger = $logger;
        $this->_manTrans = $manTrans;
        $this->_repoMod = $repoMod;
        $this->_callBasePeriod = $callBasePeriod;
        $this->_callWalletOperation = $callWalletOperation;
        $this->_subBonus = $subBonus;
        $this->_subQualification = $subQual;
    }

    /**
     * @param $updates [$custId=>[$rankId=>$bonus, ...], ...]
     *
     * @return \Praxigento\Wallet\Lib\Service\Operation\Response\AddToWalletActive
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
            $trans = $this->_manTrans->transactionBegin();
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
                $this->_repoMod->updateCalcSetComplete($calcIdDepend);
                $this->_manTrans->transactionCommit($trans);
                $result->setPeriodId($periodDataDepend[Period::ATTR_ID]);
                $result->setCalcId($calcIdDepend);
                $result->setAsSucceed();
            } finally {
                $this->_manTrans->transactionClose($trans);
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
            $trans = $this->_manTrans->transactionBegin();
            try {
                $periodDataDepend = $respGetPeriod->getDependentPeriodData();
                $calcDataDepend = $respGetPeriod->getDependentCalcData();
                $calcIdDepend = $calcDataDepend[Calculation::ATTR_ID];
                $calcDataBase = $respGetPeriod->getBaseCalcData();
                $dsBegin = $periodDataDepend[Period::ATTR_DSTAMP_BEGIN];
                $dsEnd = $periodDataDepend[Period::ATTR_DSTAMP_END];
                $calcIdBase = $calcDataBase[Calculation::ATTR_ID];
                $tree = $this->_repoMod->getCompressedTree($calcIdBase);
                $qualData = $this->_repoMod->getQualificationData($dsBegin, $dsEnd);
                $cfgParams = $this->_repoMod->getConfigParams();
                $updates = $this->_subQualification->calcParams($tree, $qualData, $cfgParams, $gvMaxLevels);
                $this->_repoMod->saveQualificationParams($updates);
                $this->_repoMod->updateCalcSetComplete($calcIdDepend);
                $this->_manTrans->transactionCommit($trans);
                $result->setPeriodId($periodDataDepend[Period::ATTR_ID]);
                $result->setCalcId($calcIdDepend);
                $result->setAsSucceed();
            } finally {
                $this->_manTrans->transactionClose($trans);
            }
        }
        $this->_logger->info("'Qualification for Global Sales' calculation is complete.");
        return $result;
    }
}