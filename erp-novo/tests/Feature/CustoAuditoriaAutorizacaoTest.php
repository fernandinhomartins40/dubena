<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Empresa;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustoAuditoriaAutorizacaoTest extends TestCase
{
    use RefreshDatabase;

    /** @param list<string> $chaves */
    private function usuario(Empresa $empresa, array $chaves): User
    {
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'support' => false,
        ]);
        $role = Role::create([
            'grupo_id' => $empresa->grupo_id,
            'nome' => 'Auditoria custo '.User::query()->count(),
        ]);
        $permissions = collect($chaves)->map(
            fn (string $chave) => Permission::firstOrCreate(['chave' => $chave])->id,
        );
        $role->permissions()->sync($permissions);
        $user->roles()->attach($role->id, ['empresa_id' => $empresa->id]);

        return $user;
    }

    private function log(Empresa $empresa): AuditLog
    {
        return AuditLog::create([
            'empresa_id' => $empresa->id,
            'entidade' => 'produtos',
            'entidade_id' => 123,
            'acao' => 'atualizado',
            'antes' => [
                'descricao' => 'Produto seguro',
                'custo_medio' => 9876.5432,
                'produto' => ['custofrete' => 8765.4321],
            ],
            'depois' => [
                'descricao' => 'Produto seguro alterado',
                'custo_medio' => 7654.321,
                'custo_frete' => 6543.21,
            ],
            'criado_em' => now(),
        ]);
    }

    public function test_custo_some_das_duas_superficies_sem_permissao_e_permanece_no_banco(): void
    {
        $empresa = Empresa::factory()->create();
        $user = $this->usuario($empresa, ['auditoria.view', 'relatorio.view']);
        $log = $this->log($empresa);

        $trilha = $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/auditoria/trilha?entidade=produtos')->assertOk();
        $relatorio = $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/relatorios/auditoria?entidade=produtos')->assertOk();

        foreach ([$trilha, $relatorio] as $resposta) {
            $json = json_encode($resposta->json(), JSON_THROW_ON_ERROR);
            foreach (['custo_medio', 'custo_frete', 'customedio', 'custofrete'] as $campo) {
                $this->assertStringNotContainsString($campo, $json);
            }
            foreach (['9876.5432', '8765.4321', '7654.321', '6543.21'] as $valor) {
                $this->assertStringNotContainsString($valor, $json);
            }
        }

        $this->assertSame(9876.5432, (float) $log->fresh()->antes['custo_medio']);
        $this->assertSame(6543.21, (float) $log->fresh()->depois['custo_frete']);
    }

    public function test_observador_com_view_recebe_diff_e_valores_brutos_de_custo(): void
    {
        $empresa = Empresa::factory()->create();
        $user = $this->usuario($empresa, [
            'auditoria.view', 'relatorio.view', 'produto.campo.custo.view',
        ]);
        $this->log($empresa);

        $trilha = $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/auditoria/trilha?entidade=produtos')->assertOk();
        $this->assertContains('custo_medio', collect($trilha->json('data.0.alteracoes'))->pluck('campo')->all());

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/relatorios/auditoria?entidade=produtos')
            ->assertOk()
            ->assertJsonPath('data.0.antes.custo_medio', 9876.5432)
            ->assertJsonPath('data.0.depois.custo_frete', 6543.21);
    }
}
