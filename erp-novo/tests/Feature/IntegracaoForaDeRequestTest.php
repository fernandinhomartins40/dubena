<?php

namespace Tests\Feature;

use App\Domain\Cliente\GeocodificarClienteJob;
use App\Domain\Integracao\CredencialNaoConfiguradaException;
use App\Domain\Integracao\IntegracaoTenant;
use App\Domain\Tenant\TenantContext;
use App\Models\Cliente\Cliente;
use App\Models\ConfigGlobal;
use App\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * FASE 5 do PLANO_SEGURANCA_MULTITENANT_APPS — credencial por RECURSO fora de request.
 *
 * Em job/cron o TenantContext ambient está vazio: a credencial deve ser resolvida
 * pelo id EXPLÍCITO do recurso (grupo do cliente, empresa do pedido), nunca cair
 * no env em silêncio. Dinheiro em contexto vazio já é fail-closed (F2).
 */
class IntegracaoForaDeRequestTest extends TestCase
{
    use RefreshDatabase;

    private function clienteComEndereco(Empresa $empresa): Cliente
    {
        DB::table('estados')->insertOrIgnore(['uf' => 'PR', 'descricao' => 'Paraná']);
        $cidadeId = DB::table('cidades')->insertGetId([
            'grupo_id' => $empresa->grupo_id, 'descricao' => 'Guarapuava', 'uf' => 'PR',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'endereco' => 'Rua XV de Novembro', 'numero' => '100', 'cidade_id' => $cidadeId,
            'latitude' => null, 'longitude' => null,
        ]);
    }

    public function test_geocodificacao_usa_a_key_do_grupo_do_cliente(): void
    {
        $empresa = Empresa::factory()->create();
        ConfigGlobal::withoutGrupo()->create(['grupo_id' => $empresa->grupo_id, 'google_maps_key' => 'KEY-DO-GRUPO']);
        config(['services.geocoding.key' => 'KEY-DA-PLATAFORMA']);

        $cliente = $this->clienteComEndereco($empresa);
        Http::fake(['maps.googleapis.com/*' => Http::response([
            'results' => [['geometry' => ['location' => ['lat' => -25.38, 'lng' => -51.48], 'location_type' => 'ROOFTOP']]],
        ])]);

        // Contexto AMBIENT vazio, como num worker de fila.
        app(TenantContext::class)->clear();
        (new GeocodificarClienteJob($cliente->id))->handle();

        // A chamada saiu com a key DO GRUPO do cliente, não a da plataforma.
        Http::assertSent(fn ($request) => $request['key'] === 'KEY-DO-GRUPO');
        $this->assertEqualsWithDelta(-25.38, (float) $cliente->refresh()->latitude, 0.001);
    }

    public function test_geocodificacao_sem_key_de_grupo_cai_no_env(): void
    {
        $empresa = Empresa::factory()->create(); // grupo SEM key própria
        config(['services.geocoding.key' => 'KEY-DA-PLATAFORMA']);

        $cliente = $this->clienteComEndereco($empresa);
        Http::fake(['maps.googleapis.com/*' => Http::response(['results' => []])]);

        app(TenantContext::class)->clear();
        (new GeocodificarClienteJob($cliente->id))->handle();

        Http::assertSent(fn ($request) => $request['key'] === 'KEY-DA-PLATAFORMA');
    }

    public function test_dinheiro_em_contexto_vazio_e_fail_closed_em_producao(): void
    {
        // Worker de fila sem tenant aplicado: cartão NUNCA resolve pelo env em produção.
        config(['services.erede.pv' => 'PV-ENV', 'services.erede.token' => 'TOKEN-ENV']);
        app(TenantContext::class)->clear();
        $this->app['env'] = 'production';

        $this->expectException(CredencialNaoConfiguradaException::class);
        app(IntegracaoTenant::class)->cartao();
    }
}
