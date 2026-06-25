<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Geografico\Bairro;
use App\Models\Geografico\Cidade;
use App\Models\Geografico\Rua;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F11 — trilha de auditoria unificada + detecção de inconsistências (rua/bairro).
 */
class F11AuditoriaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Empresa} */
    private function suporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => true]);

        return [$user, $empresa];
    }

    public function test_create_update_delete_geram_trilha(): void
    {
        [$user, $empresa] = $this->suporte();

        $cliente = Cliente::withoutTenant()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'nome' => 'Original']);
        $cliente->update(['nome' => 'Editado']);
        $cliente->delete();

        $logs = AuditLog::query()->where('entidade', 'clientes')->where('entidade_id', $cliente->id)->orderBy('id')->get();
        $this->assertSame(['criado', 'atualizado', 'excluido'], $logs->pluck('acao')->all());
        // O update só registra o campo alterado.
        $this->assertSame(['nome' => 'Editado'], $logs[1]->depois);
        $this->assertSame('Original', $logs[1]->antes['nome']);
    }

    public function test_segredo_nao_entra_na_trilha(): void
    {
        [, $empresa] = $this->suporte();

        // EmpresaConfig tem cert_senha (cast encrypted) — não pode aparecer no log.
        $cfg = \App\Models\EmpresaConfig::query()->create(['empresa_id' => $empresa->id, 'cert_senha' => 'segredo123']);

        $log = AuditLog::query()->where('entidade', 'empresa_configs')->where('entidade_id', $cfg->id)->first();
        $this->assertNotNull($log);
        $this->assertArrayNotHasKey('cert_senha', $log->depois ?? []);
    }

    public function test_endpoint_auditoria_filtra_por_entidade(): void
    {
        [$user, $empresa] = $this->suporte();
        Cliente::withoutTenant()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'nome' => 'A']);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/relatorios/auditoria?entidade=clientes')
            ->assertOk()
            ->assertJsonPath('data.0.entidade', 'clientes');
    }

    public function test_inconsistencias_detecta_ruas_similares(): void
    {
        [$user, $empresa] = $this->suporte();
        \App\Models\Estado::query()->firstOrCreate(['uf' => 'PR'], ['descricao' => 'Paraná']);
        $cidade = Cidade::withoutGrupo()->create(['grupo_id' => $empresa->grupo_id, 'descricao' => 'Curitiba', 'uf' => 'PR']);

        // Duas ruas com nomes quase iguais (typo) na mesma cidade → apontadas.
        Rua::withoutGrupo()->create(['grupo_id' => $empresa->grupo_id, 'cidade_id' => $cidade->id, 'descricao' => 'Rua das Flores']);
        Rua::withoutGrupo()->create(['grupo_id' => $empresa->grupo_id, 'cidade_id' => $cidade->id, 'descricao' => 'Rua das Florese']);
        // Uma rua diferente → não deve casar.
        Rua::withoutGrupo()->create(['grupo_id' => $empresa->grupo_id, 'cidade_id' => $cidade->id, 'descricao' => 'Avenida Brasil']);

        $data = $this->actingAs($user, 'sanctum')->getJson('/api/admin/cadastros/inconsistencias?tipo=ruas')
            ->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('rua', $data[0]['tipo']);
        $this->assertGreaterThanOrEqual(0.85, $data[0]['similaridade']);
    }

    public function test_inconsistencias_nao_cruza_cidades(): void
    {
        [$user, $empresa] = $this->suporte();
        \App\Models\Estado::query()->firstOrCreate(['uf' => 'PR'], ['descricao' => 'Paraná']);
        \App\Models\Estado::query()->firstOrCreate(['uf' => 'SP'], ['descricao' => 'São Paulo']);
        $c1 = Cidade::withoutGrupo()->create(['grupo_id' => $empresa->grupo_id, 'descricao' => 'A', 'uf' => 'PR']);
        $c2 = Cidade::withoutGrupo()->create(['grupo_id' => $empresa->grupo_id, 'descricao' => 'B', 'uf' => 'SP']);
        Bairro::withoutGrupo()->create(['grupo_id' => $empresa->grupo_id, 'cidade_id' => $c1->id, 'descricao' => 'Centro']);
        Bairro::withoutGrupo()->create(['grupo_id' => $empresa->grupo_id, 'cidade_id' => $c2->id, 'descricao' => 'Centro']);

        // Nomes iguais mas em CIDADES diferentes → não é duplicata.
        $data = $this->actingAs($user, 'sanctum')->getJson('/api/admin/cadastros/inconsistencias?tipo=bairros')
            ->assertOk()->json('data');
        $this->assertEmpty($data);
    }
}
