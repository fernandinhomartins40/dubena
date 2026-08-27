<?php

namespace Tests\Feature;

use App\Domain\Acesso\PolicyEvaluator;
use App\Domain\Tenant\TenantContext;
use App\Models\Empresa;
use App\Models\Organizacao\Departamento;
use App\Models\Organizacao\SetorOrg;
use App\Models\Organizacao\Unidade;
use App\Models\Permission;
use App\Models\PermissionCondition;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * GATE da FASE A4 — ABAC (PolicyEvaluator).
 *
 * Cobre os 3 eixos: escopo hierárquico (A3), condições (limite/ownership/horário),
 * e a garantia de NÃO-QUEBRA: sem recurso, a decisão é RBAC puro.
 */
class AbacPolicyEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Empresa,2:Role} cria user com papel que concede $ability na empresa */
    private function cenario(string $ability, array $pivotEscopo = []): array
    {
        $empresa = Empresa::factory()->create();
        app(TenantContext::class)->set($empresa->id, $empresa->grupo_id);

        $user = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => false,
        ]);
        $role = Role::create(['grupo_id' => $empresa->grupo_id, 'nome' => 'Papel']);
        $perm = Permission::firstOrCreate(['chave' => $ability]);
        $role->permissions()->sync([$perm->id]);
        $user->roles()->attach($role->id, array_merge(['empresa_id' => $empresa->id], $pivotEscopo));

        Auth::login($user->fresh());

        return [$user->fresh(), $empresa, $role];
    }

    private function evaluator(): PolicyEvaluator
    {
        return app(PolicyEvaluator::class);
    }

    public function test_sem_recurso_e_rbac_puro(): void
    {
        [$user] = $this->cenario('pedido.aprovar');

        // Sem recurso: permite porque tem a permissão (compatível com A1).
        $this->assertTrue($this->evaluator()->permite($user, 'pedido.aprovar'));
        // Permissão que não tem: nega.
        $this->assertFalse($this->evaluator()->permite($user, 'pedido.cancelar'));
    }

    public function test_escopo_de_unidade_restringe_recurso(): void
    {
        $empresa = Empresa::factory()->create();
        app(TenantContext::class)->set($empresa->id, $empresa->grupo_id);
        $uniA = Unidade::create(['nome' => 'A', 'tipo' => 'filial']);
        $uniB = Unidade::create(['nome' => 'B', 'tipo' => 'filial']);

        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => false]);
        $role = Role::create(['grupo_id' => $empresa->grupo_id, 'nome' => 'GerenteFilial']);
        $perm = Permission::firstOrCreate(['chave' => 'pedido.edit']);
        $role->permissions()->sync([$perm->id]);
        $user->roles()->attach($role->id, ['empresa_id' => $empresa->id, 'unidade_id' => $uniA->id]);
        Auth::login($user->fresh());

        // Recurso da unidade A → permitido; da unidade B → negado.
        $this->assertTrue($this->evaluator()->permite($user->fresh(), 'pedido.edit', ['unidade_id' => $uniA->id]));
        $this->assertFalse($this->evaluator()->permite($user->fresh(), 'pedido.edit', ['unidade_id' => $uniB->id]));
    }

    public function test_escopo_vazio_cobre_a_empresa_inteira(): void
    {
        [$user] = $this->cenario('pedido.edit'); // sem escopo no pivot

        $this->assertTrue($this->evaluator()->permite($user, 'pedido.edit', ['unidade_id' => 999]));
    }

    public function test_departamento_cobre_setor_filho_somente_quando_herda(): void
    {
        $empresa = Empresa::factory()->create();
        app(TenantContext::class)->set($empresa->id, $empresa->grupo_id);
        $unidade = Unidade::create(['nome' => 'Matriz', 'tipo' => 'matriz']);
        $departamento = Departamento::create(['unidade_id' => $unidade->id, 'nome' => 'Operação']);
        $setor = SetorOrg::create(['departamento_id' => $departamento->id, 'nome' => 'Entrega']);
        $outroDepartamento = Departamento::create(['unidade_id' => $unidade->id, 'nome' => 'Financeiro']);
        $outroSetor = SetorOrg::create(['departamento_id' => $outroDepartamento->id, 'nome' => 'Cobrança']);

        $user = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => false,
        ]);
        $role = Role::create(['grupo_id' => $empresa->grupo_id, 'nome' => 'Gerente']);
        $permission = Permission::firstOrCreate(['chave' => 'pedido.edit']);
        $role->permissions()->sync([$permission->id]);
        $user->roles()->attach($role->id, [
            'empresa_id' => $empresa->id,
            'departamento_id' => $departamento->id,
            'herda_filhos' => true,
        ]);
        Auth::login($user->fresh());

        $this->assertTrue($this->evaluator()->permite($user->fresh(), 'pedido.edit', ['setor_id' => $setor->id]));
        $this->assertFalse($this->evaluator()->permite($user->fresh(), 'pedido.edit', ['setor_id' => $outroSetor->id]));

        $user->roles()->updateExistingPivot($role->id, ['herda_filhos' => false]);
        $this->assertFalse($this->evaluator()->permite($user->fresh(), 'pedido.edit', ['setor_id' => $setor->id]));
    }

    public function test_condicao_de_limite_de_valor(): void
    {
        [$user, $empresa, $role] = $this->cenario('pedido.aprovar');

        PermissionCondition::create([
            'empresa_id' => $empresa->id,
            'role_id' => $role->id,
            'permission_id' => Permission::where('chave', 'pedido.aprovar')->value('id'),
            'tipo' => 'limite',
            'parametros' => ['campo' => 'valor', 'valor_max' => 5000],
            'ativo' => true,
        ]);

        $this->assertTrue($this->evaluator()->permite($user, 'pedido.aprovar', ['valor' => 4999]));
        $this->assertTrue($this->evaluator()->permite($user, 'pedido.aprovar', ['valor' => 5000]));
        $this->assertFalse($this->evaluator()->permite($user, 'pedido.aprovar', ['valor' => 5000.01]));
    }

    public function test_condicao_de_ownership(): void
    {
        [$user, $empresa, $role] = $this->cenario('caixa.estornar');

        PermissionCondition::create([
            'empresa_id' => $empresa->id,
            'role_id' => $role->id,
            'permission_id' => Permission::where('chave', 'caixa.estornar')->value('id'),
            'tipo' => 'ownership',
            'parametros' => ['campo_dono' => 'operador_id'],
            'ativo' => true,
        ]);

        // Recurso do próprio usuário → ok; de outro → negado.
        $this->assertTrue($this->evaluator()->permite($user, 'caixa.estornar', ['operador_id' => $user->id]));
        $this->assertFalse($this->evaluator()->permite($user, 'caixa.estornar', ['operador_id' => $user->id + 99]));
    }

    public function test_condicoes_incompletas_ou_desconhecidas_negam(): void
    {
        [$user, $empresa, $role] = $this->cenario('pedido.aprovar');
        $permissionId = Permission::where('chave', 'pedido.aprovar')->value('id');

        PermissionCondition::create([
            'empresa_id' => $empresa->id,
            'role_id' => $role->id,
            'permission_id' => $permissionId,
            'tipo' => 'limite',
            'parametros' => ['campo' => 'valor', 'valor_max' => 5000],
            'ativo' => true,
        ]);
        $this->assertFalse($this->evaluator()->permite($user, 'pedido.aprovar', ['descricao' => 'sem valor']));

        PermissionCondition::query()->delete();
        PermissionCondition::create([
            'empresa_id' => $empresa->id,
            'role_id' => $role->id,
            'permission_id' => $permissionId,
            'tipo' => 'tipo_futuro_desconhecido',
            'parametros' => [],
            'ativo' => true,
        ]);
        $this->assertFalse($this->evaluator()->permite($user, 'pedido.aprovar', ['valor' => 1]));
    }

    public function test_ownership_sem_campo_dono_nega(): void
    {
        [$user, $empresa, $role] = $this->cenario('caixa.estornar');
        PermissionCondition::create([
            'empresa_id' => $empresa->id,
            'role_id' => $role->id,
            'permission_id' => Permission::where('chave', 'caixa.estornar')->value('id'),
            'tipo' => 'ownership',
            'parametros' => ['campo_dono' => 'operador_id'],
            'ativo' => true,
        ]);

        $this->assertFalse($this->evaluator()->permite($user, 'caixa.estornar', ['valor' => 10]));
    }

    public function test_condicao_de_horario(): void
    {
        [$user, $empresa, $role] = $this->cenario('financeiro.baixar');

        // Janela impossível (mesmo minuto de início e fim no passado) para forçar negativa.
        $agora = now();
        PermissionCondition::create([
            'empresa_id' => $empresa->id,
            'role_id' => $role->id,
            'permission_id' => Permission::where('chave', 'financeiro.baixar')->value('id'),
            'tipo' => 'horario',
            'parametros' => ['de' => $agora->copy()->addHours(2)->format('H:i'), 'ate' => $agora->copy()->addHours(3)->format('H:i')],
            'ativo' => true,
        ]);

        // Fora da janela → negado.
        $this->assertFalse($this->evaluator()->permite($user, 'financeiro.baixar', ['valor' => 1]));

        // Janela cobrindo agora → permitido.
        PermissionCondition::query()->update([
            'parametros' => json_encode(['de' => '00:00', 'ate' => '23:59']),
        ]);
        $this->assertTrue($this->evaluator()->permite($user->fresh(), 'financeiro.baixar', ['valor' => 1]));
    }

    public function test_suporte_ignora_abac(): void
    {
        $empresa = Empresa::factory()->create();
        app(TenantContext::class)->set($empresa->id, $empresa->grupo_id);
        $support = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => true]);
        Auth::login($support);

        // Sem papel, sem nada — suporte passa em qualquer recurso/condição.
        $this->assertTrue($this->evaluator()->permite($support, 'pedido.aprovar', ['valor' => 999999]));
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)->clear();
        parent::tearDown();
    }
}
