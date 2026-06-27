<?php

namespace Tests\Feature;

use App\Domain\Shared\TenantCache;
use App\Domain\Tenant\TenantContext;
use App\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Monolog\Formatter\JsonFormatter;
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

    public function test_login_bloqueia_apos_falhas_repetidas(): void
    {
        // Defesa em camadas: o LOCKOUT da A5 (5 falhas por e-mail/IP numa janela)
        // barra ANTES do rate-limit de 10/min. As 5 primeiras falham com 401; a 6ª
        // é barrada (429), mesmo continuando credencial inválida.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'x@y.com', 'password' => 'errada'])
                ->assertStatus(401);
        }
        $this->postJson('/api/login', ['email' => 'x@y.com', 'password' => 'errada'])
            ->assertStatus(429); // lockout (A5)
    }

    public function test_rotas_admin_tem_throttle(): void
    {
        // A presença do middleware throttle:api é verificável na rota.
        $rota = collect(Route::getRoutes())
            ->first(fn ($r) => $r->uri() === 'api/admin/clientes' && in_array('GET', $r->methods(), true));
        $this->assertNotNull($rota);
        $this->assertContains('throttle:api', $rota->gatherMiddleware());
    }

    public function test_canal_de_log_estruturado_configurado(): void
    {
        $cfg = config('logging.channels.estruturado');
        $this->assertSame('daily', $cfg['driver']);
        $this->assertSame(JsonFormatter::class, $cfg['formatter']);
    }
}
