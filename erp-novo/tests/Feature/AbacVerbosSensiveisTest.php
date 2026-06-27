<?php

namespace Tests\Feature;

use App\Domain\Caixa\CaixaService;
use App\Domain\Financeiro\FinanceiroService;
use App\Domain\Tenant\TenantContext;
use App\Models\Caixa\Conta;
use App\Models\Empresa;
use App\Models\Financeiro\Financeiro;
use App\Models\Permission;
use App\Models\PermissionCondition;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GATE do ABAC ponto-a-ponto — verbos sensíveis (estornar/baixar) com condições.
 *
 * Garante que `caixa.estornar` e `financeiro.baixar` passam pelo PolicyEvaluator
 * com RECURSO: ownership (estornar só o próprio lançamento) e limite (baixar/
 * estornar até R$ X). Sem o verbo, 403; com verbo mas violando a condição, 403.
 */
class AbacVerbosSensiveisTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Usuário não-suporte com as permissões dadas + tenant ativo.
     *
     * @param  list<string>  $chaves
     * @return array{0:User,1:Empresa,2:Role}
     */
    private function userCom(array $chaves): array
    {
        $empresa = Empresa::factory()->create();
        app(TenantContext::class)->set($empresa->id, $empresa->grupo_id);

        $user = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => false,
        ]);
        $role = Role::create(['grupo_id' => $empresa->grupo_id, 'nome' => 'Operador']);
        $ids = collect($chaves)->map(fn (string $c) => Permission::firstOrCreate(['chave' => $c])->id)->all();
        $role->permissions()->sync($ids);
        $user->roles()->attach($role->id, ['empresa_id' => $empresa->id]);

        return [$user->fresh(), $empresa, $role];
    }

    private function conta(Empresa $e): Conta
    {
        return app(CaixaService::class)->criarConta([
            'empresa_id' => $e->id, 'grupo_id' => $e->grupo_id, 'descricao' => 'Caixa', 'saldo_inicial' => 0,
        ]);
    }

    private function titulo(Empresa $e, float $valor): Financeiro
    {
        return app(FinanceiroService::class)->criar([
            'empresa_id' => $e->id, 'grupo_id' => $e->grupo_id, 'pagarreceber' => 'R', 'valor' => $valor,
        ]);
    }

    public function test_baixar_exige_o_verbo_financeiro_baixar(): void
    {
        // Só com caixa.edit (sem financeiro.baixar) → 403 na baixa.
        [$user, $empresa] = $this->userCom(['caixa.edit']);
        $conta = $this->conta($empresa);
        $parcela = $this->titulo($empresa, 100)->parcelas->first();

        $this->actingAs($user, 'sanctum')->postJson("/api/admin/caixa/{$conta->id}/baixar", [
            'parcela_id' => $parcela->id,
        ])->assertStatus(403);
    }

    public function test_baixar_respeita_limite_abac(): void
    {
        [$user, $empresa, $role] = $this->userCom(['financeiro.baixar']);
        // Condição: baixar só até R$ 150.
        PermissionCondition::create([
            'empresa_id' => $empresa->id, 'role_id' => $role->id,
            'permission_id' => Permission::where('chave', 'financeiro.baixar')->value('id'),
            'tipo' => 'limite', 'parametros' => ['campo' => 'valor', 'valor_max' => 150], 'ativo' => true,
        ]);
        $conta = $this->conta($empresa);

        // Parcela de 100 → permitido.
        $p100 = $this->titulo($empresa, 100)->parcelas->first();
        $this->actingAs($user, 'sanctum')->postJson("/api/admin/caixa/{$conta->id}/baixar", [
            'parcela_id' => $p100->id,
        ])->assertCreated();

        // Parcela de 500 → acima do limite → 403.
        $p500 = $this->titulo($empresa, 500)->parcelas->first();
        $this->actingAs($user, 'sanctum')->postJson("/api/admin/caixa/{$conta->id}/baixar", [
            'parcela_id' => $p500->id,
        ])->assertStatus(403);
    }

    public function test_estornar_exige_verbo_e_respeita_ownership(): void
    {
        // Ator A baixa uma parcela (cria o movimento dele).
        [$userA, $empresa, $roleA] = $this->userCom(['financeiro.baixar', 'caixa.estornar']);
        // Ownership: só estorna o próprio lançamento.
        PermissionCondition::create([
            'empresa_id' => $empresa->id, 'role_id' => $roleA->id,
            'permission_id' => Permission::where('chave', 'caixa.estornar')->value('id'),
            'tipo' => 'ownership', 'parametros' => ['campo_dono' => 'user_id'], 'ativo' => true,
        ]);
        $conta = $this->conta($empresa);
        $parcela = $this->titulo($empresa, 100)->parcelas->first();

        $mov = $this->actingAs($userA, 'sanctum')->postJson("/api/admin/caixa/{$conta->id}/baixar", [
            'parcela_id' => $parcela->id,
        ])->assertCreated()->json('data.id');

        // Ator B (mesma empresa, com estornar+ownership) NÃO pode estornar o de A.
        $userB = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => false]);
        $userB->roles()->attach($roleA->id, ['empresa_id' => $empresa->id]);
        $this->actingAs($userB->fresh(), 'sanctum')->postJson("/api/admin/caixa/movimentos/{$mov}/estornar")
            ->assertStatus(403);

        // O próprio A estorna o seu → ok.
        $this->actingAs($userA, 'sanctum')->postJson("/api/admin/caixa/movimentos/{$mov}/estornar")
            ->assertCreated();
    }

    public function test_estornar_sem_verbo_da_403(): void
    {
        [$user, $empresa] = $this->userCom(['financeiro.baixar']); // sem caixa.estornar
        $conta = $this->conta($empresa);
        $parcela = $this->titulo($empresa, 100)->parcelas->first();
        $mov = $this->actingAs($user, 'sanctum')->postJson("/api/admin/caixa/{$conta->id}/baixar", [
            'parcela_id' => $parcela->id,
        ])->assertCreated()->json('data.id');

        $this->actingAs($user, 'sanctum')->postJson("/api/admin/caixa/movimentos/{$mov}/estornar")
            ->assertStatus(403);
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)->clear();
        parent::tearDown();
    }
}
