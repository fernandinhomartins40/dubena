<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application Services.
     *
     * @return void
     */
    public function boot()
    {
        Validator::extend("minwords", function ($attribute, $value, $parameters, $validator) {
            $param = array_first($parameters);
            $words = explode(' ', $value);
            $nbWords = count($words);

            return $nbWords >= $param;
        });

        Validator::replacer('minwords', function ($message, $attribute, $rule, $parameters) {
            $param = array_first($parameters);
            return str_replace(":minwords", $param, $message);
        });


//	    Schema::defaultStringLength(191);
//
//        if (config('app.debug')) {
//            error_reporting(E_ALL & ~E_USER_DEPRECATED);
//        } else {
//            error_reporting(0);
//        }
    }

    /**
     * Register any application Services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
