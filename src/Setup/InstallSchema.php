<?php
/**
 * Create DB schema.
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusGlobalSales\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Praxigento\Bonus\GlobalSales\Lib\Entity\Cfg\Param;
use Praxigento\Bonus\GlobalSales\Lib\Entity\Qualification;

class InstallSchema extends \Praxigento\Core\Setup\Schema\Base
{
    protected function _setup(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        /** Read and parse JSON schema. */
        $pathToFile = __DIR__ . '/../etc/dem.json';
        $pathToNode = '/dBEAR/package/Praxigento/package/Bonus/package/GlobalSales';
        $demPackage = $this->_toolDem->readDemPackage($pathToFile, $pathToNode);

        /* Cfg/ Param */
        $entityAlias = Param::ENTITY_NAME;
        $demEntity = $demPackage->getData('package/Config/entity/Param');
        $this->_toolDem->createEntity($entityAlias, $demEntity);

        /* Qualification */
        $entityAlias = Qualification::ENTITY_NAME;
        $demEntity = $demPackage->getData('entity/Qualification');
        $this->_toolDem->createEntity($entityAlias, $demEntity);
    }


}