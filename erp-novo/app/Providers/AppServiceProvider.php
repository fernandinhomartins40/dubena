<?php

namespace App\Providers;

use App\Domain\Tenant\TenantContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // TenantContext único por ciclo de requisição (substitui Session('empresa_padrao')).
        // O middleware ResolveTenant popula; Services/Models/Scopes injetam o MESMO objeto.
        $this->app->scoped(TenantContext::class, fn () => new TenantContext());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
