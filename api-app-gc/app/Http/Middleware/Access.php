<?php

namespace App\Http\Middleware;

use App\Helpers\Util;
use Closure;
use Illuminate\Support\Facades\Log;

class Access
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        Util::logAccess($request);
        $headers = $request->header();
        $head = json_encode($headers);
        Log::info("URL: " . $request->path() . PHP_EOL);
        // Log::info("HEADERS: " . $head . PHP_EOL);
        return $next($request);
    }
}
