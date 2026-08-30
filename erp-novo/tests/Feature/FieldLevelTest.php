<?php

namespace Tests\Feature;

use App\Domain\Shared\PermissaoCatalogo;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Permission;
use App\Models\Produto\Produto;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GATE da FASE A7 — granularidade fina (field-level + export).
 *
 * Garante: campo sensível some do payload sem `...campo.{nome}.view`; é ignorado
 * na escrita sem `...campo.{nome}.edit`; export é gated por `cliente.export`; e o
 * catálogo inclui as chaves granulares (contrato).
 */
class FieldLevelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<string>  $chaves
     * @return array{0:User,1:Empresa}
     */
    private function userCom(array $chaves): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->semPapel()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        $role = Role::create(['grupo_id' => $empresa->grupo_id, 'nome' => 'Papel']);
        $ids = collect($chaves)->map(fn (string $c) => Permission::firstOrCreate(['chave' => $c])->id)->all();
        $role->permissions()->sync($ids);
        $user->roles()->attach($role->id, ['empresa_id' => $empresa->id]);

        return [$user->fresh(), $empresa];
    }

    private function cliente(Empresa $empresa): Cliente
    {
        return Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'credito_limite' => 5000, 'credito_saldo' => 1200, 'convenio_limite' => 800,
        ]);
    }

    public function test_catalogo_inclui_chaves_granulares(): void
    {
        $chaves = PermissaoCatalogo::chaves();
        $this->assertContains('cliente.campo.credito_limite.view', $chaves);
        $this->assertContains('cliente.export', $chaves);
        $this->assertContains('relatorio.dre.view', $chaves);
        $this->assertContains('produto.campo.custo.view', $chaves);
    }

    public function test_campo_sensivel_oculto_sem_permissao_de_ver(): void
    {
        [$user, $empresa] = $this->userCom(['cliente.view']); // sem campo.*.view
        $cli = $this->cliente($empresa);

        $resp = $this->actingAs($user, 'sanctum')->getJson("/api/admin/clientes/{$cli->id}")->assertOk();

        // Campos controlados não viajam.
        $resp->assertJsonMissingPath('data.credito_limite');
        $resp->assertJsonMissingPath('data.credito_saldo');
        $resp->assertJsonMissingPath('data.convenio_limite');
        // Campo livre continua presente.
        $resp->assertJsonPath('data.nome', $cli->nome);
    }

    public function test_campo_sensivel_visivel_com_permissao(): void
    {
        [$user, $empresa] = $this->userCom([
            'cliente.view', 'cliente.campo.credito_limite.view',
        ]);
        $cli = $this->cliente($empresa);

        $resp = $this->actingAs($user, 'sanctum')->getJson("/api/admin/clientes/{$cli->id}")->assertOk();

        $this->assertSame(5000.0, (float) $resp->json('data.credito_limite'));
        // Os outros (sem view) continuam ocultos.
        $resp->assertJsonMissingPath('data.credito_saldo');
    }

    public function test_suporte_ve_todos_os_campos(): void
    {
        // Comportamento do modo LEGADO: com o enforcement ligado, `support`
        // sozinho nao autoriza mais — quem autoriza e o break-glass (F2-05).
        config()->set('saas_transformation.enforcement.tenant_envelope', false);
        $empresa = Empresa::factory()->create();
        $support = User::factory()->semPapel()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        // `support` não é fillable (T1.8). No modo legado ele ainda vale por si;
        // com o enforcement é o break-glass que autoriza (F2-05).
        $support->forceFill(['support' => true])->save();
        $support = $support->fresh();
        $cli = $this->cliente($empresa);

        $resp = $this->actingAs($support, 'sanctum')->getJson("/api/admin/clientes/{$cli->id}")->assertOk();
        $this->assertSame(5000.0, (float) $resp->json('data.credito_limite'));
    }

    public function test_campo_sem_edit_e_ignorado_na_escrita(): void
    {
        [$user, $empresa] = $this->userCom([
            'cliente.edit', 'cliente.campo.credito_limite.view', // pode ver, mas NÃO editar
        ]);
        $cli = $this->cliente($empresa);

        $this->actingAs($user, 'sanctum')->putJson("/api/admin/clientes/{$cli->id}", [
            'nome' => 'Atualizado',
            'credito_limite' => 99999, // deve ser IGNORADO
        ])->assertOk();

        $cli->refresh();
        $this->assertSame('Atualizado', $cli->nome);
        $this->assertSame(5000.0, (float) $cli->credito_limite, 'credito_limite não pode mudar sem permissão de editar.');
    }

    public function test_campo_com_edit_e_gravado(): void
    {
        [$user, $empresa] = $this->userCom([
            'cliente.edit', 'cliente.campo.credito_limite.edit',
        ]);
        $cli = $this->cliente($empresa);

        $this->actingAs($user, 'sanctum')->putJson("/api/admin/clientes/{$cli->id}", [
            'nome' => $cli->nome, 'credito_limite' => 7777,
        ])->assertOk();

        $this->assertSame(7777.0, (float) $cli->refresh()->credito_limite);
    }

    public function test_export_exige_permissao(): void
    {
        [$semExport, $empresa] = $this->userCom(['cliente.view']);
        $this->actingAs($semExport, 'sanctum')->getJson('/api/admin/clientes/exportar')->assertStatus(403);

        [$comExport] = $this->userCom(['cliente.export']);
        $this->actingAs($comExport, 'sanctum')->get('/api/admin/clientes/exportar')->assertOk();
    }

    public function test_custo_do_produto_fica_oculto_sem_permissao_granular(): void
    {
        [$user, $empresa] = $this->userCom(['produto.view']);
        $produto = Produto::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'preco_venda' => 100,
            'custo_medio' => 60,
            'custo_frete' => 5,
        ]);

        $resposta = $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/produtos/{$produto->id}")
            ->assertOk();

        $resposta->assertJsonMissingPath('data.customedio');
        $resposta->assertJsonMissingPath('data.custo_medio');
        $resposta->assertJsonPath('data.precovenda', 100);
    }

    public function test_custo_do_produto_exige_permissao_para_editar(): void
    {
        [$user, $empresa] = $this->userCom(['produto.edit']);
        $produto = Produto::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Produto protegido',
            'custo_medio' => 60,
        ]);

        $this->actingAs($user, 'sanctum')->putJson("/api/admin/produtos/{$produto->id}", [
            'descricao' => 'Produto renomeado',
            'customedio' => 1,
        ])->assertOk();

        $produto->refresh();
        $this->assertSame('Produto renomeado', $produto->descricao);
        $this->assertSame(60.0, (float) $produto->custo_medio);
    }
}
