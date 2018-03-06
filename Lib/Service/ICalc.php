<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\GlobalSales\Lib\Service;

use Praxigento\Bonus\GlobalSales\Lib\Service\Calc\Request;
use Praxigento\Bonus\GlobalSales\Lib\Service\Calc\Response;

interface ICalc {

    /**
     * @param Request\Bonus $req
     *
     * @return Response\Bonus
     */
    public function bonus(Request\Bonus $req);

    /**
     * @param Request\Qualification $req
     *
     * @return Response\Qualification
     */
    public function qualification(Request\Qualification $req);

}