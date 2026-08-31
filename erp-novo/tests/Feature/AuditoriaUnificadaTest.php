<?php

namespace Tests\Feature;

use App\Domain\Auditoria\ContextoAuditoria;
use App\Domain\Auditoria\RegistroAcao;
use App\Domain\Saas\AuditoriaPlataforma;
use App\Domain\Seguranca\AuditoriaSeguranca;
use App\Models\AuditLog;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F2-06 — as quatro trilhas passam a falar a mesma língua.
 *
 * Antes: nenhuma das quatro (`audit_logs`, `login_logs`, `security_events`,
 * `platform_audit_logs`) gravava correlação, e três delas tinham a coluna
 * `tenant_account_id` desde a migration 000300 com **ninguém a preenchendo** —
 * coluna vazia não responde pergunta nenhuma.
 *
 * Isso tem duas consequências num SaaS com N revendas:
 *
 *  - "empresa 2" não identifica ninguém sozinho — duas revendas podem ter
 *    unidades homônimas, e a pergunta real é "o que aconteceu no tenant X";
 *  - uma ação humana vira várias linhas em tabelas diferentes, e sem um fio
 *    comum reconstruir "o que aconteceu naquele clique" é adivinhação por
 *    timestamp.
 */
class AuditoriaUnificadaTest extends TestCase
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

    public function test_crud_automatico_grava_tenant_e_correlacao(): void
    {
        config()->set('saas_transformation.enforcement.tenant_envelope', true);
        [$user, $empresa] = $this->cenario();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/clientes', ['nome' => 'Cliente auditado', 'telefones' => [['telefone' => '42999990001']]])
            ->assertCreated();

        $linha = AuditLog::query()->where('entidade', 'clientes')->latest('id')->firstOrFail();

        $this->assertNotNull($linha->tenant_account_id, 'a trilha precisa dizer de qual revenda');
        $this->assertNotNull($linha->correlation_id, 'a trilha precisa dizer de qual requisição');
        $this->assertSame($empresa->id, (int) $linha->empresa_id);
    }

    /**
     * O fio comum é o ponto da tarefa: linhas de tabelas diferentes, geradas
     * pela MESMA requisição, têm de compartilhar o `correlation_id`.
     */
    public function test_a_mesma_requisicao_produz_o_mesmo_correlation_id(): void
    {
        [$user, $empresa] = $this->cenario();
        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        $this->actingAs($user, 'sanctum');
        $contexto = app(ContextoAuditoria::class);

        // Duas trilhas distintas, no mesmo ciclo.
        app(RegistroAcao::class)->registrar($cliente, 'cliente.desativado', 'inadimplência');
        app(AuditoriaSeguranca::class)->registrar('autorizacao.negada', 'cliente.delete');

        $daTrilha = AuditLog::query()->latest('id')->value('correlation_id');
        $doEvento = SecurityEvent::withoutTenant()->latest('id')->value('correlation_id');

        $this->assertSame($contexto->correlationId(), $daTrilha);
        $this->assertSame($daTrilha, $doEvento, 'linhas do mesmo clique compartilham o fio');
    }

    /**
     * `X-Request-Id` é o fio que o cliente já envia — reusá-lo liga a trilha ao
     * log da requisição no servidor, em vez de criar um identificador paralelo.
     */
    public function test_correlacao_respeita_o_header_da_requisicao(): void
    {
        [$user, $empresa] = $this->cenario();

        $this->actingAs($user, 'sanctum')
            ->withHeader('X-Request-Id', 'req-abc-123')
            ->postJson('/api/admin/clientes', ['nome' => 'Com header', 'telefones' => [['telefone' => '42999990002']]])
            ->assertCreated();

        $linha = AuditLog::query()->where('entidade', 'clientes')->latest('id')->firstOrFail();

        $this->assertSame('req-abc-123', $linha->correlation_id);
        $this->assertSame($empresa->id, (int) $linha->empresa_id);
    }

    /** `motivo` virou COLUNA: dentro do JSON não dava para filtrar nem exigir. */
    public function test_motivo_da_acao_semantica_e_coluna_consultavel(): void
    {
        [$user, $empresa] = $this->cenario();
        $this->actingAs($user, 'sanctum');

        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        app(RegistroAcao::class)->registrar($cliente, 'cliente.desativado', 'pediu para sair');

        $this->assertSame(
            'pediu para sair',
            AuditLog::query()->where('motivo', 'pediu para sair')->value('motivo'),
        );
    }

    /**
     * O SuperAdmin opera SEM tenant resolvido — é assim por desenho, senão não
     * cruzaria empresas. Então o tenant da trilha de plataforma vem da empresa
     * ALVO, que é o que identifica de quem é o dado tocado.
     */
    public function test_trilha_de_plataforma_deriva_o_tenant_da_empresa_alvo(): void
    {
        [, $empresa] = $this->cenario();

        app(AuditoriaPlataforma::class)->registrar(
            acao: 'empresa.suspensa',
            empresaId: $empresa->id,
            entidade: 'empresas',
            entidadeId: $empresa->id,
        );

        $linha = DB::table('platform_audit_logs')->latest('id')->first();

        $this->assertNotNull($linha->tenant_account_id, 'sem envelope, o tenant vem da empresa alvo');
        $this->assertNotNull($linha->correlation_id);
    }

    /** Ação global (sem empresa alvo) não inventa tenant. */
    public function test_acao_global_de_plataforma_fica_sem_tenant(): void
    {
        app(AuditoriaPlataforma::class)->registrar(acao: 'plano.criado', entidade: 'planos', entidadeId: 1);

        $linha = DB::table('platform_audit_logs')->latest('id')->first();

        $this->assertNull($linha->tenant_account_id);
        $this->assertNotNull($linha->correlation_id, 'mesmo sem tenant, a correlação existe');
    }

    /**
     * O contraponto do fio comum: requisições DIFERENTES não podem compartilhá-lo.
     *
     * Esta é a regressão que a primeira versão tinha. `scoped` não garante
     * instância nova por requisição — no boot o container já resolve o serviço, e
     * sob Octane a mesma instância atende requisições seguidas. Memorizando num
     * campo solto, a primeira correlação vazava para todas as demais e a trilha
     * passava a dizer que ações de clientes distintos vieram do mesmo clique.
     */
    public function test_requisicoes_distintas_nao_compartilham_o_fio(): void
    {
        [$user, $empresa] = $this->cenario();

        foreach (['Primeiro', 'Segundo'] as $i => $nome) {
            $this->actingAs($user, 'sanctum')
                ->postJson('/api/admin/clientes', [
                    'nome' => $nome,
                    'telefones' => [['telefone' => '4299999100'.$i]],
                ])->assertCreated();
        }

        $fios = AuditLog::query()->where('entidade', 'clientes')
            ->orderByDesc('id')->limit(2)->pluck('correlation_id');

        $this->assertCount(2, $fios->unique(), 'cada requisição tem o seu próprio fio');
        $this->assertSame($empresa->id, (int) AuditLog::query()->latest('id')->value('empresa_id'));
    }

    /**
     * O fio só vale se for navegável: gravar `correlation_id` e não deixar
     * filtrar por ele deixaria a resposta a "o que mais aconteceu naquele
     * clique" enterrada no banco.
     */
    public function test_trilha_filtra_pelo_fio_sem_atravessar_empresa(): void
    {
        [$user, $empresa] = $this->cenario();
        $this->concederAuditoria($user, $empresa);

        $this->actingAs($user, 'sanctum')
            ->withHeader('X-Request-Id', 'clique-unico')
            ->postJson('/api/admin/clientes', [
                'nome' => 'Do clique', 'telefones' => [['telefone' => '42999992001']],
            ])->assertCreated();

        // Linha de OUTRA empresa com o MESMO fio: o filtro não pode alcançá-la.
        $outra = Empresa::factory()->create();
        AuditLog::query()->create([
            'empresa_id' => $outra->id, 'entidade' => 'clientes', 'entidade_id' => 999,
            'acao' => 'created', 'correlation_id' => 'clique-unico', 'criado_em' => now(),
        ]);

        $resposta = $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/auditoria/trilha?correlacao=clique-unico')
            ->assertOk();

        $linhas = collect($resposta->json('data'));

        $this->assertNotEmpty($linhas, 'o fio precisa recortar a trilha');
        $this->assertTrue(
            $linhas->every(fn ($l) => $l['correlacao'] === 'clique-unico'),
            'o filtro devolve só o que nasceu daquele clique',
        );
        $this->assertFalse(
            $linhas->contains(fn ($l) => $l['entidade_id'] === 999),
            'o fio é um filtro DENTRO do tenant, nunca uma porta para fora dele',
        );
    }

    /** A trilha é gated por `auditoria.view`; o usuário do cenário não a tem. */
    private function concederAuditoria(User $user, Empresa $empresa): void
    {
        $papel = Role::create(['grupo_id' => $empresa->grupo_id, 'nome' => 'Auditor F2-06']);
        $papel->permissions()->sync([Permission::firstOrCreate(['chave' => 'auditoria.view'])->id]);
        $user->roles()->attach($papel->id, ['empresa_id' => $empresa->id]);
    }

    /**
     * Login acontece ANTES de existir tenant, e ainda assim precisa ser
     * correlacionável — senão a investigação de um acesso indevido começa
     * justamente sem o fio da entrada.
     */
    public function test_login_pre_tenant_tem_correlacao_e_fica_sem_tenant(): void
    {
        $contexto = app(ContextoAuditoria::class);

        $this->assertNull($contexto->tenantAccountId());
        $this->assertNotEmpty($contexto->correlationId());
    }
}
