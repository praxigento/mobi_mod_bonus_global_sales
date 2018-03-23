<?php
/**
 * Populate DB schema with module's initial data
 * .
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusGlobalSales\Setup;

use Praxigento\Accounting\Repo\Data\Type\Operation as TypeOperation;
use Praxigento\BonusBase\Repo\Data\Type\Calc as TypeCalc;
use Praxigento\BonusGlobalSales\Config as Cfg;

class InstallData extends \Praxigento\Core\App\Setup\Data\Base
{
    private function _addAccountingOperationsTypes()
    {
        $this->_repoGeneric->addEntity(
            TypeOperation::ENTITY_NAME,
            [
                TypeOperation::A_CODE => Cfg::CODE_TYPE_OPER_BONUS,
                TypeOperation::A_NOTE => 'Global Sales bonus.'
            ]
        );
    }

    private function _addBonusCalculationsTypes()
    {
        $this->_conn->insertArray(
            $this->_resource->getTableName(TypeCalc::ENTITY_NAME),
            [TypeCalc::A_CODE, TypeCalc::A_NOTE],
            [
                [Cfg::CODE_TYPE_CALC_QUALIFICATION, 'Qualification for Global Sales bonus.'],
                [Cfg::CODE_TYPE_CALC_BONUS, 'Global Sales bonus itself.']
            ]
        );
    }

    protected function _setup()
    {
        $this->_addAccountingOperationsTypes();
        $this->_addBonusCalculationsTypes();
    }

}