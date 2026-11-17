<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PIE R3-F-05: quem não tem a permissão recebe 403 ANTES da validação. Antes,
 * corpo vazio devolvia 422 com as regras do formulário a quem nem podia agir.
 */
class AutorizaAntesDeValidarTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
    }

    /** @param list<string> $chaves */
    private function usuarioCom(array $chaves): User
    {
        $user = User::factory()->semPapel()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        $role = Role::create(['grupo_id' => $this->empresa->grupo_id, 'nome' => 'Papel '.uniqid()]);
        $role->permissions()->sync(collect($chaves)->map(fn (string $c) => Permission::firstOrCreate(['chave' => $c])->id));
        $user->roles()->attach($role->id, ['empresa_id' => $this->empresa->id]);

        return $user;
    }

    public function test_sem_permissao_corpo_vazio_da_403_e_nao_422(): void
    {
        $this->actingAs($this->usuarioCom(['cliente.view']), 'sanctum');

        foreach ([
            ['POST', '/api/admin/clientes'],
            ['PUT', '/api/admin/clientes/1'],
            ['POST', '/api/admin/pedidos'],
            ['PUT', '/api/admin/pedidos/1'],
            ['POST', '/api/admin/produtos'],
            ['PUT', '/api/admin/produtos/1'],
            ['POST', '/api/admin/caixa/1/baixar'],
        ] as [$metodo, $uri]) {
            $status = $this->json($metodo, $uri, [])->status();
            $this->assertSame(403, $status, "$metodo $uri");
        }
    }

    public function test_com_permissao_a_validacao_continua_respondendo(): void
    {
        // Guarda: o authorize() novo não pode barrar quem tem a permissão.
        $this->actingAs($this->usuarioCom(['cliente.create', 'pedido.create', 'produto.create']), 'sanctum');

        foreach (['/api/admin/clientes', '/api/admin/pedidos', '/api/admin/produtos'] as $uri) {
            $this->postJson($uri, [])->assertStatus(422);
        }
    }
}
