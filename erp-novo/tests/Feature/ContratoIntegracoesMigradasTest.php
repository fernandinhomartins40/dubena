<?php

namespace Tests\Feature;

use App\Domain\Integracao\IntegracaoTenant;
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

    /** @return array{0:\App\Models\User, 1:\App\Models\Empresa} */
    private function suporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'support' => true,
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

        DB::table('config_globais')->updateOrInsert(
            ['grupo_id' => $empresa->grupo_id],
            ['google_maps_key' => 'AIza_key_da_rede', 'updated_at' => now()],
        );

        $this->assertSame(
            'AIza_key_da_rede',
            app(IntegracaoTenant::class)->googleMapsKey($empresa->grupo_id),
            'o Maps é lido de config_globais do grupo — gravar em empresa_configs some'
        );
    }
}
