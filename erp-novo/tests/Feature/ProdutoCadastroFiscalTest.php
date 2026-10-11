<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Fiscal\MalhaFiscal;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O cadastro de produto editava campos que o servidor não aceitava nem
 * devolvia: grupo fiscal, EAN tributável, IPI, dados ANP do GLP, CEST,
 * vasilhame retornável. A tela abria em branco e o salvamento descartava —
 * e as origens eram recriadas sem índice de importação nem percentual.
 */
class ProdutoCadastroFiscalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $empresa = Empresa::factory()->create();
        $this->user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
    }

    private function malha(string $tipo, string $descricao): MalhaFiscal
    {
        return MalhaFiscal::query()->create(['grupo_id' => $this->user->grupo_id, 'tipo' => $tipo, 'codigo' => $descricao, 'descricao' => $descricao, 'ativo' => true]);
    }

    public function test_campos_fiscais_e_origens_vao_e_voltam_intactos(): void
    {
        $glp = $this->malha('grupos-fiscais', 'NCM 27111910');

        $id = $this->actingAs($this->user, 'sanctum')->postJson('/api/admin/produtos', [
            'descricao' => 'GLP P13', 'vasilhame_retornavel' => true, 'dias_giro' => 15, 'envia_app_nf' => true,
            'nfe_permite' => true, 'nfe_cest' => '0601000', 'ncm' => '27111910', 'ean_trib' => '7891234567890',
            'grupo_fiscal_id' => $glp->id, 'nfe_descricao_fiscal' => 'GAS LIQUEFEITO DE PETROLEO',
            'nfe_cprod_anp' => '210203001', 'nfe_desc_anp' => 'GLP', 'pglp' => 100, 'pgnn' => 0, 'pgni' => 0,
            'nfe_aliq_ipi' => 0, 'nfe_cod_enquadramento_ipi' => 999,
            'origens' => [['uf' => 'PR', 'ind_import' => 0, 'cuf_orig' => 41, 'p_orig' => 100]],
        ])->assertCreated()->json('data.id');

        $p = Produto::query()->with('origens')->findOrFail($id);
        $this->assertTrue((bool) $p->vasilhame_retornavel);
        $this->assertSame(15, (int) $p->dias_giro);
        $this->assertSame('0601000', $p->nfe_cest);
        $this->assertSame($glp->id, (int) $p->grupo_fiscal_id);
        $this->assertSame('GLP', $p->nfe_desc_anp);
        $this->assertSame('7891234567890', $p->ean_trib);
        $this->assertSame(41, (int) $p->origens->first()->cuf_orig);
        $this->assertEqualsWithDelta(100, (float) $p->origens->first()->p_orig, 0.001);

        // E a tela recebe de volta o que precisa para abrir preenchida.
        $this->actingAs($this->user, 'sanctum')->getJson("/api/admin/produtos/{$id}")->assertOk()
            ->assertJsonPath('data.grupo_fiscal_id', $glp->id)
            ->assertJsonPath('data.grupofiscal_label', 'NCM 27111910')
            ->assertJsonPath('data.nfe_desc_anp', 'GLP')
            ->assertJsonPath('data.ean_trib', '7891234567890')
            ->assertJsonPath('data.origens.0.p_orig', 100);
    }

    /** CST e grupo fiscal moram na mesma tabela: só grupo vale como grupo. */
    public function test_grupo_fiscal_tem_que_ser_grupo_fiscal(): void
    {
        $cst = $this->malha('cst-icms', '060');

        $this->actingAs($this->user, 'sanctum')->postJson('/api/admin/produtos', [
            'descricao' => 'Qualquer', 'grupo_fiscal_id' => $cst->id,
        ])->assertStatus(422)->assertJsonValidationErrors('grupo_fiscal_id');
    }

    /**
     * O seletor oferecia uma lista fixa com ids 1..4 — que não são grupos
     * fiscais de ninguém. Agora lista os grupos reais da revenda.
     */
    public function test_seletor_de_grupo_fiscal_lista_os_grupos_reais(): void
    {
        $glp = $this->malha('grupos-fiscais', 'NCM 27111910');
        $this->malha('cst-icms', '060');

        $r = $this->actingAs($this->user, 'sanctum')->getJson('/api/admin/lookups/nf-grupos-fiscais')->assertOk();

        $this->assertSame([['id' => $glp->id, 'label' => 'NCM 27111910']], $r->json('data'));
    }
}
