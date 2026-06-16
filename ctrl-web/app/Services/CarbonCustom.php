<?php

namespace App\Services;

use Carbon\Carbon;

class CarbonCustom extends Carbon 
{
    public function __construct($time = null, $tz = null)
    {
        if (is_null($tz)) {
            setTimezone();
        }
        parent::__construct($time, $tz);
    }
}