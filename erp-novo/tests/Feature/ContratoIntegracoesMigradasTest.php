<?php

namespace Tests\Feature;

use App\Domain\Integracao\IntegracaoTenant;
use App\Models\ConfigGlobal;
use App\Models\Empresa;
use App\Models\EmpresaConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * O que este teste protege: credencial migrada tem que APARECER na tela.
 *
 * O defeito real: o EmpresaConfigMigrator cifrava `chave` e `client_id` além do
 * `client_secret`. O GET `/empresas/{id}/integracoes` devolve esses dois campos
 * SEM decifrar (são públicos por contrato), então a página de Configurações
 * mostrava o blob `eyJpdiI6...` no lugar da credencial. A migração "deu certo"
 * — 7 lidos, 7 gravados — e a tela ficou inútil.
 *
 * Por isso o teste não olha o banco: ele escreve pelo mesmo caminho do migrador
 * e LÊ PELO ENDPOINT, que é o que o usuário enxerga.
 */
class ContratoIntegracoesMigradasTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User, 1:Empresa} */
    private function suporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);

        return [$user, $empresa];
    }

    public function test_credencial_gravada_pelo_migrador_aparece_legivel_na_tela(): void
    {
        [$user, $empresa] = $this->suporte();

        // Mesmo caminho de escrita do EmpresaConfigMigrator.
        $bloco = IntegracaoTenant::cifrarBloco([
            'chave' => 'dubena@pix.com.br',
            'client_id' => 'Client_Id_abc123',
            'client_secret' => 'Client_Secret_super_sigiloso',
            'ambiente' => 'homologacao',
        ], ['client_secret', 'webhook_hmac_secret']);

        $config = EmpresaConfig::query()->firstOrCreate(['empresa_id' => $empresa->id]);
        $config->dados = ['integracoes' => ['pix' => $bloco]];
        $config->save();

        $r = $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/empresas/{$empresa->id}/integracoes")
            ->assertOk()
            ->json('data.pix');

        // Públicos: voltam LEGÍVEIS, não como blob do Crypt.
        $this->assertSame('dubena@pix.com.br', $r['chave'],
            'a chave PIX precisa voltar em claro — é o que o pagador lê');
        $this->assertSame('Client_Id_abc123', $r['client_id']);

        // Segredo: nunca volta, mas a tela sabe que existe.
        $this->assertTrue($r['client_secret_configurado']);
        $this->assertArrayNotHasKey('client_secret', $r);
    }

    public function test_segredo_fica_cifrado_no_banco(): void
    {
        $bloco = IntegracaoTenant::cifrarBloco([
            'chave' => 'dubena@pix.com.br',
            'client_secret' => 'Client_Secret_super_sigiloso',
        ], ['client_secret', 'webhook_hmac_secret']);

        $this->assertNotSame('Client_Secret_super_sigiloso', $bloco['client_secret'],
            'client_secret não pode ir para o banco em claro');
        $this->assertSame('dubena@pix.com.br', $bloco['chave'],
            'cifrar a chave PIX quebra a exibição na tela');
    }

    public function test_google_maps_e_credencial_de_rede_nao_de_empresa(): void
    {
        [, $empresa] = $this->suporte();

        // Pelo model, não por `DB::table`: `google_maps_key` é `encrypted` desde
        // 2026-08-29 (credencial cobrada não fica em claro), e a escrita crua
        // gravaria texto puro que o cast não consegue decifrar na leitura.
        ConfigGlobal::withoutGrupo()->updateOrCreate(
            ['grupo_id' => $empresa->grupo_id],
            ['google_maps_key' => 'AIza_key_da_rede'],
        );

        $this->assertSame(
            'AIza_key_da_rede',
            app(IntegracaoTenant::class)->googleMapsKey($empresa->grupo_id),
            'o Maps é lido de config_globais do grupo — gravar em empresa_configs some'
        );
    }

    /**
     * A chave do Maps é credencial COBRADA. Duas revendas concorrentes não podem
     * ver a chave uma da outra — nem gastar a cota alheia.
     *
     * Antes de 2026-08-29 ela era a única de `config_globais` que não era
     * `encrypted` nem `hidden`, e saía em claro no banco e em qualquer
     * serialização do model.
     */
    public function test_chave_do_maps_nao_fica_em_claro_nem_aparece_serializada(): void
    {
        [, $empresa] = $this->suporte();

        $config = ConfigGlobal::withoutGrupo()->updateOrCreate(
            ['grupo_id' => $empresa->grupo_id],
            ['google_maps_key' => 'AIza_segredo_da_revenda'],
        );

        $cru = DB::table('config_globais')->where('grupo_id', $empresa->grupo_id)->value('google_maps_key');
        $this->assertNotSame('AIza_segredo_da_revenda', $cru, 'a chave não pode ficar em claro no banco');
        $this->assertSame('AIza_segredo_da_revenda', $config->fresh()->google_maps_key, 'o cast precisa decifrar de volta');
        $this->assertArrayNotHasKey('google_maps_key', $config->toArray(), 'a chave não pode sair em serialização');
    }
}
