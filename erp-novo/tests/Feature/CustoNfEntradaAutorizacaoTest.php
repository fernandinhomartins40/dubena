<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Estoque\EstoqueSaldo;
use App\Models\Estoque\Setor;
use App\Models\Financeiro\Financeiro;
use App\Models\Fiscal\NfRecebida;
use App\Models\Permission;
use App\Models\Produto\Produto;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustoNfEntradaAutorizacaoTest extends TestCase
{
    use RefreshDatabase;

    /** @param list<string> $permissoes */
    private function cenario(array $permissoes): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->semPapel()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);
        $role = Role::create(['grupo_id' => $empresa->grupo_id, 'nome' => 'NF entrada segura']);
        $ids = collect($permissoes)->map(
            fn (string $chave) => Permission::firstOrCreate(['chave' => $chave])->id,
        );
        $role->permissions()->sync($ids);
        $user->roles()->attach($role->id, ['empresa_id' => $empresa->id]);

        $produto = Produto::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'custo_medio' => 10,
        ]);
        $setor = Setor::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);
        $nota = NfRecebida::withoutTenant()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'numero' => '9001',
            'emitente_nome' => 'Fornecedor seguro',
            'valor_produtos' => 19753.09,
            'valor_total' => 19753.09,
            'situacao' => 'importada',
            'movimentou_estoque' => false,
            'xml' => '<nfe>SEGREDO_XML_NF_ENTRADA</nfe>',
        ]);
        $item = $nota->itens()->create([
            'produto_id' => $produto->id,
            'codigo_fornecedor' => 'P-1',
            'descricao' => 'Produto de custo sigiloso',
            'quantidade' => 2,
            'valor_unitario' => 9876.54321,
            'valor_total' => 19753.09,
        ]);

        return [$user, $empresa, $produto, $setor, $nota, $item];
    }

    public function test_payloads_e_models_ocultam_xml_e_custo_sem_view(): void
    {
        [$user, , , , $nota, $item] = $this->cenario(['fiscal.view']);

        $lista = $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/fiscal/nf-entrada')->assertOk();
        $detalhe = $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/fiscal/nf-entrada/{$nota->id}")->assertOk();

        foreach ([$lista, $detalhe] as $resposta) {
            $json = json_encode($resposta->json(), JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('SEGREDO_XML_NF_ENTRADA', $json);
            $this->assertStringNotContainsString('valor_unitario', $json);
            $this->assertStringNotContainsString('9876.54321', $json);
        }

        $this->assertArrayNotHasKey('xml', $nota->toArray());
        $this->assertArrayNotHasKey('valor_unitario', $item->toArray());
    }

    public function test_view_de_custo_expoe_apenas_valor_unitario_e_nunca_xml(): void
    {
        [$user, , , , $nota] = $this->cenario([
            'fiscal.view', 'produto.campo.custo.view',
        ]);

        $resposta = $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/fiscal/nf-entrada/{$nota->id}")
            ->assertOk()
            ->assertJsonPath('data.itens.0.valor_unitario', 9876.54321);

        $this->assertStringNotContainsString(
            'SEGREDO_XML_NF_ENTRADA',
            json_encode($resposta->json(), JSON_THROW_ON_ERROR),
        );
    }

    public function test_processamento_sem_edit_de_custo_e_negado_sem_efeito(): void
    {
        [$user, $empresa, $produto, $setor, $nota] = $this->cenario(['fiscal.emitir']);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/fiscal/nf-entrada/{$nota->id}/processar", ['setor_id' => $setor->id])
            ->assertForbidden();

        $this->assertDatabaseMissing('estoquesaldos', [
            'empresa_id' => $empresa->id,
            'setor_id' => $setor->id,
            'produto_id' => $produto->id,
        ]);
        $this->assertSame(0, Financeiro::withoutTenant()->count());
        $this->assertFalse((bool) $nota->refresh()->movimentou_estoque);
    }

    public function test_edit_sem_view_processa_custo_mas_redige_resposta(): void
    {
        [$user, $empresa, $produto, $setor, $nota] = $this->cenario([
            'fiscal.emitir', 'produto.campo.custo.edit',
        ]);

        $resposta = $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/fiscal/nf-entrada/{$nota->id}/processar", ['setor_id' => $setor->id])
            ->assertOk()
            ->assertJsonPath('data.situacao', 'processada')
            ->assertJsonMissingPath('data.itens.0.valor_unitario');

        $this->assertStringNotContainsString(
            'SEGREDO_XML_NF_ENTRADA',
            json_encode($resposta->json(), JSON_THROW_ON_ERROR),
        );
        $this->assertEqualsWithDelta(
            9876.5432,
            (float) EstoqueSaldo::withoutTenant()
                ->where('empresa_id', $empresa->id)
                ->where('setor_id', $setor->id)
                ->where('produto_id', $produto->id)
                ->value('custo_medio'),
            0.00001,
        );
        $this->assertSame(1, Financeiro::withoutTenant()->count());
    }
}
