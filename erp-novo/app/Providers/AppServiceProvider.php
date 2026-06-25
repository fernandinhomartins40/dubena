<?php

namespace App\Providers;

use App\Domain\Cobranca\Contracts\BoletoDriver;
use App\Domain\Cobranca\Drivers\CaixaBoletoDriver;
use App\Domain\Cobranca\Drivers\FakeBoletoDriver;
use App\Domain\Cobranca\Drivers\ItauBoletoDriver;
use App\Domain\Fiscal\Contracts\SefazDriver;
use App\Domain\Fiscal\Drivers\FakeSefazDriver;
use App\Domain\Fiscal\Drivers\NFePHPSefazDriver;
use App\Domain\Mobile\Contracts\PagamentoDriver;
use App\Domain\Mobile\Drivers\EredeDriver;
use App\Domain\Mobile\Drivers\FakePagamentoDriver;
use App\Domain\Monitora\Contracts\SgcasaDriver;
use App\Domain\Monitora\Drivers\FakeSgcasaDriver;
use App\Domain\Monitora\Drivers\SgcasaHttpDriver;
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
        $this->app->scoped(TenantContext::class, fn () => new TenantContext);

        // Driver de boleto (N7/F08 — GATE bancário). COBRANCA_DRIVER seleciona o
        // CNAB real por banco: 'caixa' (104) ou 'itau' (341); qualquer outro valor
        // mantém o Fake (CI/homolog). Lê de config() (compatível com config:cache).
        $this->app->bind(BoletoDriver::class, fn () => match (config('services.cobranca.driver')) {
            'caixa' => $this->app->make(CaixaBoletoDriver::class),
            'itau' => $this->app->make(ItauBoletoDriver::class),
            default => $this->app->make(FakeBoletoDriver::class),
        });

        // Driver SEFAZ (N9/C7b — GATE). FISCAL_DRIVER=nfephp ativa o driver REAL
        // (NFePHP + certificado A1 do tenant); qualquer outro valor mantém o Fake
        // (CI/homolog). O FiscalService não muda — só a config do gate.
        // Lê de config() (não env() direto): com config:cache em produção, env()
        // retornaria vazio e o driver real nunca ativaria.
        $this->app->bind(
            SefazDriver::class,
            fn () => config('services.fiscal.driver') === 'nfephp'
                ? $this->app->make(NFePHPSefazDriver::class)
                : $this->app->make(FakeSefazDriver::class),
        );

        // Driver de pagamento online (N10/F12 — GATE Rede). PAGAMENTO_DRIVER=erede
        // ativa o real (eRede + PV/token); qualquer outro valor mantém o Fake.
        $this->app->bind(PagamentoDriver::class, fn () => config('services.pagamento.driver') === 'erede'
            ? $this->app->make(EredeDriver::class)
            : $this->app->make(FakePagamentoDriver::class));

        // Driver SGCasa (N11/F12 — GATE sync GPS). MONITORA_DRIVER=sgcasa ativa o real
        // (API SGCasa); senão Fake. Singleton p/ permitir stub em teste.
        $this->app->singleton(SgcasaDriver::class, fn () => config('services.monitora.driver') === 'sgcasa'
            ? $this->app->make(SgcasaHttpDriver::class)
            : $this->app->make(FakeSgcasaDriver::class));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
