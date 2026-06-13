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
        // Deploy atrás de proxy (Nginx do host faz o TLS). Força https no gerador
        // de URLs quando APP_URL é https (evita mixed content nos assets).
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
