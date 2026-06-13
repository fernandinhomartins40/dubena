<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 30/08/2018
 * Time: 09:59
 */

namespace App\Exceptions;

use App\Helpers\Utils\Util;
use App\Services\CarbonCustom as Carbon;
use \Exception;
use Storage;
use Throwable;

class RejectedException extends Exception
{
    public function __construct(string $message = "", int $code = 101, Throwable $previous = null)
    {
        $this->report($message . PHP_EOL);
        parent::__construct($message, $code, $previous);
    }

    private function report($message)
    {
        $path = "orders " . Carbon::now()->format("m-Y") . ".log";
        Storage::drive("notification")->append($path, $message);
        Util::notify($message);
    }

}
