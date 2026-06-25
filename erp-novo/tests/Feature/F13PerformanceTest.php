<?php

namespace Tests\Feature;

use App\Domain\Shared\TenantCache;
use App\Domain\Tenant\TenantContext;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * F13 — performance/observabilidade: índices por empresa_id, cache por-tenant,
 * rate-limit e canal de log estruturado.
 */
class F13PerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_indices_empresa_id_existem(): void
    {
        $temIndice = function (string $tabela, array $cols): bool {
            foreach (Schema::getIndexes($tabela) as $i) {
                if ($i['columns'] === $cols) {
                    return true;
                }
            }

            return false;
        };

        $this->assertTrue($temIndice('contamovimentos', ['empresa_id', 'datahora']));
        $this->assertTrue($temIndice('estoquehistorico', ['empresa_id', 'setor_id', 'produto_id']));
        $this->assertTrue($temIndice('financeiroparcelas', ['empresa_id', 'baixado', 'vencimento']));
    }

    public function test_cache_por_tenant_nao_vaza_entre_empresas(): void
    {
        $ctx = app(TenantContext::class);
        $cache = app(TenantCache::class);

        // Empresa A grava no cache.
        $ctx->set(1, 10);
        $cache->put('catalogo', 'dados-A', 60);
        $this->assertSame('dados-A', $cache->get('catalogo'));

        // Empresa B (mesma chave lógica) NÃO enxerga o valor de A.
        $ctx->set(2, 20);
        $this->assertNull($cache->get('catalogo'));
        $cache->put('catalogo', 'dados-B', 60);
        $this->assertSame('dados-B', $cache->get('catalogo'));

        // Voltando p/ A, o valor original permanece.
        $ctx->set(1, 10);
        $this->assertSame('dados-A', $cache->get('catalogo'));
    }

    public function test_chave_de_cache_e_namespaced_por_tenant(): void
    {
        $ctx = app(TenantContext::class);
        $cache = app(TenantCache::class);
        $ctx->set(7, 3);
        $this->assertSame('t:3:7:x', $cache->chave('x'));
        $ctx->clear();
        $this->assertSame('t:global:global:x', $cache->chave('x'));
    }

    public function test_rate_limit_login_bloqueia_apos_limite(): void
    {
        // 10/min no 'login'. As 10 primeiras passam pela validação (credencial
        // inválida = 401/422); a 11ª do mesmo IP é barrada pelo rate-limit (429).
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/login', ['email' => 'x@y.com', 'password' => 'errada'])
                ->assertStatus(401);
        }
        $this->postJson('/api/login', ['email' => 'x@y.com', 'password' => 'errada'])
            ->assertStatus(429); // estourou o rate-limit
    }

    public function test_rotas_admin_tem_throttle(): void
    {
        // A presença do middleware throttle:api é verificável na rota.
        $rota = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->first(fn ($r) => $r->uri() === 'api/admin/clientes' && in_array('GET', $r->methods(), true));
        $this->assertNotNull($rota);
        $this->assertContains('throttle:api', $rota->gatherMiddleware());
    }

    public function test_canal_de_log_estruturado_configurado(): void
    {
        $cfg = config('logging.channels.estruturado');
        $this->assertSame('daily', $cfg['driver']);
        $this->assertSame(\Monolog\Formatter\JsonFormatter::class, $cfg['formatter']);
    }
}
