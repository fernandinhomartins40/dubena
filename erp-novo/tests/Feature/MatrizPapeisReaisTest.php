<?php

namespace Tests\Feature;

use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Role;
use App\Models\User;
use Database\Factories\Support\FronteiraTenant;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F2-08 — matriz positiva e negativa com os papéis REAIS do produto.
 *
 * Os papéis que uma revenda recebe são os do `RbacSeeder`: Administrador,
 * Gerente, Operador e Entregador. Testes que montam papéis sob medida provam
 * que o mecanismo de permissão funciona — não que os papéis ENTREGUES estão
 * desenhados corretamente. São perguntas diferentes, e só a segunda diz o que o
 * cliente recebe.
 *
 * A parte negativa é a que importa. Uma matriz que só verifica o que cada papel
 * PODE fazer passa com um papel que pode tudo — e é exatamente assim que a
 * separação de funções apodrece sem ninguém notar: alguém acrescenta uma
 * permissão a um papel para resolver um chamado, e nada acusa.
 *
 * Nenhum teste aqui usa `support`.
 */
class MatrizPapeisReaisTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::factory()->create();
        $this->seed(RbacSeeder::class);
    }

    /** Usuário com UM dos papéis reais do produto, e mais nada. */
    private function com(string $papel): User
    {
        // `semPapel`: a factory dá administrador por padrão, o que mascararia
        // justamente a granularidade que esta matriz existe para medir.
        $user = User::factory()->semPapel()->create([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
        ]);

        $role = Role::query()
            ->where('grupo_id', $this->empresa->grupo_id)
            ->where('nome', $papel)
            ->firstOrFail();

        $user->roles()->attach($role->id, ['empresa_id' => $this->empresa->id]);
        FronteiraTenant::paraUsuario($user);

        return $user->fresh();
    }

    private function cliente(): Cliente
    {
        return Cliente::factory()->create([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
        ]);
    }

    /** O seeder tem de entregar os quatro papéis — a matriz depende deles. */
    public function test_os_quatro_papeis_reais_existem(): void
    {
        $nomes = Role::query()->where('grupo_id', $this->empresa->grupo_id)->pluck('nome');

        foreach (['Administrador', 'Gerente', 'Operador', 'Entregador'] as $papel) {
            $this->assertContains($papel, $nomes->all());
        }
    }

    // ─────────────────────────── Administrador ───────────────────────────

    public function test_administrador_administra_acesso(): void
    {
        $this->actingAs($this->com('Administrador'), 'sanctum')
            ->getJson('/api/admin/usuarios')
            ->assertOk();
    }

    public function test_administrador_exclui(): void
    {
        $cliente = $this->cliente();

        $this->actingAs($this->com('Administrador'), 'sanctum')
            ->deleteJson("/api/admin/clientes/{$cliente->id}")
            ->assertOk();
    }

    // ───────────────────────────── Gerente ───────────────────────────────

    /** Gerente opera o negócio... */
    public function test_gerente_opera_o_negocio(): void
    {
        $this->actingAs($this->com('Gerente'), 'sanctum')
            ->getJson('/api/admin/clientes')
            ->assertOk();
    }

    /** ...mas não administra acesso: isso é privilégio do Administrador. */
    public function test_gerente_nao_administra_acesso(): void
    {
        $gerente = $this->com('Gerente');

        $this->actingAs($gerente, 'sanctum')->getJson('/api/admin/usuarios')->assertForbidden();
        $this->actingAs($gerente, 'sanctum')->getJson('/api/admin/papeis')->assertForbidden();
    }

    /** Nem exclui: `.delete` fica fora do papel por desenho. */
    public function test_gerente_nao_exclui(): void
    {
        $cliente = $this->cliente();

        $this->actingAs($this->com('Gerente'), 'sanctum')
            ->deleteJson("/api/admin/clientes/{$cliente->id}")
            ->assertForbidden();
    }

    // ───────────────────────────── Operador ──────────────────────────────

    public function test_operador_atende_o_balcao(): void
    {
        $operador = $this->com('Operador');

        $this->actingAs($operador, 'sanctum')->getJson('/api/admin/clientes')->assertOk();
        $this->actingAs($operador, 'sanctum')
            ->postJson('/api/admin/clientes', [
                'nome' => 'Cliente do balcão',
                'telefones' => [['telefone' => '42999998888']],
            ])
            ->assertCreated();
    }

    /**
     * Estornar e aprovar ficam FORA do Operador de propósito (default-deny): são
     * os verbos que desfazem dinheiro, e o administrador os concede caso a caso.
     */
    public function test_operador_nao_alcanca_os_verbos_que_desfazem_dinheiro(): void
    {
        $operador = $this->com('Operador');

        $this->assertFalse($operador->temPermissao('financeiro.estornar', $this->empresa->id));
        $this->assertFalse($operador->temPermissao('pedido.aprovar', $this->empresa->id));
        $this->assertTrue(
            $operador->temPermissao('financeiro.baixar', $this->empresa->id),
            'baixar é cotidiano do operador e precisa continuar valendo',
        );
    }

    public function test_operador_nao_administra_nem_exclui(): void
    {
        $operador = $this->com('Operador');
        $cliente = $this->cliente();

        $this->actingAs($operador, 'sanctum')->getJson('/api/admin/usuarios')->assertForbidden();
        $this->actingAs($operador, 'sanctum')
            ->deleteJson("/api/admin/clientes/{$cliente->id}")->assertForbidden();
    }

    // ──────────────────────────── Entregador ─────────────────────────────

    /**
     * O Entregador espelha o app: pedido e monitoramento, mais nada.
     *
     * É o papel mais exposto do produto — roda num celular que anda pela rua e
     * pode ser perdido ou roubado. Cada permissão a mais aqui é um vazamento com
     * pernas.
     */
    public function test_entregador_ve_apenas_o_que_o_app_precisa(): void
    {
        $entregador = $this->com('Entregador');

        $this->assertTrue($entregador->temPermissao('pedido.view', $this->empresa->id));
        $this->assertTrue($entregador->temPermissao('monitora.view', $this->empresa->id));

        foreach (['cliente.view', 'financeiro.view', 'produto.view', 'usuario.view'] as $chave) {
            $this->assertFalse(
                $entregador->temPermissao($chave, $this->empresa->id),
                "o entregador não pode alcançar {$chave} — o app dele não precisa",
            );
        }
    }

    public function test_entregador_nao_abre_a_carteira_de_clientes(): void
    {
        $this->actingAs($this->com('Entregador'), 'sanctum')
            ->getJson('/api/admin/clientes')
            ->assertForbidden();
    }

    // ─────────────────────── A hierarquia se sustenta ────────────────────

    /**
     * A propriedade que dá sentido à matriz: cada papel é subconjunto do
     * anterior. Se um dia um papel menor ganhar algo que o maior não tem, a
     * escada deixou de ser escada — e isso não apareceria em nenhum teste
     * positivo.
     */
    public function test_a_escada_de_privilegio_nao_se_inverte(): void
    {
        $chaves = fn (string $papel) => Role::query()
            ->where('grupo_id', $this->empresa->grupo_id)->where('nome', $papel)
            ->firstOrFail()->permissions()->pluck('chave')->all();

        $admin = $chaves('Administrador');
        $gerente = $chaves('Gerente');
        $operador = $chaves('Operador');
        $entregador = $chaves('Entregador');

        $this->assertSame([], array_diff($gerente, $admin), 'Gerente tem algo que o Administrador não tem');
        $this->assertSame([], array_diff($operador, $gerente), 'Operador tem algo que o Gerente não tem');
        $this->assertSame([], array_diff($entregador, $operador), 'Entregador tem algo que o Operador não tem');

        // E cada degrau é ESTRITAMENTE menor: papéis idênticos não são hierarquia.
        $this->assertGreaterThan(count($gerente), count($admin));
        $this->assertGreaterThan(count($operador), count($gerente));
        $this->assertGreaterThan(count($entregador), count($operador));
    }

    /** Nenhum papel real, sozinho, dá acesso de plataforma. */
    public function test_nenhum_papel_real_alcanca_o_superadmin(): void
    {
        foreach (['Administrador', 'Gerente', 'Operador', 'Entregador'] as $papel) {
            $this->actingAs($this->com($papel), 'sanctum')
                ->getJson('/api/superadmin/empresas')
                ->assertStatus(401);
        }
    }
}
