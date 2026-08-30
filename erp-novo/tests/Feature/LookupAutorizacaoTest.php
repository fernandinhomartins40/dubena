<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\Admin\LookupController;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\User;
use Database\Factories\Support\FronteiraTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F2-01 — o lookup entrega o mesmo dado da listagem, e precisa da mesma permissão.
 *
 * `LookupController` era o único controller do Admin sem `autorizar()` em método
 * nenhum. Media na prática: `/api/admin/clientes` devolvia 403 para quem não tem
 * `cliente.view`, e `/api/admin/lookups/clientes` devolvia os mesmos clientes
 * com 200. Valia igual para produtos, contas, colaboradores e usuários.
 */
class LookupAutorizacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_nao_entrega_o_que_a_listagem_nega(): void
    {
        $empresa = Empresa::factory()->create();
        Cliente::factory()->count(3)->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);

        $user = User::factory()->semPapel()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);

        // A listagem do módulo nega...
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/clientes')
            ->assertForbidden();

        // ...e o lookup do MESMO dado tem de negar igual.
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/lookups/clientes')
            ->assertForbidden();
    }

    public function test_lookup_libera_para_quem_tem_a_permissao_do_modulo(): void
    {
        $empresa = Empresa::factory()->create();
        Cliente::factory()->count(2)->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);

        // A factory concede papel com todas as permissões.
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/lookups/clientes')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /**
     * Cada lookup exige a permissão do módulo DONO do dado — não uma permissão
     * genérica. Quem só enxerga cliente não passa a enxergar conta bancária.
     */
    public function test_permissao_e_a_do_modulo_dono_do_dado(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->semPapel()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);
        FronteiraTenant::papelComPermissoes($user, ['cliente.view']);

        $this->actingAs($user->fresh(), 'sanctum')
            ->getJson('/api/admin/lookups/clientes')
            ->assertOk();

        // `contas` é financeiro: a permissão de cliente não alcança.
        $this->actingAs($user->fresh(), 'sanctum')
            ->getJson('/api/admin/lookups/contas')
            ->assertForbidden();
    }

    /**
     * Default-deny para slug CONHECIDO sem permissão declarada.
     *
     * Slug inexistente segue devolvendo lista vazia com 200 — contrato que a SPA
     * já consome. Mas um lookup que existe e serve dado, sem permissão no mapa,
     * é esquecimento: aí o default é negar, não entregar.
     */
    public function test_todo_lookup_que_serve_dado_tem_permissao_declarada(): void
    {
        $reflexao = new \ReflectionClass(LookupController::class);
        $permissao = $reflexao->getConstant('PERMISSAO');
        $servem = array_merge(
            array_keys($reflexao->getConstant('MAPA')),
            array_keys($reflexao->getConstant('ESTATICOS')),
        );

        $semPermissao = array_values(array_diff($servem, array_keys($permissao)));

        $this->assertSame(
            [],
            $semPermissao,
            'Lookup que serve dado sem permissão declarada: '.implode(', ', $semPermissao),
        );
    }

    public function test_slug_inexistente_mantem_o_contrato_de_lista_vazia(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/lookups/inventado-que-nao-existe')
            ->assertOk()
            ->assertJsonPath('data', []);
    }
}
