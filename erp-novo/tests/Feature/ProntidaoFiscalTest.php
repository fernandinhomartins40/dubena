<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\EmpresaConfig;
use App\Models\Fiscal\ConfigFiscal;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Pronto para emitir?": a lista inteira do que falta, antes da primeira nota
 * — e não um erro de cada vez na hora de transmitir.
 */
class ProntidaoFiscalTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, bool> chave => ok */
    private function itens(User $user, Empresa $empresa): array
    {
        $r = $this->actingAs($user, 'sanctum')->getJson("/api/admin/empresas/{$empresa->id}/fiscal/prontidao")->assertOk();

        return collect($r->json('data.itens'))->mapWithKeys(fn ($i) => [$i['chave'] => $i['ok']])->all();
    }

    public function test_aponta_o_que_falta_e_nao_declara_pronto(): void
    {
        $empresa = Empresa::factory()->create(['cnpj' => '04190715000105', 'inscricao_estadual' => '1234567', 'cep' => '85010100', 'endereco' => 'Rua XV', 'numero' => '10', 'bairro' => 'Centro']);
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        ConfigFiscal::withoutTenant()->create(['empresa_id' => $empresa->id, 'ambiente' => 2, 'regime_tributario' => 3]);
        EmpresaConfig::query()->create(['empresa_id' => $empresa->id, 'dados' => ['fiscal' => ['nfce_emite' => true]]]);
        Produto::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'ativo' => true, 'ncm' => null, 'natureza' => 'produto']);

        $itens = $this->itens($user, $empresa);

        $this->assertFalse($itens['certificado'], 'sem certificado não está pronto');
        $this->assertFalse($itens['csc'], 'emite NFC-e e não tem CSC');
        $this->assertFalse($itens['produtos_ncm'], 'produto sem NCM');
        $this->assertTrue($itens['config_fiscal']);
        $this->assertTrue($itens['cnpj']);
        $this->assertTrue($itens['cep']);
        $this->assertFalse($this->actingAs($user, 'sanctum')->getJson("/api/admin/empresas/{$empresa->id}/fiscal/prontidao")->json('data.pronto'));
    }

    /** Sem emitir NFC-e, CSC não é exigido. */
    public function test_csc_so_e_cobrado_de_quem_emite_nfce(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        EmpresaConfig::query()->create(['empresa_id' => $empresa->id, 'dados' => ['fiscal' => ['nfce_emite' => false]]]);

        $this->assertArrayNotHasKey('csc', $this->itens($user, $empresa));
    }

    public function test_outra_rede_nao_ve(): void
    {
        $outra = Empresa::factory()->create();
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        $this->actingAs($user, 'sanctum')->getJson("/api/admin/empresas/{$outra->id}/fiscal/prontidao")->assertNotFound();
    }
}
