<?php

namespace App\Providers;

use App\Domain\Cobranca\Contracts\BoletoDriver;
use App\Domain\Cobranca\Drivers\FakeBoletoDriver;
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

        // Driver de boleto (N7 — GATE). Default: Fake (dev/homolog/CI). Em produção,
        // trocar por driver real do banco (porta eduardokum/laravel-boleto).
        $this->app->bind(BoletoDriver::class, FakeBoletoDriver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
