<?php

namespace Tests\Feature;

use App\Domain\Saas\LicencaService;
use App\Domain\Saas\LimiteContratado;
use App\Domain\Saas\RecursoCatalogo;
use App\Domain\Saas\SuperAdminService;
use App\Http\Middleware\RecursoPorRota;
use App\Models\Empresa;
use App\Models\Saas\Assinatura;
use App\Models\Saas\LimiteOverride;
use App\Models\Saas\Plano;
use App\Models\Saas\PlanoLimite;
use App\Models\Saas\RecursoOverride;
use App\Models\User;
use Database\Seeders\PlanosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
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

    /**
     * F2-03 (pendência) — limite numérico. Recurso diz "tem"; limite, "até
     * quanto". Num SaaS é o limite que separa a revenda de bairro da rede.
     */
    public function test_limite_do_plano_vale_e_ilimitado_e_null(): void
    {
        [, $empresa] = $this->cenario();
        $licenca = app(LicencaService::class);

        $this->assinar($empresa, 'essencial');

        // Sem teto declarado, o plano e ILIMITADO: o seeder nao fixa numero, e
        // a grade e definida pelo dono no painel SuperAdmin.
        $this->assertNull($licenca->limite('empresas', $empresa->id));
        $this->assertTrue($licenca->dentroDoLimite('empresas', 9999, $empresa->id));

        // Com teto definido (como o painel faria), ele passa a valer.
        PlanoLimite::query()->create([
            'plano_id' => Plano::query()->where('slug', 'essencial')->firstOrFail()->id,
            'limite_chave' => 'empresas',
            'valor' => 2,
        ]);
        $licenca->invalidar($empresa->id);

        $this->assertSame(2, $licenca->limite('empresas', $empresa->id));
        $this->assertTrue($licenca->dentroDoLimite('empresas', 1, $empresa->id));
        $this->assertFalse($licenca->dentroDoLimite('empresas', 2, $empresa->id));
    }

    /** Fail-closed também nos limites: sem contrato, teto zero. */
    public function test_sem_assinatura_o_teto_e_zero_e_nao_ilimitado(): void
    {
        [, $empresa] = $this->cenario();
        $licenca = app(LicencaService::class);

        $this->assertSame(0, $licenca->limite('usuarios', $empresa->id));
        $this->assertFalse($licenca->dentroDoLimite('usuarios', 0, $empresa->id));
    }

    public function test_override_de_limite_sobrepoe_o_plano_e_expira(): void
    {
        [, $empresa] = $this->cenario();
        $this->assinar($empresa, 'essencial');
        $licenca = app(LicencaService::class);
        PlanoLimite::query()->create([
            'plano_id' => Plano::query()->where('slug', 'essencial')->firstOrFail()->id,
            'limite_chave' => 'empresas', 'valor' => 2,
        ]);
        $licenca->invalidar($empresa->id);

        $this->assertSame(2, $licenca->limite('empresas', $empresa->id));

        // Cortesia com prazo.
        LimiteOverride::withoutTenant()->create([
            'empresa_id' => $empresa->id,
            'limite_chave' => 'empresas',
            'valor' => 10,
            'motivo' => 'piloto comercial — chamado 51',
            'expira_em' => now()->addDay(),
        ]);
        $licenca->invalidar($empresa->id);
        $this->assertSame(10, $licenca->limite('empresas', $empresa->id));

        // Expirado, volta a valer o plano: cortesia com prazo tem de acabar.
        LimiteOverride::withoutTenant()->where('empresa_id', $empresa->id)
            ->update(['expira_em' => now()->subMinute()]);
        $licenca->invalidar($empresa->id);
        $this->assertSame(2, $licenca->limite('empresas', $empresa->id));
    }

    /**
     * A mesma expiração vale para o override de RECURSO — era permanente antes,
     * e um piloto de 30 dias virava dois anos por esquecimento.
     */
    public function test_override_de_recurso_expira(): void
    {
        [, $empresa] = $this->cenario();
        $this->assinar($empresa, 'essencial'); // sem monitoramento
        $licenca = app(LicencaService::class);

        $this->assertFalse($licenca->recursoHabilitado('monitora', $empresa->id));

        RecursoOverride::withoutTenant()->create([
            'empresa_id' => $empresa->id,
            'recurso_chave' => 'monitora',
            'habilitado' => true,
            'motivo' => 'cortesia — avaliação',
            'expira_em' => now()->addDay(),
        ]);
        $licenca->invalidar($empresa->id);
        $this->assertTrue($licenca->recursoHabilitado('monitora', $empresa->id));

        RecursoOverride::withoutTenant()->where('empresa_id', $empresa->id)
            ->update(['expira_em' => now()->subMinute()]);
        $licenca->invalidar($empresa->id);
        $this->assertFalse($licenca->recursoHabilitado('monitora', $empresa->id));
    }

    /** Sobrepor plano contratado é exceção comercial: sem motivo, não passa. */
    public function test_override_exige_motivo(): void
    {
        [, $empresa] = $this->cenario();
        $servico = app(SuperAdminService::class);

        $this->expectException(HttpException::class);
        $servico->definirOverride($empresa->id, 'monitora', true, '   ');
    }

    public function test_override_recusa_chave_fora_do_catalogo(): void
    {
        [, $empresa] = $this->cenario();
        $servico = app(SuperAdminService::class);

        $this->expectException(HttpException::class);
        $servico->definirLimiteOverride($empresa->id, 'limite_inventado', 5, 'teste');
    }

    /**
     * F2-03 — o teto passa a RECUSAR, não só a informar.
     *
     * Antes disto `dentroDoLimite()` existia e ninguém o chamava: a decisão
     * estava pronta e nenhuma porta de criação a consultava.
     */
    public function test_criacao_de_empresa_para_no_teto_do_plano(): void
    {
        config()->set('saas_transformation.enforcement.licenca', true);
        config()->set('saas_transformation.freeze.company_creation', false);

        [$user, $empresa] = $this->cenario();
        $this->assinar($empresa, 'essencial');
        PlanoLimite::query()->create([
            'plano_id' => Plano::query()->where('slug', 'essencial')->firstOrFail()->id,
            'limite_chave' => 'empresas', 'valor' => 2,
        ]);
        app(LicencaService::class)->invalidar($empresa->id);

        // A empresa do cenário é a 1ª; criar a 2ª cabe.
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/empresas', ['razao_social' => 'Filial 2'])
            ->assertCreated();

        // A 3ª estoura o teto do Essencial.
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/empresas', ['razao_social' => 'Filial 3'])
            ->assertStatus(402);
    }

    public function test_criacao_de_usuario_para_no_teto_do_plano(): void
    {
        config()->set('saas_transformation.enforcement.licenca', true);
        [$user, $empresa] = $this->cenario();

        // Teto de 1 usuário: o próprio ator já ocupa a vaga.
        $this->assinar($empresa, 'essencial');
        LimiteOverride::withoutTenant()->create([
            'empresa_id' => $empresa->id,
            'limite_chave' => 'usuarios',
            'valor' => 1,
            'motivo' => 'teste',
        ]);
        app(LicencaService::class)->invalidar($empresa->id);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/usuarios', [
                'name' => 'Novo', 'email' => 'novo@teste.test',
                'password' => 'Segredo!123', 'password_confirmation' => 'Segredo!123',
            ])
            ->assertStatus(402);
    }

    /** Usuário inativo não ocupa vaga — senão desligar alguém não liberaria espaço. */
    public function test_usuario_inativo_nao_ocupa_vaga(): void
    {
        [, $empresa] = $this->cenario();
        $inativo = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'ativo' => false,
        ]);

        $uso = app(LimiteContratado::class)->usoAtual('usuarios', $empresa->id);

        $this->assertSame(1, $uso, 'só o ator ativo do cenário conta');
        $this->assertNotNull($inativo->id);
    }

    /** Desligado, o teto não recusa nada: a operação atual não pode cair. */
    public function test_enforcement_desligado_nao_recusa(): void
    {
        config()->set('saas_transformation.enforcement.licenca', false);
        [$user, $empresa] = $this->cenario();
        $this->assinar($empresa, 'essencial');
        LimiteOverride::withoutTenant()->create([
            'empresa_id' => $empresa->id, 'limite_chave' => 'usuarios', 'valor' => 0, 'motivo' => 'teste',
        ]);
        app(LicencaService::class)->invalidar($empresa->id);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/usuarios', [
                'name' => 'Novo', 'email' => 'novo2@teste.test',
                'password' => 'Segredo!123', 'password_confirmation' => 'Segredo!123',
            ])
            ->assertCreated();
    }

    /**
     * O painel precisa mostrar o teto EFETIVO, não o do plano: com uma cortesia
     * ativa os dois divergem, e exibir o do plano faria quem concedeu achar que
     * ela não pegou.
     */
    public function test_endpoint_de_limites_devolve_o_teto_efetivo(): void
    {
        [, $empresa] = $this->cenario();
        $this->assinar($empresa, 'essencial');

        $plano = Plano::query()->where('slug', 'essencial')->firstOrFail();
        PlanoLimite::query()->create([
            'plano_id' => $plano->id, 'limite_chave' => 'usuarios', 'valor' => 5,
        ]);
        LimiteOverride::withoutTenant()->create([
            'empresa_id' => $empresa->id, 'limite_chave' => 'usuarios',
            'valor' => 30, 'motivo' => 'cortesia',
        ]);
        app(LicencaService::class)->invalidar($empresa->id);

        $licenca = app(LicencaService::class);

        // O override vence o plano — é o teto que de fato vale.
        $this->assertSame(30, $licenca->limite('usuarios', $empresa->id));

        // Limite sem teto declarado continua ilimitado.
        $this->assertNull($licenca->limite('veiculos_monitorados', $empresa->id));
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
