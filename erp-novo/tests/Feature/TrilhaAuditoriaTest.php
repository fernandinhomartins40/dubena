<?php

namespace Tests\Feature;

use App\Domain\Cliente\ClienteService;
use App\Models\AuditLog;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Rh\Colaborador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Trilha de auditoria — o histórico de ações que responde "quem decidiu isto".
 *
 * O que se garante aqui: a ação semântica substitui o `atualizado` genérico
 * (uma ação humana = uma linha), o autor e o motivo ficam registrados, e a
 * trilha não vaza segredo nem cruza empresa.
 */
class TrilhaAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Empresa} */
    private function suporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);

        return [$user, $empresa];
    }

    public function test_desativar_registra_quem_quando_e_por_que(): void
    {
        [$user, $empresa] = $this->suporte();
        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'ativo' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/admin/clientes/{$cliente->id}", ['motivo' => 'Mudou de cidade'])
            ->assertOk();

        $log = AuditLog::query()->where('entidade', 'clientes')
            ->where('entidade_id', $cliente->id)->where('acao', 'desativou')->first();

        $this->assertNotNull($log, 'a desativação deve gerar uma ação "desativou" na trilha');
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('Mudou de cidade', $log->depois['motivo']);
        // O nome NO MOMENTO da ação: renomear depois não pode reescrever o passado.
        $this->assertSame($cliente->nome, $log->depois['alvo']);
    }

    /**
     * Uma ação humana = UMA linha. O trait grava `atualizado` e a ação semântica
     * o absorve; sem isso a linha do tempo mostra "Desativou" e logo abaixo um
     * "Alterou" do mesmo segundo, e o ruído esconde a decisão.
     */
    public function test_acao_semantica_absorve_o_update_automatico(): void
    {
        [$user, $empresa] = $this->suporte();
        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'ativo' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/admin/clientes/{$cliente->id}", ['motivo' => 'teste'])
            ->assertOk();

        // Só as linhas GERADAS PELA DESATIVAÇÃO (a criação do cliente pelo
        // factory também deixa a sua, e não é o que está sob teste).
        $daDesativacao = AuditLog::query()->where('entidade', 'clientes')
            ->where('entidade_id', $cliente->id)
            ->whereIn('acao', ['desativou', 'atualizado'])
            ->get();

        $this->assertCount(1, $daDesativacao, 'desativar deve gerar UMA linha, não duas');
        $this->assertSame('desativou', $daDesativacao->first()->acao);
        // O diff de colunas foi preservado dentro da ação semântica.
        $this->assertArrayHasKey('ativo', $daDesativacao->first()->depois);
    }

    public function test_reativar_guarda_o_motivo_anterior(): void
    {
        [$user, $empresa] = $this->suporte();
        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'ativo' => false, 'motivo_desativacao' => 'cadastro duplicado',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/clientes/{$cliente->id}/reativar")->assertOk();

        $log = AuditLog::query()->where('entidade', 'clientes')
            ->where('entidade_id', $cliente->id)->where('acao', 'reativou')->first();

        $this->assertNotNull($log);
        // O motivo some do cadastro ao reativar; a trilha preserva por que ele
        // tinha saído da lista.
        $this->assertSame('cadastro duplicado', $log->depois['desativado_antes_por_motivo']);
    }

    public function test_trilha_geral_traduz_para_linguagem_de_gente(): void
    {
        [$user, $empresa] = $this->suporte();
        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'ativo' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/admin/clientes/{$cliente->id}", ['motivo' => 'Mudou de cidade'])->assertOk();

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/auditoria/trilha')
            ->assertOk()
            ->assertJsonPath('data.0.acao_rotulo', 'Desativou')
            ->assertJsonPath('data.0.entidade_rotulo', 'Cliente')
            ->assertJsonPath('data.0.autor', $user->name)
            ->assertJsonPath('data.0.motivo', 'Mudou de cidade')
            ->assertJsonPath('data.0.sensivel', true);
    }

    public function test_trilha_do_registro_agrupa_por_acao(): void
    {
        [$user, $empresa] = $this->suporte();
        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'ativo' => true,
        ]);

        $this->actingAs($user, 'sanctum')->deleteJson("/api/admin/clientes/{$cliente->id}")->assertOk();
        $this->actingAs($user, 'sanctum')->postJson("/api/admin/clientes/{$cliente->id}/reativar")->assertOk();

        $resp = $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/auditoria/registro/clientes/{$cliente->id}")
            ->assertOk()
            ->assertJsonPath('entidade_rotulo', 'Cliente');

        $acoes = collect($resp->json('resumo'))->pluck('total', 'acao');
        $this->assertSame(1, $acoes['desativou']);
        $this->assertSame(1, $acoes['reativou']);
    }

    /** Entidade fora do catálogo não pode virar seletor livre de tabela. */
    public function test_registro_recusa_entidade_fora_do_catalogo(): void
    {
        [$user] = $this->suporte();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/auditoria/registro/password_resets/1')
            ->assertStatus(404);
    }

    public function test_trilha_nao_cruza_empresa(): void
    {
        [$userA, $empresaA] = $this->suporte();
        [$userB] = $this->suporte();

        $clienteA = Cliente::factory()->create([
            'empresa_id' => $empresaA->id, 'grupo_id' => $empresaA->grupo_id, 'ativo' => true,
        ]);
        $this->actingAs($userA, 'sanctum')->deleteJson("/api/admin/clientes/{$clienteA->id}")->assertOk();

        // O usuário da outra empresa não enxerga a ação da empresa A.
        // A trilha de B nao pode conter NENHUMA acao sobre o cliente de A.
        // (B enxerga acoes proprias da empresa dele, como a criacao do seu
        // usuario — o que importa e nao alcancar o dado da empresa A.)
        $daEmpresaA = collect(
            $this->actingAs($userB, 'sanctum')->getJson('/api/admin/auditoria/trilha')
                ->assertOk()->json('data'),
        )->where('entidade', 'clientes')->where('entidade_id', $clienteA->id);

        $this->assertCount(0, $daEmpresaA, 'a trilha nao pode cruzar empresa');

        // E a consulta direta pelo registro da outra empresa tambem vem vazia.
        $this->actingAs($userB, 'sanctum')
            ->getJson("/api/admin/auditoria/registro/clientes/{$clienteA->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        unset($empresaA);
    }

    /** A trilha é lida por gente do negócio: nada nela pode servir de invasão. */
    public function test_trilha_nunca_registra_senha(): void
    {
        [$user, $empresa] = $this->suporte();

        $novo = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'password' => 'segredo-do-usuario',
        ]);

        $log = AuditLog::query()->where('entidade', 'users')
            ->where('entidade_id', $novo->id)->first();

        $this->assertNotNull($log, 'criar usuário deve entrar na trilha');
        $this->assertArrayNotHasKey('password', $log->depois ?? []);
        $this->assertArrayNotHasKey('remember_token', $log->depois ?? []);
        $this->assertStringNotContainsString('segredo-do-usuario', json_encode($log->depois));

        unset($user);
    }

    public function test_colaborador_tambem_entra_na_trilha(): void
    {
        [$user, $empresa] = $this->suporte();
        $colab = Colaborador::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'ativo' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/admin/colaboradores/{$colab->id}", ['motivo' => 'Desligado'])
            ->assertOk();

        $log = AuditLog::query()->where('entidade', 'colaboradores')
            ->where('entidade_id', $colab->id)->where('acao', 'desativou')->first();

        $this->assertNotNull($log);
        $this->assertSame('Desligado', $log->depois['motivo']);
        $this->assertSame($user->id, $log->user_id);
    }

    /** Encerramento pelo titular (LGPD) tem verbo próprio: não é decisão do operador. */
    public function test_encerramento_pelo_titular_tem_acao_propria(): void
    {
        [$user, $empresa] = $this->suporte();
        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'ativo' => true,
        ]);

        app(ClienteService::class)->encerrarPeloTitular($cliente);

        $this->assertDatabaseHas('audit_logs', [
            'entidade' => 'clientes',
            'entidade_id' => $cliente->id,
            'acao' => 'encerrou_conta',
        ]);

        unset($user);
    }

    public function test_opcoes_lista_apenas_o_que_existe_na_trilha(): void
    {
        [$user, $empresa] = $this->suporte();
        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'ativo' => true,
        ]);
        $this->actingAs($user, 'sanctum')->deleteJson("/api/admin/clientes/{$cliente->id}")->assertOk();

        $resp = $this->actingAs($user, 'sanctum')->getJson('/api/admin/auditoria/opcoes')->assertOk();

        $this->assertContains('clientes', collect($resp->json('data.entidades'))->pluck('valor')->all());
        $this->assertContains('desativou', collect($resp->json('data.acoes'))->pluck('valor')->all());
        $this->assertContains($user->name, collect($resp->json('data.autores'))->pluck('rotulo')->all());
    }

    /** A busca do filtro precisa alcançar o desativado — é sobre ele que se pergunta. */
    public function test_busca_de_cliente_encontra_desativado(): void
    {
        [$user, $empresa] = $this->suporte();
        Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'nome' => 'Zulmira Desativada', 'ativo' => false,
        ]);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/auditoria/clientes?q=Zulmira')
            ->assertOk()
            ->assertJsonPath('data.0.nome', 'Zulmira Desativada')
            ->assertJsonPath('data.0.ativo', false);
    }

    public function test_sem_permissao_recebe_403(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->semPapel()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/auditoria/trilha')->assertStatus(403);
    }
}
