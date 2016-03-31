<?php
/**
 * Setup schema (create tables in DB).
 *
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\GlobalSales\Lib\Setup;

use Praxigento\Bonus\GlobalSales\Lib\Entity\Cfg\Param;
use Praxigento\Bonus\GlobalSales\Lib\Entity\Qualification;


class Schema extends \Praxigento\Core\Lib\Setup\Schema\Base {

    public function setup() {
        /**
         * Read and parse JSON schema.
         */
        $pathToFile = __DIR__ . '/../etc/dem.json';
        $pathToNode = '/dBEAR/package/Praxigento/package/Bonus/package/GlobalSales';
        $demPackage = $this->_readDemPackage($pathToFile, $pathToNode);

        /* Cfg/ Param */
        $entityAlias = Param::ENTITY_NAME;
        $demEntity = $demPackage['package']['Config']['entity']['Param'];
        $this->_demDb->createEntity($entityAlias, $demEntity);

        /* Qualification */
        $entityAlias = Qualification::ENTITY_NAME;
        $demEntity = $demPackage['entity']['Qualification'];
        $this->_demDb->createEntity($entityAlias, $demEntity);

    }
}