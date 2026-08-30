<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A tela de conferência do par casco ↔ gás.
 *
 * A vigilância inteira se apoia neste vínculo. O que estes testes protegem é
 * a fronteira de empresa: apontar o vasilhame para o produto de outro tenant
 * faria o consumo ser medido contra um id que nunca aparece nos pedidos daqui,
 * dando giro zero e alerta falso contra uma carteira que compra normalmente.
 */
class ComodatoVinculoApiTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $user;

    private Produto $vasilhame;

    private Produto $gas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::factory()->create();
        $this->user = User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
        ]);

        $this->vasilhame = Produto::create([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
            'descricao' => 'Vasilha P13 Kg',
            'ativo' => true,
        ]);
        $this->gas = Produto::create([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
            'descricao' => 'Glp P13',
            'tipo_glp' => 3,
            'ativo' => true,
        ]);
    }

    public function test_lista_traz_vasilhame_com_sugestao_e_candidatos(): void
    {
        $r = $this->actingAs($this->user, 'sanctum')->getJson('/api/admin/comodatos/vinculos');

        $r->assertOk();

        $linha = collect($r->json('data'))->firstWhere('id', $this->vasilhame->id);

        $this->assertNotNull($linha, 'O vasilhame deveria aparecer na conferência.');
        $this->assertSame('P13', $linha['capacidade']);
        $this->assertNull($linha['produto_retornavel_id']);
        $this->assertContains($this->gas->id, $linha['sugeridos']);

        // O gás é candidato do seletor; o casco não pode ser.
        $conteudos = collect($r->json('conteudos'))->pluck('id');
        $this->assertContains($this->gas->id, $conteudos);
        $this->assertNotContains($this->vasilhame->id, $conteudos);
    }

    public function test_confirma_o_par(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/admin/comodatos/vinculos/{$this->vasilhame->id}", [
                'produto_retornavel_id' => $this->gas->id,
            ])
            ->assertOk();

        $this->assertSame($this->gas->id, (int) $this->vasilhame->refresh()->produto_retornavel_id);
    }

    /** O caso ambíguo precisa poder ficar sem par, e não com um par chutado. */
    public function test_desfaz_o_par(): void
    {
        $this->vasilhame->forceFill(['produto_retornavel_id' => $this->gas->id])->save();

        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/admin/comodatos/vinculos/{$this->vasilhame->id}", [
                'produto_retornavel_id' => null,
            ])
            ->assertOk();

        $this->assertNull($this->vasilhame->refresh()->produto_retornavel_id);
    }

    /**
     * `exists:produtos,id` sozinho deixaria passar o produto de outro tenant.
     * Este é o teste que impede a tela de reintroduzir à mão o vazamento que a
     * inferência já teve.
     */
    public function test_nao_aceita_conteudo_de_outra_empresa(): void
    {
        $outra = Empresa::factory()->create(['grupo_id' => $this->empresa->grupo_id]);
        $gasAlheio = Produto::create([
            'empresa_id' => $outra->id,
            'grupo_id' => $outra->grupo_id,
            'descricao' => 'Glp P13',
            'tipo_glp' => 3,
            'ativo' => true,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/admin/comodatos/vinculos/{$this->vasilhame->id}", [
                'produto_retornavel_id' => $gasAlheio->id,
            ])
            ->assertStatus(422);

        $this->assertNull($this->vasilhame->refresh()->produto_retornavel_id);
    }

    /** O vasilhame de outra empresa não é sequer endereçável por esta rota. */
    public function test_nao_edita_vasilhame_de_outra_empresa(): void
    {
        $outra = Empresa::factory()->create(['grupo_id' => $this->empresa->grupo_id]);
        $vasilhameAlheio = Produto::create([
            'empresa_id' => $outra->id,
            'grupo_id' => $outra->grupo_id,
            'descricao' => 'Vasilha P13 Kg',
            'ativo' => true,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/admin/comodatos/vinculos/{$vasilhameAlheio->id}", [
                'produto_retornavel_id' => $this->gas->id,
            ])
            ->assertStatus(404);
    }

    public function test_salva_a_regua_da_vigilancia(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/admin/comodatos/config', [
                'dias_janela' => 180,
                'giro_minimo' => 4,
                'giro_critico' => 1,
                'queda_atencao' => 40,
                'queda_critica' => 70,
                'dias_sem_compra_alerta' => 90,
                'posse_minima_vigiada' => 4,
                'dias_aviso_vencimento' => 30,
                'ativo' => true,
            ])
            ->assertOk();

        $this->assertDatabaseHas('comodato_config', [
            'empresa_id' => $this->empresa->id,
            'dias_janela' => 180,
        ]);
    }
}
