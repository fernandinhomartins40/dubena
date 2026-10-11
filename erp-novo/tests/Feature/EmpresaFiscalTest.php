<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\EmpresaConfig;
use App\Models\Fiscal\ConfigFiscal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A tela da empresa tinha ambiente, regime, séries, CSC, contador e cadastro
 * fiscal — e mandava tudo para um endpoint que descartava. A emissão lê
 * `config_fiscais`, que nenhuma tela gravava. `/empresas/{id}/fiscal` liga as
 * duas pontas.
 */
class EmpresaFiscalTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, Empresa} */
    private function cenario(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        return [$user, $empresa];
    }

    public function test_grava_onde_a_emissao_le_e_devolve_para_a_tela(): void
    {
        [$user, $empresa] = $this->cenario();

        $this->actingAs($user, 'sanctum')->putJson("/api/admin/empresas/{$empresa->id}/fiscal", [
            'ambiente' => 2, 'regime_tributario' => 3, 'serie_nfe' => 1, 'serie_nfce' => 2,
            'csc_id' => '000001', 'csc_token' => 'SEGREDO-CSC',
            'nfce_emite' => true, 'sped_emite' => true,
            'contador' => ['nome' => 'Contabil Ltda', 'crc' => 'PR-123'],
            'cadastro' => ['cnae' => '4784900', 'registro_anp' => '123456', 'distribuidora' => true],
        ])->assertOk();

        $fiscal = ConfigFiscal::withoutTenant()->where('empresa_id', $empresa->id)->firstOrFail();
        $this->assertSame([2, 3, 1, 2, '000001', 'SEGREDO-CSC'], [
            (int) $fiscal->ambiente, (int) $fiscal->regime_tributario, (int) $fiscal->serie_nfe,
            (int) $fiscal->serie_nfce, $fiscal->csc_id, $fiscal->csc_token,
        ]);
        $this->assertNotSame('SEGREDO-CSC', DB::table('config_fiscais')->where('empresa_id', $empresa->id)->value('csc_token'), 'CSC gravado cifrado');

        $r = $this->actingAs($user, 'sanctum')->getJson("/api/admin/empresas/{$empresa->id}/fiscal")->assertOk();
        $r->assertJsonPath('data.tem_csc', true)
            ->assertJsonPath('data.nfce_emite', true)
            ->assertJsonPath('data.contador.nome', 'Contabil Ltda')
            ->assertJsonPath('data.cadastro.cnae', '4784900');
        $this->assertArrayNotHasKey('csc_token', $r->json('data'), 'o segredo nunca volta');
    }

    /** CSC em branco = manter: a tela não recebe o segredo de volta. */
    public function test_csc_em_branco_nao_apaga_o_que_existe(): void
    {
        [$user, $empresa] = $this->cenario();
        $this->actingAs($user, 'sanctum')->putJson("/api/admin/empresas/{$empresa->id}/fiscal", ['csc_token' => 'ORIGINAL'])->assertOk();
        $this->actingAs($user, 'sanctum')->putJson("/api/admin/empresas/{$empresa->id}/fiscal", ['csc_token' => '', 'serie_nfce' => 3])->assertOk();

        $this->assertSame('ORIGINAL', ConfigFiscal::withoutTenant()->where('empresa_id', $empresa->id)->value('csc_token'));
    }

    /** Mexer no contador não apaga o resto da config (integrações, SPED…). */
    public function test_preserva_os_outros_blocos_da_config(): void
    {
        [$user, $empresa] = $this->cenario();
        EmpresaConfig::query()->create(['empresa_id' => $empresa->id, 'dados' => ['integracoes' => ['pix' => ['chave' => 'x']], 'sped' => ['perfil' => 'A']]]);

        $this->actingAs($user, 'sanctum')->putJson("/api/admin/empresas/{$empresa->id}/fiscal", ['contador' => ['nome' => 'Novo']])->assertOk();

        $dados = EmpresaConfig::query()->where('empresa_id', $empresa->id)->value('dados');
        $this->assertSame('x', $dados['integracoes']['pix']['chave']);
        $this->assertSame('A', $dados['sped']['perfil']);
        $this->assertSame('Novo', $dados['contador']['nome']);
    }

    public function test_sem_permissao_nao_edita(): void
    {
        $empresa = Empresa::factory()->create();
        $semPapel = User::factory()->semPapel()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        $this->actingAs($semPapel, 'sanctum')->putJson("/api/admin/empresas/{$empresa->id}/fiscal", ['ambiente' => 1])->assertForbidden();
    }
}
