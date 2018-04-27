<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\GlobalSales\Lib\Service\Calc;

use Praxigento\BonusBase\Service\Period\Request\GetForDependentCalc as PeriodGetForDependentCalcRequest;
use Praxigento\BonusGlobalSales\Config as Cfg;
use Praxigento\Wallet\Service\Operation\Request\AddToWalletActive as WalletOperationAddToWalletActiveRequest;

/**
 * @SuppressWarnings(PHPMD.CamelCasePropertyName)
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Call
    implements \Praxigento\Bonus\GlobalSales\Lib\Service\ICalc
{
    /** @var  \Praxigento\BonusBase\Service\IPeriod */
    private $callBasePeriod;
    /** @var  \Praxigento\Wallet\Service\IOperation */
    private $callWalletOperation;
    /** @var \Psr\Log\LoggerInterface */
    private $logger;
    /** @var  \Praxigento\Core\Api\App\Repo\Transaction\Manager */
    private $manTrans;
    /** @var  \Praxigento\BonusBase\Repo\Dao\Compress */
    private $repoBonusCompress;
    /** @var \Praxigento\BonusBase\Repo\Service\IModule */
    private $repoBonusService;
    /** @var \Praxigento\BonusBase\Repo\Dao\Type\Calc */
    private $repoBonusTypeCalc;
    /** @var \Praxigento\Bonus\GlobalSales\Lib\Repo\IModule */
    private $repoMod;
    /** @var  Sub\Bonus */
    private $subBonus;
    /** @var Sub\Qualification */
    private $subQualification;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\Core\Api\App\Repo\Transaction\Manager $manTrans,
        \Praxigento\Bonus\GlobalSales\Lib\Repo\IModule $daoMod,
        \Praxigento\BonusBase\Repo\Service\IModule $daoBonusService,
        \Praxigento\BonusBase\Repo\Dao\Compress $daoBonusCompress,
        \Praxigento\BonusBase\Repo\Dao\Type\Calc $daoBonusTypeCalc,
        \Praxigento\BonusBase\Service\IPeriod $callBasePeriod,
        \Praxigento\Wallet\Service\IOperation $callWalletOperation,
        Sub\Bonus $subBonus,
        Sub\Qualification $subQual
    ) {
        $this->logger = $logger;
        $this->manTrans = $manTrans;
        $this->repoMod = $daoMod;
        $this->repoBonusService = $daoBonusService;
        $this->repoBonusCompress = $daoBonusCompress;
        $this->repoBonusTypeCalc = $daoBonusTypeCalc;
        $this->callBasePeriod = $callBasePeriod;
        $this->callWalletOperation = $callWalletOperation;
        $this->subBonus = $subBonus;
        $this->subQualification = $subQual;
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
        $result = $this->callWalletOperation->addToWalletActive($req);
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
        $this->logger->info("'Global Sales Bonus' calculation is started. Performed at: $datePerformed, applied at: $dateApplied.");
        $reqGetPeriod = new PeriodGetForDependentCalcRequest();
        $calcTypeBase = Cfg::CODE_TYPE_CALC_QUALIFICATION;
        $calcType = Cfg::CODE_TYPE_CALC_BONUS;
        $reqGetPeriod->setBaseCalcTypeCode($calcTypeBase);
        $reqGetPeriod->setDependentCalcTypeCode($calcType);
        $respGetPeriod = $this->callBasePeriod->getForDependentCalc($reqGetPeriod);
        if ($respGetPeriod->isSucceed()) {
            $def = $this->manTrans->begin();
            try {
                $periodDataDepend = $respGetPeriod->getDependentPeriodData();
                $calcDataDepend = $respGetPeriod->getDependentCalcData();
                $calcIdDepend = $calcDataDepend->getId();
                $dsBegin = $periodDataDepend->getDstampBegin();
                $dsEnd = $periodDataDepend->getDstampEnd();
                /* collect data to process bonus */
                $calcTypeIdCompress = $this->repoBonusTypeCalc->getIdByCode(Cfg::CODE_TYPE_CALC_COMPRESSION);
                $calcDataCompress = $this->repoBonusService
                    ->getLastCalcForPeriodByDates($calcTypeIdCompress, $dsBegin, $dsEnd);
                $calcIdCompress = $calcDataCompress->getId();
                $params = $this->repoMod->getConfigParams();
                $treeCompressed = $this->repoMod->getCompressedTreeWithQualifications($calcIdCompress);
                $pvTotal = $this->repoMod->getSalesOrdersPvForPeriod($dsBegin, $dsEnd);
                /* calculate bonus */
                $updates = $this->subBonus->calc($treeCompressed, $pvTotal, $params);
                /* create new operation with bonus transactions and save sales log */
                $respAdd = $this->_createBonusOperation($updates);
                $transLog = $respAdd->getTransactionsIds();
                $this->repoMod->saveLogRanks($transLog);
                /* mark calculation as completed and finalize bonus */
                $this->repoBonusService->markCalcComplete($calcIdDepend);
                $this->manTrans->commit($def);
                $result->setPeriodId($periodDataDepend->getId());
                $result->setCalcId($calcIdDepend);
                $result->markSucceed();
            } finally {
                $this->manTrans->end($def);
            }
        }
        $this->logger->info("'Global Sales Bonus' calculation is complete.");
        return $result;
    }

    public function qualification(Request\Qualification $req)
    {
        $result = new Response\Qualification();
        $datePerformed = $req->getDatePerformed();
        $dateApplied = $req->getDateApplied();
        $gvMaxLevels = $req->getGvMaxLevels();
        $msg = "'Qualification for Global Sales' calculation is started. "
            . "Performed at: $datePerformed, applied at: $dateApplied.";
        $this->logger->info($msg);
        $reqGetPeriod = new PeriodGetForDependentCalcRequest();
        $calcTypeBase = Cfg::CODE_TYPE_CALC_COMPRESSION;
        $calcType = Cfg::CODE_TYPE_CALC_QUALIFICATION;
        $reqGetPeriod->setBaseCalcTypeCode($calcTypeBase);
        $reqGetPeriod->setDependentCalcTypeCode($calcType);
        $respGetPeriod = $this->callBasePeriod->getForDependentCalc($reqGetPeriod);
        if ($respGetPeriod->isSucceed()) {
            $def = $this->manTrans->begin();
            try {
                $periodDataDepend = $respGetPeriod->getDependentPeriodData();
                $calcDataDepend = $respGetPeriod->getDependentCalcData();
                $calcIdDepend = $calcDataDepend->getId();
                $calcDataBase = $respGetPeriod->getBaseCalcData();
                $dsBegin = $periodDataDepend->getDstampBegin();
                $dsEnd = $periodDataDepend->getDstampEnd();
                $calcIdBase = $calcDataBase->getId();
                $tree = $this->repoBonusCompress->getTreeByCalcId($calcIdBase);
                $qualData = $this->repoMod->getQualificationData($dsBegin, $dsEnd);
                $cfgParams = $this->repoMod->getConfigParams();
                $updates = $this->subQualification->calcParams($tree, $qualData, $cfgParams, $gvMaxLevels);
                $this->repoMod->saveQualificationParams($updates);
                $this->repoBonusService->markCalcComplete($calcIdDepend);
                $this->manTrans->commit($def);
                $result->setPeriodId($periodDataDepend->getId());
                $result->setCalcId($calcIdDepend);
                $result->markSucceed();
            } finally {
                $this->manTrans->end($def);
            }
        }
        $this->logger->info("'Qualification for Global Sales' calculation is complete.");
        return $result;
    }
}