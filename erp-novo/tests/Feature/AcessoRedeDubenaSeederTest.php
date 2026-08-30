<?php

namespace Tests\Feature;

use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Grupo;
use App\Models\User;
use Database\Factories\Support\FronteiraTenant;
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

        // Os usuários vêm do seeder, não da factory, então a fronteira SaaS não
        // é criada automaticamente. Sem ela o enforcement nega o acesso e o
        // teste passaria a medir o resolver em vez do RBAC de rede que é o
        // assunto aqui.
        foreach (User::withoutGlobalScopes()->get() as $usuario) {
            FronteiraTenant::sincronizarVinculosLegados($usuario);
        }
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

    public function test_dono_ve_a_rede_inteira_e_filtra_por_filial(): void
    {
        $dono = User::where('email', 'dono@dubena.com.br')->firstOrFail();
        $token = $dono->createToken('teste')->plainTextToken;

        // A REDE: 2 da matriz + 1 da filial.
        $this->withToken($token)
            ->getJson('/api/admin/clientes')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        // O combo da tela restringe a uma unidade.
        $this->withToken($token)
            ->getJson("/api/admin/clientes?empresa_id={$this->filial->id}")
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

    public function test_dono_troca_de_filial_e_consegue_voltar_para_a_matriz(): void
    {
        // Regressão real: a empresa padrão do dono não entrava na pivot
        // `empresa_user` (parecia redundante, já que `podeAcessarEmpresa`
        // aceita a padrão). Ao ativar outra filial, `users.empresa_id` muda, a
        // matriz deixa de ser a padrão e não está na pivot — o dono ficava
        // PRESO na filial, com 403 ao tentar voltar.
        $dono = User::where('email', 'dono@dubena.com.br')->firstOrFail();
        $token = $dono->createToken('teste')->plainTextToken;

        // Vai para a filial.
        $this->withToken($token)
            ->postJson("/api/admin/empresas/{$this->filial->id}/ativar")
            ->assertOk();

        // E consegue VOLTAR para a matriz.
        $this->withToken($token)
            ->postJson("/api/admin/empresas/{$this->matriz->id}/ativar")
            ->assertOk();

        $this->assertSame(
            $this->matriz->id,
            (int) $dono->fresh()->empresa_id,
            'o dono precisa conseguir voltar para a matriz'
        );
    }

    public function test_dono_esta_vinculado_a_todas_as_empresas_da_rede(): void
    {
        $dono = User::where('email', 'dono@dubena.com.br')->firstOrFail();
        $vinculadas = $dono->empresas()->pluck('empresas.id')->all();

        // Inclusive a padrão — é o que sustenta a ida e volta entre filiais.
        $this->assertContains($this->matriz->id, $vinculadas);
        $this->assertContains($this->filial->id, $vinculadas);
    }

    public function test_seeder_e_idempotente(): void
    {
        $antes = User::count();
        $this->seed(AcessoRedeDubenaSeeder::class);

        $this->assertSame($antes, User::count(), 'rodar de novo não deve duplicar usuários');
    }
}
