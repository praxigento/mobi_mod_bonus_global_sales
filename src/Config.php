<?php
/**
 * Module's configuration (hard-coded).
 *
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusGlobalSales;

use Praxigento\BonusLoyalty\Config as BonusLoyaltyCfg;

class Config
{
    /**
     * This module's calculation types.
     */
    const CODE_TYPE_CALC_BONUS = 'GLOBAL_SALES_BONUS';
    const CODE_TYPE_CALC_COMPRESSION = BonusLoyaltyCfg::CODE_TYPE_CALC_COMPRESSION;
    const CODE_TYPE_CALC_QUALIFICATION = 'GLOBAL_SALES_QUAL';
    /**
     * This module's operation types.
     */
    const CODE_TYPE_OPER_BONUS = 'GLOBAL_SALES_BONUS';
    const MODULE = 'Praxigento_BonusGlobalSales';
}