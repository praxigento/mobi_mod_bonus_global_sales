<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\GlobalSales\Lib\Service\Calc\Request;
/**
 * @method string getDateApplied() Transaction applied dates for calculation. UTC current date is used if missed.
 * @method void setDateApplied(string $data)
 * @method string getDatePerformed() Operation performed dates for calculation. UTC current date is used if missed.
 * @method void setDatePerformed(string $data)
 * @method int getGvMaxLevels() Max levels to calculate GV
 * @method void setGvMaxLevels(int $data)
 */
class Qualification extends \Praxigento\Core\App\Service\Request {

}