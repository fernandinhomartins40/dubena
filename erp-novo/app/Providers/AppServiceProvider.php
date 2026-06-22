<?php

namespace App\Providers;

use App\Domain\Cobranca\Contracts\BoletoDriver;
use App\Domain\Cobranca\Drivers\FakeBoletoDriver;
use App\Domain\Fiscal\Contracts\SefazDriver;
use App\Domain\Fiscal\Drivers\FakeSefazDriver;
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

        // Driver SEFAZ (N9 — GATE). Default: Fake. Em produção, driver real (NFePHP
        // + certificado A1 do tenant). Troca por config sem mexer no FiscalService.
        $this->app->bind(SefazDriver::class, FakeSefazDriver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
