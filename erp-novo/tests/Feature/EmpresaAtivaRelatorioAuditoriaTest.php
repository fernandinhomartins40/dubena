<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Grupo;
use App\Models\LoginLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmpresaAtivaRelatorioAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, Empresa, Empresa} */
    private function cenario(): array
    {
        $grupo = Grupo::factory()->create();
        $padrao = Empresa::factory()->create(['grupo_id' => $grupo->id]);
        $ativa = Empresa::factory()->create(['grupo_id' => $grupo->id]);
        $user = User::factory()->create([
            'empresa_id' => $padrao->id,
            'grupo_id' => $grupo->id,
            'support' => true,
        ]);

        Cliente::factory()->create(['empresa_id' => $padrao->id, 'grupo_id' => $grupo->id]);
        Cliente::factory()->count(2)->create(['empresa_id' => $ativa->id, 'grupo_id' => $grupo->id]);

        return [$user, $padrao, $ativa];
    }

    public function test_dashboard_consulta_empresa_ativa_em_vez_da_padrao(): void
    {
        [$user, , $ativa] = $this->cenario();

        $this->actingAs($user, 'sanctum')
            ->withHeader('X-Empresa-Id', (string) $ativa->id)
            ->getJson('/api/admin/dashboard/resumo')
            ->assertOk()
            ->assertJsonPath('clientes', 2);
    }

    public function test_logs_e_trilha_consultam_somente_empresa_ativa(): void
    {
        [$user, $padrao, $ativa] = $this->cenario();

        foreach ([[$padrao, 'PADRAO'], [$ativa, 'ATIVA']] as [$empresa, $marca]) {
            LoginLog::create([
                'empresa_id' => $empresa->id,
                'email' => strtolower($marca).'@example.test',
                'ip' => '127.0.0.1',
                'sucesso' => true,
            ]);
            SecurityEvent::withoutTenant()->create([
                'empresa_id' => $empresa->id,
                'user_id' => $user->id,
                'tipo' => 'teste.'.strtolower($marca),
                'alvo' => $marca,
                'detalhes' => [],
                'ip' => '127.0.0.1',
            ]);
            AuditLog::create([
                'empresa_id' => $empresa->id,
                'entidade' => 'teste_empresa_ativa',
                'entidade_id' => $empresa->id,
                'acao' => 'updated',
                'user_id' => $user->id,
                'depois' => ['marca' => $marca],
                'ip' => '127.0.0.1',
                'criado_em' => now(),
            ]);
        }

        $cabecalho = ['X-Empresa-Id' => (string) $ativa->id];

        $this->actingAs($user, 'sanctum')->withHeaders($cabecalho)
            ->getJson('/api/admin/auditoria/logins')->assertOk()
            ->assertJsonPath('data.0.email', 'ativa@example.test')
            ->assertJsonCount(1, 'data');

        $this->actingAs($user, 'sanctum')->withHeaders($cabecalho)
            ->getJson('/api/admin/auditoria/eventos')->assertOk()
            ->assertJsonPath('data.0.alvo', 'ATIVA')
            ->assertJsonCount(1, 'data');

        $this->actingAs($user, 'sanctum')->withHeaders($cabecalho)
            ->getJson('/api/admin/auditoria/trilha?entidade=teste_empresa_ativa')->assertOk()
            ->assertJsonPath('data.0.alteracoes.0.para', 'ATIVA')
            ->assertJsonCount(1, 'data');

        $this->actingAs($user, 'sanctum')->withHeaders($cabecalho)
            ->getJson('/api/admin/relatorios/auditoria?entidade=teste_empresa_ativa')->assertOk()
            ->assertJsonPath('data.0.depois.marca', 'ATIVA')
            ->assertJsonCount(1, 'data');
    }

    public function test_permissao_de_custo_da_empresa_padrao_nao_vaza_na_ativa(): void
    {
        $grupo = Grupo::factory()->create();
        $padrao = Empresa::factory()->create(['grupo_id' => $grupo->id]);
        $ativa = Empresa::factory()->create(['grupo_id' => $grupo->id]);
        $user = User::factory()->create([
            'empresa_id' => $padrao->id,
            'grupo_id' => $grupo->id,
            'support' => false,
        ]);
        $user->empresas()->attach($ativa->id);

        $auditoria = Permission::firstOrCreate(['chave' => 'auditoria.view']);
        $verCusto = Permission::firstOrCreate(['chave' => 'produto.campo.custo.view']);
        $papelPadrao = Role::create(['grupo_id' => $grupo->id, 'nome' => 'Custo na padrão']);
        $papelPadrao->permissions()->sync([$auditoria->id, $verCusto->id]);
        $user->roles()->attach($papelPadrao->id, ['empresa_id' => $padrao->id]);

        $papelAtiva = Role::create(['grupo_id' => $grupo->id, 'nome' => 'Auditoria sem custo']);
        $papelAtiva->permissions()->sync([$auditoria->id]);
        $user->roles()->attach($papelAtiva->id, ['empresa_id' => $ativa->id]);

        AuditLog::create([
            'empresa_id' => $ativa->id,
            'entidade' => 'teste_custo_empresa_ativa',
            'entidade_id' => 1,
            'acao' => 'updated',
            'user_id' => $user->id,
            'depois' => ['custo_medio' => 9999.99],
            'criado_em' => now(),
        ]);

        $resposta = $this->actingAs($user, 'sanctum')
            ->withHeader('X-Empresa-Id', (string) $ativa->id)
            ->getJson('/api/admin/auditoria/trilha?entidade=teste_custo_empresa_ativa')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertStringNotContainsString(
            '9999.99',
            json_encode($resposta->json(), JSON_THROW_ON_ERROR),
        );
    }
}
