<?php

namespace Tests\Feature;

use App\Domain\Saas\LicencaService;
use App\Domain\Saas\RecursoCatalogo;
use App\Http\Middleware\RecursoPorRota;
use App\Models\Empresa;
use App\Models\Saas\Assinatura;
use App\Models\Saas\Plano;
use App\Models\User;
use Database\Seeders\PlanosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F2-03 — a licença passa a decidir.
 *
 * O `LicencaService`, o catálogo de 10 recursos e o middleware `recurso:` já
 * existiam e estavam corretos. Mas **zero rotas** usavam o middleware e havia
 * **zero assinaturas** no banco: a licença existia e não decidia nada.
 *
 * O serviço é fail-closed — sem assinatura, nenhum recurso é liberado. Isso
 * torna a ordem obrigatória: semear plano e assinatura ANTES de ligar o
 * enforcement, senão quem já opera perde os módulos.
 */
class LicencaEnforcementTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, Empresa} */
    private function cenario(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        return [$user, $empresa];
    }

    private function assinar(Empresa $empresa, string $slug): void
    {
        $this->seed(PlanosSeeder::class);
        Assinatura::withoutTenant()->create([
            'empresa_id' => $empresa->id,
            'plano_id' => Plano::query()->where('slug', $slug)->firstOrFail()->id,
            'status' => Assinatura::STATUS_ATIVA,
            'inicio' => now()->subDay(),
        ]);
        app(LicencaService::class)->invalidar($empresa->id);
    }

    public function test_sem_assinatura_a_licenca_nao_libera_nada(): void
    {
        [, $empresa] = $this->cenario();

        $licenca = app(LicencaService::class);

        $this->assertFalse($licenca->assinaturaAtiva($empresa->id));
        $this->assertSame([], $licenca->recursosEfetivos($empresa->id));
        $this->assertFalse($licenca->recursoHabilitado('monitora', $empresa->id));
    }

    public function test_modulo_nao_contratado_responde_402(): void
    {
        config()->set('saas_transformation.enforcement.licenca', true);
        [$user, $empresa] = $this->cenario();

        // Essencial não inclui monitoramento GPS.
        $this->assinar($empresa, 'essencial');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/monitora/veiculos')
            ->assertStatus(402);
    }

    public function test_modulo_contratado_passa(): void
    {
        config()->set('saas_transformation.enforcement.licenca', true);
        [$user, $empresa] = $this->cenario();

        $this->assinar($empresa, 'completo');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/monitora/veiculos')
            ->assertOk();
    }

    /**
     * O núcleo do ERP não é add-on: cliente, produto e pedido são o que a
     * revenda contrata por definição, e não podem depender de plano.
     */
    public function test_nucleo_do_erp_nao_depende_de_plano(): void
    {
        config()->set('saas_transformation.enforcement.licenca', true);
        [$user, $empresa] = $this->cenario();
        $this->assinar($empresa, 'essencial');

        foreach (['/api/admin/clientes', '/api/admin/produtos', '/api/admin/pedidos'] as $rota) {
            $this->actingAs($user, 'sanctum')->getJson($rota)->assertOk();
        }
    }

    /** Desligado, a operação atual não pode cair por causa da grade de planos. */
    public function test_flag_desligada_e_passagem_livre(): void
    {
        config()->set('saas_transformation.enforcement.licenca', false);
        [$user] = $this->cenario();

        // Sem assinatura nenhuma, e ainda assim passa.
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/monitora/veiculos')
            ->assertOk();
    }

    public function test_dois_planos_pagos_e_nenhum_gratuito(): void
    {
        $this->seed(PlanosSeeder::class);

        $ativos = Plano::query()->where('ativo', true)->get();

        $this->assertCount(2, $ativos, 'a grade tem dois planos, ambos vendáveis');
        foreach ($ativos as $plano) {
            $this->assertGreaterThan(0, (float) $plano->preco_mensal, "plano {$plano->slug} não pode ser gratuito");
        }

        // Completo cobre o catálogo inteiro: recurso novo entra nele sozinho.
        $completo = Plano::query()->where('slug', 'completo')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            RecursoCatalogo::chaves(),
            $completo->chavesDeRecurso(),
        );

        // Essencial é subconjunto próprio — senão os dois planos seriam iguais.
        $essencial = Plano::query()->where('slug', 'essencial')->firstOrFail();
        $this->assertNotEmpty($essencial->chavesDeRecurso());
        $this->assertLessThan(
            count(RecursoCatalogo::chaves()),
            count($essencial->chavesDeRecurso()),
        );
    }

    public function test_planos_legados_ficam_inativos_mas_nao_sao_apagados(): void
    {
        // Apagar deixaria órfã uma assinatura antiga, e o tenant perderia tudo.
        Plano::query()->create([
            'slug' => 'basico', 'nome' => 'Básico', 'preco_mensal' => 149.90, 'ativo' => true,
        ]);

        $this->seed(PlanosSeeder::class);

        $legado = Plano::query()->where('slug', 'basico')->first();
        $this->assertNotNull($legado, 'plano legado não pode ser excluído');
        $this->assertFalse((bool) $legado->ativo);
    }

    public function test_mapa_de_rota_cobre_os_modulos_opcionais(): void
    {
        $this->assertSame('monitora', RecursoPorRota::recursoDaRota('api/admin/monitora/veiculos'));
        $this->assertSame('crm', RecursoPorRota::recursoDaRota('api/admin/pos-vendas'));
        $this->assertSame('frota', RecursoPorRota::recursoDaRota('api/admin/veiculos/1'));
        $this->assertSame('cobranca', RecursoPorRota::recursoDaRota('api/admin/boletos'));
        $this->assertSame('nfce', RecursoPorRota::recursoDaRota('api/admin/notas'));

        // Núcleo e rotas de fora do admin não entram no mapa.
        $this->assertNull(RecursoPorRota::recursoDaRota('api/admin/clientes'));
        $this->assertNull(RecursoPorRota::recursoDaRota('api/app/v1/pedidos'));
    }
}
