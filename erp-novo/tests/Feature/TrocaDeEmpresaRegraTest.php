<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Grupo;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Factories\Support\FronteiraTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regra de PLATAFORMA da troca de empresa — vale para qualquer rede.
 *
 * Estes testes usam uma rede genérica (não a Dubena) de propósito: o
 * comportamento é do produto, não de um cliente. Num SaaS, "o dono consegue
 * voltar para a matriz" não pode depender de alguém ter rodado um seeder ou um
 * SQL naquele tenant.
 *
 * A regra: ao ativar outra empresa, a de ORIGEM vira vínculo permanente. Sem
 * isso o usuário perde o acesso à empresa de onde saiu — porque
 * `podeAcessarEmpresa` aceita a empresa padrão OU uma da pivot, e a ação de
 * ativar muda justamente a padrão.
 */
class TrocaDeEmpresaRegraTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $matriz;

    private Empresa $filial;

    private User $usuario;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $rede = Grupo::factory()->create(['descricao' => 'Rede Qualquer']);
        $this->matriz = Empresa::factory()->create([
            'grupo_id' => $rede->id, 'razao_social' => 'Matriz', 'matriz' => true,
        ]);
        $this->filial = Empresa::factory()->create([
            'grupo_id' => $rede->id, 'razao_social' => 'Filial',
        ]);

        // Usuário comum (sem bypass), com acesso às duas e permissão de empresa.
        $this->usuario = User::factory()->create([
            'empresa_id' => $this->matriz->id,
            'grupo_id' => $rede->id,
        ]);
        $this->usuario->empresas()->attach($this->filial->id);
        FronteiraTenant::sincronizarVinculosLegados($this->usuario->refresh());

        $papel = Role::create(['grupo_id' => $rede->id, 'nome' => 'Admin']);
        $papel->permissions()->sync([
            Permission::firstOrCreate(['chave' => 'empresa.view'])->id,
        ]);
        $this->usuario->roles()->attach($papel->id, ['empresa_id' => null]);

        $this->token = $this->usuario->createToken('teste')->plainTextToken;
    }

    public function test_ao_trocar_de_empresa_a_origem_vira_vinculo_permanente(): void
    {
        // A matriz é só a empresa PADRÃO — ainda não está na pivot.
        $this->assertNotContains(
            $this->matriz->id,
            $this->usuario->empresas()->pluck('empresas.id')->all()
        );

        $this->withToken($this->token)
            ->postJson("/api/admin/empresas/{$this->filial->id}/ativar")
            ->assertOk();

        // Depois de sair dela, a matriz passou a ser vínculo — é o que garante
        // a volta.
        $this->assertContains(
            $this->matriz->id,
            $this->usuario->fresh()->empresas()->pluck('empresas.id')->all()
        );
    }

    public function test_usuario_consegue_ir_e_voltar_quantas_vezes_quiser(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->withToken($this->token)
                ->postJson("/api/admin/empresas/{$this->filial->id}/ativar")
                ->assertOk();

            $this->withToken($this->token)
                ->postJson("/api/admin/empresas/{$this->matriz->id}/ativar")
                ->assertOk();
        }

        $this->assertSame(
            $this->matriz->id,
            (int) $this->usuario->fresh()->empresa_id
        );
    }

    public function test_trocar_nao_concede_acesso_a_empresa_de_outra_rede(): void
    {
        $outraRede = Grupo::factory()->create(['descricao' => 'Outra Rede']);
        $concorrente = Empresa::factory()->create(['grupo_id' => $outraRede->id]);

        // A empresa nem pertence ao grupo do usuário: 404 do escopo por grupo.
        $this->withToken($this->token)
            ->postJson("/api/admin/empresas/{$concorrente->id}/ativar")
            ->assertNotFound();

        $this->assertFalse(
            $this->usuario->fresh()->podeAcessarEmpresa($concorrente->id)
        );
    }

    public function test_sem_vinculo_nao_ativa(): void
    {
        $terceira = Empresa::factory()->create(['grupo_id' => $this->matriz->grupo_id]);

        // Mesma rede, mas sem vínculo nem ser a padrão: negado.
        $this->withToken($this->token)
            ->postJson("/api/admin/empresas/{$terceira->id}/ativar")
            ->assertForbidden();
    }
}
