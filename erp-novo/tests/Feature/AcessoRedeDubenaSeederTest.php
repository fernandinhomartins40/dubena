<?php

namespace Tests\Feature;

use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Grupo;
use App\Models\User;
use Database\Seeders\AcessoRedeDubenaSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O seeder de acessos da rede monta o cenário que o cliente vai operar:
 * um DONO que enxerga todas as filiais e um GERENTE preso à sua.
 *
 * Vale testar porque o vínculo tem duas pernas independentes — acesso à empresa
 * (`empresa_user`) e permissão na empresa (`role_user`) — e esquecer uma delas
 * produz um usuário que "entra mas não vê" ou "vê mas não deveria".
 */
class AcessoRedeDubenaSeederTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $matriz;

    private Empresa $filial;

    protected function setUp(): void
    {
        parent::setUp();

        $rede = Grupo::factory()->create(['descricao' => 'Rede Dubena']);
        $this->matriz = Empresa::factory()->create([
            'grupo_id' => $rede->id, 'razao_social' => 'Dubena Matriz', 'matriz' => true,
        ]);
        $this->filial = Empresa::factory()->create([
            'grupo_id' => $rede->id, 'razao_social' => 'Dubena Filial',
        ]);

        // A matriz é descoberta pelo volume de clientes (como na migração real).
        Cliente::withoutTenant()->create([
            'nome' => 'Cliente da Matriz', 'empresa_id' => $this->matriz->id,
            'grupo_id' => $rede->id, 'cliente' => true, 'ativo' => true,
        ]);
        Cliente::withoutTenant()->create([
            'nome' => 'Outro da Matriz', 'empresa_id' => $this->matriz->id,
            'grupo_id' => $rede->id, 'cliente' => true, 'ativo' => true,
        ]);
        Cliente::withoutTenant()->create([
            'nome' => 'Cliente da Filial', 'empresa_id' => $this->filial->id,
            'grupo_id' => $rede->id, 'cliente' => true, 'ativo' => true,
        ]);

        $this->seed(RbacSeeder::class);
        $this->seed(AcessoRedeDubenaSeeder::class);
    }

    public function test_dono_da_rede_enxerga_todas_as_filiais(): void
    {
        $dono = User::where('email', 'dono@dubena.com.br')->firstOrFail();

        $this->assertFalse($dono->support, 'o dono NÃO deve ter bypass de RBAC');
        $this->assertTrue($dono->podeAcessarEmpresa($this->matriz->id));
        $this->assertTrue($dono->podeAcessarEmpresa($this->filial->id));

        // Papel global na rede: a permissão vale em qualquer filial.
        $this->assertTrue($dono->temPermissao('cliente.view', $this->matriz->id));
        $this->assertTrue($dono->temPermissao('cliente.view', $this->filial->id));
    }

    public function test_dono_alterna_de_filial_e_ve_os_dados_de_cada_uma(): void
    {
        $dono = User::where('email', 'dono@dubena.com.br')->firstOrFail();
        $token = $dono->createToken('teste')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/clientes')
            ->assertOk()
            ->assertJsonCount(2, 'data');   // os dois da matriz

        $this->withToken($token)
            ->withHeader('X-Empresa-Id', (string) $this->filial->id)
            ->getJson('/api/admin/clientes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nome', 'Cliente da Filial');
    }

    public function test_gerente_da_filial_nao_alcanca_a_matriz(): void
    {
        $gerente = User::where('email', 'gerente.filial@dubena.com.br')->firstOrFail();

        $this->assertFalse($gerente->support);
        $this->assertSame($this->filial->id, (int) $gerente->empresa_id);
        $this->assertFalse($gerente->podeAcessarEmpresa($this->matriz->id));

        // Mesmo forçando o header, continua vendo apenas a própria filial.
        $this->withToken($gerente->createToken('teste')->plainTextToken)
            ->withHeader('X-Empresa-Id', (string) $this->matriz->id)
            ->getJson('/api/admin/clientes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nome', 'Cliente da Filial');
    }

    public function test_seeder_e_idempotente(): void
    {
        $antes = User::count();
        $this->seed(AcessoRedeDubenaSeeder::class);

        $this->assertSame($antes, User::count(), 'rodar de novo não deve duplicar usuários');
    }
}
