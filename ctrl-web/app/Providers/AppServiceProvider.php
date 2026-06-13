<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        \Carbon\Carbon::setLocale(config('app.locale'));

        // Deploy atrás de proxy (Nginx do host faz o TLS). Se APP_URL é https,
        // força o gerador de URLs a usar https — senão URL::to() gera links http
        // e o navegador bloqueia os assets (mixed content) na página segura.
        if (strpos((string) config('app.url'), 'https://') === 0) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
