<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpedCustoAutorizacaoTest extends TestCase
{
    use RefreshDatabase;

    /** @param list<string> $chaves */
    private function usuario(array $chaves): User
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'support' => false,
        ]);
        $role = Role::create(['grupo_id' => $empresa->grupo_id, 'nome' => 'Fiscal SPED']);
        $ids = collect($chaves)->map(
            fn (string $chave) => Permission::firstOrCreate(['chave' => $chave])->id,
        );
        $role->permissions()->sync($ids);
        $user->roles()->attach($role->id, ['empresa_id' => $empresa->id]);

        return $user;
    }

    public function test_fiscal_view_sem_custo_view_nao_baixa_sped_de_inventario(): void
    {
        $user = $this->usuario(['fiscal.view']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/fiscal/sped?inicio=2026-01-01&fim=2026-01-31')
            ->assertForbidden();
    }

    public function test_custo_view_libera_arquivo_sped_inteiro(): void
    {
        $user = $this->usuario(['fiscal.view', 'produto.campo.custo.view']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/fiscal/sped?inicio=2026-01-01&fim=2026-01-31')
            ->assertOk()
            ->assertJsonPath('data.periodo.inicio', '2026-01-01');
    }
}
