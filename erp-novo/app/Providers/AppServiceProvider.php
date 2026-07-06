<?php

namespace App\Providers;

use App\Domain\Cobranca\Contracts\BoletoDriver;
use App\Domain\Cobranca\Drivers\CaixaBoletoDriver;
use App\Domain\Cobranca\Drivers\FakeBoletoDriver;
use App\Domain\Cobranca\Drivers\ItauBoletoDriver;
use App\Domain\Fiscal\Contracts\SefazDriver;
use App\Domain\Fiscal\Drivers\FakeSefazDriver;
use App\Domain\Fiscal\Drivers\NFePHPSefazDriver;
use App\Domain\Logistica\Contracts\MatrizDistancia;
use App\Domain\Logistica\Contracts\TracadorRota;
use App\Domain\Logistica\Drivers\GoogleMatrizDriver;
use App\Domain\Logistica\Drivers\GoogleRoutesDriver;
use App\Domain\Logistica\Drivers\HaversineDriver;
use App\Domain\Logistica\Drivers\SemTracado;
use App\Domain\Logistica\Drivers\TracadorRotaCacheado;
use App\Domain\Mobile\Contracts\FirebaseVerifier;
use App\Domain\Mobile\Contracts\PagamentoDriver;
use App\Domain\Mobile\Contracts\PushTransport;
use App\Domain\Mobile\Drivers\EredeDriver;
use App\Domain\Mobile\Drivers\FakeFirebaseVerifier;
use App\Domain\Mobile\Drivers\FakePagamentoDriver;
use App\Domain\Mobile\Drivers\FakePushTransport;
use App\Domain\Mobile\Drivers\FcmV1Transport;
use App\Domain\Mobile\Drivers\KreaitFirebaseVerifier;
use App\Domain\Monitora\Contracts\SgcasaDriver;
use App\Domain\Monitora\Drivers\FakeSgcasaDriver;
use App\Domain\Monitora\Drivers\SgcasaHttpDriver;
use App\Domain\Tenant\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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

        // Driver PIX (F6 — GATE bancário). PIX_DRIVER seleciona o PSP real na
        // homologação; default 'fake' (BR Code sintético, dev/CI). O PixService
        // resolve a credencial pela EMPRESA DO RECURSO e é fail-closed com driver
        // real sem credencial da empresa. Driver desconhecido = erro ALTO (jamais
        // degradar silenciosamente para o fake achando que cobra de verdade).
        $this->app->bind(\App\Domain\Cobranca\Contracts\PixDriver::class, function () {
            $driver = (string) config('services.pix.driver', 'fake');

            return match ($driver) {
                'fake' => $this->app->make(\App\Domain\Cobranca\Drivers\FakePixDriver::class),
                // PSPs reais entram aqui na homologação (ex.: 'itau' => ItauPixDriver).
                default => throw new \RuntimeException("PIX_DRIVER '{$driver}' não implementado — recuse a subir assim em vez de fingir cobrança com o fake."),
            };
        });

        // Matriz de distância/tempo da roteirização (L5 — GATE Google). Haversine
        // (grátis, default). A KEY vem do GRUPO ativo (config_globais.google_maps_key)
        // com fallback env — cada rede usa a SUA chave Maps (multi-tenant). Liga sem
        // tocar no Roteirizador.
        // F5 (segurança): este binding lê o TenantContext AMBIENT no make() — só
        // vale DENTRO de request (após o middleware tenant). Em job/cron, aplique o
        // tenant ANTES de resolver (TenantAwareJob::aplicarTenant) ou chame
        // IntegracaoTenant::googleMapsKey($grupoId) explícito (ex.: GeocodificarClienteJob).
        $this->app->bind(MatrizDistancia::class, function () {
            $key = $this->app->make(\App\Domain\Integracao\IntegracaoTenant::class)->googleMapsKey();

            return $key
                ? new GoogleMatrizDriver(new GoogleRoutesDriver((string) $key), $this->app->make(HaversineDriver::class))
                : $this->app->make(HaversineDriver::class);
        });

        // Traçado da rota pelas ruas (L6 — GATE Google Routes) com CACHE
        // PERSISTENTE (rotas_cache): 1 chamada Google por par de células (~100 m),
        // salva para sempre — trajetos recorrentes da praça custam zero. Key do
        // GRUPO ativo (fallback env). Sem key, o cache ainda serve trajetos já
        // aprendidos; o resto sai em linha reta.
        $this->app->bind(TracadorRota::class, function () {
            $key = $this->app->make(\App\Domain\Integracao\IntegracaoTenant::class)->googleMapsKey();

            return new TracadorRotaCacheado(
                $key ? new GoogleRoutesDriver((string) $key) : new SemTracado,
            );
        });

        // Verificador do Firebase (F1 — GATE phone-auth). FIREBASE_DRIVER=kreait ativa o
        // real (kreait/firebase-php + service account); qualquer outro valor mantém o Fake.
        $this->app->bind(FirebaseVerifier::class, fn () => config('services.firebase.driver') === 'kreait'
            ? $this->app->make(KreaitFirebaseVerifier::class)
            : $this->app->make(FakeFirebaseVerifier::class));

        // Transporte de push (N10/P0 — GATE FCM). FCM_DRIVER=v1 ativa o FCM HTTP v1
        // (service account); qualquer outro valor mantém o Fake (CI/homolog).
        $this->app->bind(PushTransport::class, fn () => config('services.fcm.driver') === 'v1'
            ? $this->app->make(FcmV1Transport::class)
            : $this->app->make(FakePushTransport::class));

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
        // Rate-limit da API (F13): por usuário autenticado (ou IP, se anônimo).
        // 120 req/min cobre o uso normal da SPA e barra abuso/loop. O webhook PIX e
        // o login têm limites próprios mais estreitos.
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)
            ->by($r->user()?->id ? 'u:'.$r->user()->id : 'ip:'.$r->ip()));

        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(10)
            ->by('login:'.$r->ip()));

        // P9 — teto por TENANT (além do teto por usuário). Protege um tenant de ser
        // afogado por outro num ambiente multi-empresa de alto volume: as requisições
        // de uma mesma empresa compartilham um balde generoso. A chave vem do
        // empresa_id do usuário autenticado (independe da ordem de middleware); sem
        // usuário/tenant, cai para o IP.
        RateLimiter::for('api-tenant', function (Request $r) {
            $empresaId = $r->user()?->empresa_id;

            return $empresaId
                ? Limit::perMinute(1200)->by('t:'.$empresaId)
                : Limit::perMinute(120)->by('ip:'.$r->ip());
        });

        // API-7 (auditoria) — limiters NOMEADOS para os throttles antes inline nas
        // rotas (throttle:60,1 / 120,1 / 30,1). Nomear centraliza o ajuste e deixa
        // a rota legível (o número solto não dizia o porquê).
        //  - marketplace: descoberta pública por geoloc (anônimo → por IP);
        //  - gps-ping: envio frequente de posição do entregador (por usuário);
        //  - missao-visita: registro de visita com evidência (por usuário).
        RateLimiter::for('marketplace', fn (Request $r) => Limit::perMinute(60)->by('ip:'.$r->ip()));
        RateLimiter::for('gps-ping', fn (Request $r) => Limit::perMinute(120)
            ->by($r->user()?->id ? 'u:'.$r->user()->id : 'ip:'.$r->ip()));
        RateLimiter::for('missao-visita', fn (Request $r) => Limit::perMinute(30)
            ->by($r->user()?->id ? 'u:'.$r->user()->id : 'ip:'.$r->ip()));
    }
}
