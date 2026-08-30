<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\EmpresaConfig;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Factories\Support\FronteiraTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmpresaAlvoAutorizacaoTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, Empresa, Empresa} */
    private function cenario(bool $vincularIrma = false): array
    {
        $ativa = Empresa::factory()->create();
        $irma = Empresa::factory()->create(['grupo_id' => $ativa->grupo_id]);
        $user = User::factory()->create([
            'empresa_id' => $ativa->id,
            'grupo_id' => $ativa->grupo_id,
            'support' => false,
        ]);

        $this->conceder($user, $ativa, ['empresa.view', 'empresa.edit', 'empresa.delete']);

        if ($vincularIrma) {
            $user->empresas()->attach($irma->id);
            FronteiraTenant::sincronizarVinculosLegados($user->refresh());
            $this->conceder($user, $irma, ['empresa.view', 'empresa.edit', 'empresa.delete']);
        }

        return [$user, $ativa, $irma];
    }

    /** @param list<string> $chaves */
    private function conceder(User $user, Empresa $empresa, array $chaves): void
    {
        $permissions = collect($chaves)->map(
            fn (string $chave) => Permission::firstOrCreate(['chave' => $chave]),
        );
        $role = Role::create([
            'grupo_id' => $empresa->grupo_id,
            'nome' => 'Empresa alvo '.$empresa->id,
        ]);
        $role->permissions()->sync($permissions->pluck('id'));
        $user->roles()->attach($role->id, ['empresa_id' => $empresa->id]);
    }

    public function test_empresa_irma_sem_vinculo_e_inexistente_para_todas_as_portas(): void
    {
        [$user, , $irma] = $this->cenario();
        EmpresaConfig::query()->create([
            'empresa_id' => $irma->id,
            'tempoentrega' => 77,
            'dados' => ['marcador' => 'INALTERADO'],
        ]);

        $portas = [
            ['GET', "/api/admin/empresas/{$irma->id}", []],
            ['PUT', "/api/admin/empresas/{$irma->id}", ['razao_social' => 'INVASAO']],
            ['DELETE', "/api/admin/empresas/{$irma->id}", []],
            ['GET', "/api/admin/empresas/{$irma->id}/config", []],
            ['PUT', "/api/admin/empresas/{$irma->id}/config", ['tempoentrega' => 1]],
            ['PUT', "/api/admin/empresas/{$irma->id}/config/senha-mestra", ['senha_nova' => 'invasao']],
            ['POST', "/api/admin/empresas/{$irma->id}/config/testar-email", ['to' => 'x@example.com']],
            ['GET', "/api/admin/empresas/{$irma->id}/certificado", []],
            ['POST', "/api/admin/empresas/{$irma->id}/certificado", []],
            ['PUT', "/api/admin/empresas/{$irma->id}/nfce-token", ['nfce_csc_id' => '1', 'nfce_csc_token' => 'invasao']],
            ['GET', "/api/admin/empresas/{$irma->id}/integracoes", []],
            ['PUT', "/api/admin/empresas/{$irma->id}/integracoes", ['pix' => ['client_secret' => 'invasao']]],
        ];

        foreach ($portas as [$metodo, $url, $dados]) {
            $this->actingAs($user, 'sanctum')->json($metodo, $url, $dados)->assertNotFound();
        }

        $this->assertDatabaseHas('empresas', ['id' => $irma->id]);
        $this->assertDatabaseHas('empresa_configs', [
            'empresa_id' => $irma->id,
            'tempoentrega' => 77,
        ]);
        $this->assertSame(
            'INALTERADO',
            EmpresaConfig::query()->where('empresa_id', $irma->id)->value('dados')['marcador'],
        );
    }

    public function test_mutacao_exige_empresa_alvo_ativa_e_permissao_dela(): void
    {
        [$user, , $irma] = $this->cenario(vincularIrma: true);

        // Acesso e papel existem, mas a empresa ativa ainda é a origem.
        $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/empresas/{$irma->id}/config", ['tempoentrega' => 41])
            ->assertNotFound();

        $this->assertDatabaseMissing('empresa_configs', ['empresa_id' => $irma->id]);

        // A troca explícita de contexto faz o Gate usar o papel da empresa alvo.
        $this->actingAs($user, 'sanctum')
            ->withHeader('X-Empresa-Id', (string) $irma->id)
            ->putJson("/api/admin/empresas/{$irma->id}/config", ['tempoentrega' => 41])
            ->assertOk()
            ->assertJsonPath('data.tempoentrega', 41);
    }

    public function test_vinculo_e_header_nao_reutilizam_permissao_da_empresa_origem(): void
    {
        [$user, , $irma] = $this->cenario();
        $user->empresas()->attach($irma->id);
        FronteiraTenant::sincronizarVinculosLegados($user->refresh());

        $this->actingAs($user, 'sanctum')
            ->withHeader('X-Empresa-Id', (string) $irma->id)
            ->putJson("/api/admin/empresas/{$irma->id}/config", ['tempoentrega' => 55])
            ->assertForbidden();

        $this->assertDatabaseMissing('empresa_configs', ['empresa_id' => $irma->id]);
    }
}
