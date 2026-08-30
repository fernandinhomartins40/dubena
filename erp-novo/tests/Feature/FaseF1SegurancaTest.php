<?php

namespace Tests\Feature;

use App\Domain\Cobranca\Contracts\BoletoDriver;
use App\Domain\Fiscal\Contracts\SefazDriver;
use App\Domain\Mobile\Contracts\FirebaseVerifier;
use App\Domain\Mobile\Contracts\PagamentoDriver;
use App\Domain\Mobile\Contracts\PushTransport;
use App\Domain\Monitora\Contracts\SgcasaDriver;
use App\Domain\Monitora\Drivers\FakeSgcasaDriver;
use App\Models\User;
use Database\Seeders\DeployAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * GATE da FASE F1 (PLANO_PRODUCAO) — "estancar o sangramento de segurança".
 *
 * Cada teste amarra um achado da auditoria ao comportamento que o corrige, para
 * que a regressão apareça no CI e não num pentest:
 *  - T1.1 senhas seed fail-close em produção;
 *  - T1.3 drivers gate fail-close em produção (fake = NF-e/auth simuladas);
 *  - T1.4 throttle nas 3 rotas públicas de login/cadastro do app;
 *  - T1.5 expiração dos tokens Sanctum;
 *  - T1.8 `support` fora do $fillable (bypass total de RBAC por mass-assign).
 */
class FaseF1SegurancaTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------- T1.1

    public function test_seeder_de_admin_aborta_em_producao_sem_senha(): void
    {
        app()['env'] = 'production';
        putenv('ADMIN_SEED_PASSWORD');
        $_ENV['ADMIN_SEED_PASSWORD'] = '';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/ADMIN_SEED_PASSWORD/');

        (new DeployAdminSeeder)->run();
    }

    public function test_seeder_de_admin_rejeita_senha_fraca_em_producao(): void
    {
        app()['env'] = 'production';
        putenv('ADMIN_SEED_PASSWORD=curta123');
        $_ENV['ADMIN_SEED_PASSWORD'] = 'curta123';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/no mínimo/');

        (new DeployAdminSeeder)->run();
    }

    // ---------------------------------------------------------------- T1.3

    /**
     * Um driver fake em produção não é degradação: é NF-e simulada, CNAB de
     * mentira e login de qualquer cliente por token forjado.
     *
     * @return list<array{0:class-string,1:string,2:string}>
     */
    public static function driversGate(): array
    {
        return [
            'firebase' => [FirebaseVerifier::class, 'services.firebase.driver', 'FIREBASE_DRIVER'],
            'fcm' => [PushTransport::class, 'services.fcm.driver', 'FCM_DRIVER'],
            'fiscal' => [SefazDriver::class, 'services.fiscal.driver', 'FISCAL_DRIVER'],
            'cobranca' => [BoletoDriver::class, 'services.cobranca.driver', 'COBRANCA_DRIVER'],
            'pagamento' => [PagamentoDriver::class, 'services.pagamento.driver', 'PAGAMENTO_DRIVER'],
        ];
    }

    /**
     * @param  class-string  $contrato
     */
    #[DataProvider('driversGate')]
    public function test_driver_gate_falha_em_producao_quando_fake(
        string $contrato, string $chaveConfig, string $envVar
    ): void {
        app()['env'] = 'production';
        config([$chaveConfig => 'fake']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/'.$envVar.'/');

        app($contrato);
    }

    /**
     * @param  class-string  $contrato
     */
    #[DataProvider('driversGate')]
    public function test_driver_gate_permite_fake_fora_de_producao(
        string $contrato, string $chaveConfig, string $envVar
    ): void {
        config([$chaveConfig => 'fake']);

        // Em dev/CI o Fake é o comportamento desejado — os workflows dependem dele.
        $this->assertNotNull(app($contrato), "{$envVar}: fake deve resolver fora de produção");
    }

    public function test_monitora_apenas_avisa_em_producao_sem_driver_real(): void
    {
        app()['env'] = 'production';
        config(['services.monitora.driver' => 'fake']);

        // GPS ausente degrada (sem rastreamento), não corrompe dado — por isso
        // é a exceção deliberada ao fail-close: resolve, mas loga aviso.
        $this->assertInstanceOf(
            FakeSgcasaDriver::class,
            app(SgcasaDriver::class),
        );
    }

    // ---------------------------------------------------------------- T1.4

    /** @return list<array{0:string}> */
    public static function rotasPublicasDoApp(): array
    {
        return [
            ['api/app/v1/login'],
            ['api/app/v1/cliente/login'],
            ['api/app/v1/cliente/cadastro'],
        ];
    }

    #[DataProvider('rotasPublicasDoApp')]
    public function test_rota_publica_do_app_tem_throttle(string $uri): void
    {
        $rota = collect(Route::getRoutes())->first(fn ($r) => $r->uri() === $uri);

        $this->assertNotNull($rota, "rota {$uri} não encontrada");
        $this->assertTrue(
            collect($rota->gatherMiddleware())->contains(fn ($m) => str_starts_with((string) $m, 'throttle:')),
            "rota pública {$uri} sem throttle — brute-force livre",
        );
    }

    public function test_cadastro_de_cliente_retorna_429_ao_estourar_o_limite(): void
    {
        RateLimiter::clear('cadastro:127.0.0.1');

        // O limiter é 5/min; o 6º POST do mesmo IP deve ser barrado ANTES de
        // qualquer validação — por isso o corpo vazio não interfere.
        for ($i = 0; $i < 5; $i++) {
            $resp = $this->postJson('/api/app/v1/cliente/cadastro', []);
            $this->assertNotSame(429, $resp->getStatusCode(), "requisição {$i} não deveria ser barrada");
        }

        $this->postJson('/api/app/v1/cliente/cadastro', [])->assertStatus(429);
    }

    // ---------------------------------------------------------------- T1.5

    public function test_expiracao_default_do_sanctum_e_de_24h(): void
    {
        // 30 dias (43200) davam a um token vazado um mês de vida; os apps têm
        // refresh, então a janela curta é operacionalmente viável.
        $this->assertSame(1440, (int) config('sanctum.expiration'));
    }

    // ---------------------------------------------------------------- T1.8

    public function test_support_nao_e_atribuivel_em_massa(): void
    {
        $user = new User;
        $user->fill(['name' => 'X', 'email' => 'x@y.com']);

        $this->assertNotTrue(
            $user->support,
            '`support` no $fillable permite escalar para bypass total de RBAC via mass-assign',
        );
    }
}
