<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 20/03/2019
 * Time: 11:33
 */

namespace App\Exceptions;


use Carbon\Carbon;
use Throwable;

class SatException extends \Exception
{

    /**
     * SatException constructor.
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(string $message = "", int $code = 0, Throwable $previous = null)
    {
        $this->log($message);
        parent::__construct($message, $code, $previous);
    }

    /**
     * @param $message
     */
    private function log($message)
    {
        satLog($message);
    }

}