<?php

namespace Tests\Feature;

use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Fiscal\NotaFiscal;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Paginação das listagens de volume.
 *
 * Vários endpoints nasceram com `->limit(200)->get()` cravado — inofensivo com
 * dados de demonstração, enganoso com dados reais: a tela de NF-e mostrava 200
 * de 241 mil notas e NÃO informava que havia mais. Quem olha conclui que o
 * módulo não carregou.
 *
 * O que estes testes fixam: a resposta traz `meta.total` (o número real), a
 * navegação entre páginas funciona, e `per_page` tem teto — para um
 * `?per_page=100000` não derrubar o servidor.
 */
class PaginacaoListagensTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::factory()->create();

        $cliente = Cliente::withoutTenant()->create([
            'nome' => 'Cliente',
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
            'cliente' => true,
            'ativo' => true,
        ]);

        // 120 notas: mais que uma página (50), menos que o teto (200).
        for ($i = 1; $i <= 120; $i++) {
            NotaFiscal::withoutTenant()->create([
                'empresa_id' => $this->empresa->id,
                'grupo_id' => $this->empresa->grupo_id,
                'cliente_id' => $cliente->id,
                'modelo' => '55',
                'tipo' => 'S',
                'serie' => 1,
                'numero' => 1000 + $i,
                'chave' => str_pad((string) $i, 44, '0', STR_PAD_LEFT),
                'valor_total' => 100 + $i,
                'situacao' => 'AUTORIZADA',
                'emitida_em' => now()->subDays($i),
            ]);
        }

        $user = User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
            'support' => false,
        ]);
        $papel = Role::create(['grupo_id' => $this->empresa->grupo_id, 'nome' => 'Fiscal']);
        $papel->permissions()->sync([Permission::firstOrCreate(['chave' => 'fiscal.view'])->id]);
        $user->roles()->attach($papel->id, ['empresa_id' => $this->empresa->id]);

        $this->token = $user->createToken('teste')->plainTextToken;
    }

    public function test_resposta_informa_o_total_real(): void
    {
        $r = $this->withToken($this->token)->getJson('/api/admin/fiscal/nfe')->assertOk();

        // O total é o que impede a tela de parecer truncada sem aviso.
        $this->assertSame(120, $r->json('meta.total'));
        $this->assertSame(1, $r->json('meta.current_page'));
        $this->assertGreaterThan(1, $r->json('meta.last_page'));
        $this->assertCount(50, $r->json('data'));
    }

    public function test_navega_para_a_proxima_pagina(): void
    {
        $p1 = $this->withToken($this->token)->getJson('/api/admin/fiscal/nfe?page=1')->assertOk();
        $p2 = $this->withToken($this->token)->getJson('/api/admin/fiscal/nfe?page=2')->assertOk();

        $this->assertSame(2, $p2->json('meta.current_page'));

        // Páginas diferentes trazem registros diferentes.
        $this->assertNotSame($p1->json('data.0.id'), $p2->json('data.0.id'));
    }

    public function test_per_page_tem_teto(): void
    {
        // Sem teto, um cliente pedindo 100 mil linhas derruba o servidor.
        $r = $this->withToken($this->token)
            ->getJson('/api/admin/fiscal/nfe?per_page=100000')
            ->assertOk();

        $this->assertLessThanOrEqual(200, $r->json('meta.per_page'));
    }

    public function test_filtro_de_periodo_restringe_o_total(): void
    {
        // As notas foram criadas uma por dia para trás; 10 dias = 10 notas.
        $r = $this->withToken($this->token)->getJson(
            '/api/admin/fiscal/nfe?inicio='.now()->subDays(10)->toDateString()
            .'&fim='.now()->toDateString()
        )->assertOk();

        $this->assertLessThan(120, $r->json('meta.total'));
        $this->assertGreaterThan(0, $r->json('meta.total'));
    }

    public function test_busca_por_numero_encontra_a_nota(): void
    {
        $r = $this->withToken($this->token)
            ->getJson('/api/admin/fiscal/nfe?q=1050')
            ->assertOk();

        $this->assertSame(1, $r->json('meta.total'));
        $this->assertSame(1050, (int) $r->json('data.0.numero'));
    }
}
