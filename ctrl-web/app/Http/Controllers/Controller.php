<?php

namespace App\Http\Controllers;

use App\Exceptions\RejectedException;
use Exception;
use GuzzleHttp\Promise\RejectionException;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * @var array
     */
    protected $paramsLink = ["results", "user_id"];

    /**
     * @param $condition
     * @param $message
     * @param int $code
     * @throws RejectedException
     * @throws Exception
     */

    protected function throwIf($condition, $msg, $code = 500)
    {
        if ($condition) {
            switch ($code) {
                case 101:
                    info("Error msg: " . $msg);
                    throw new RejectionException($msg, $code);

                default:
                    throw new Exception($msg, $code);
                    break;
            }
        }
    }
}
