<?php

namespace Tests\Feature;

use App\Domain\Crm\SorteioService;
use App\Domain\Telefonia\TelefoniaService;
use App\Models\Crm\Sorteio;
use App\Models\Empresa;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Telefonia\ChamadaEntrante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PIE P2-E-14 (Rodada 3, Agente F): furos de regra pequenos, cada um medido
 * isolado — o ator tem TUDO o que a ação legítima exige, menos a regra testada.
 */
class RegrasModulosSemDonoTest extends TestCase
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

    public function test_quem_so_ve_pedido_nao_atende_nem_rejeita_chamada(): void
    {
        $chamada = app(TelefoniaService::class)
            ->receber($this->empresa->id, $this->empresa->grupo_id, '42999602233')['chamada'];
        // O papel Entregador do seed tem `pedido.view` e nada de escrita de pedido.
        $entregador = $this->usuarioCom(['pedido.view']);

        $this->actingAs($entregador, 'sanctum')
            ->postJson("/api/admin/telefonia/chamadas/{$chamada->id}/rejeitar")->assertForbidden();
        $this->actingAs($entregador, 'sanctum')
            ->postJson("/api/admin/telefonia/chamadas/{$chamada->id}/atender")->assertForbidden();
        // A chamada continua tocando na fila (atender/rejeitar a removeriam).
        $this->assertTrue(ChamadaEntrante::query()->whereKey($chamada->id)->exists());

        // Quem lança pedido atende.
        $this->actingAs($this->usuarioCom(['pedido.view', 'pedido.create']), 'sanctum')
            ->postJson("/api/admin/telefonia/chamadas/{$chamada->id}/atender")->assertOk();
    }

    public function test_sorteio_realizado_nao_troca_premio_nem_ganha_numero(): void
    {
        $gestor = $this->usuarioCom(['sorteio.view', 'sorteio.edit', 'sorteio.create']);
        $this->actingAs($gestor, 'sanctum');
        $sorteio = Sorteio::create(['grupo_id' => $this->empresa->grupo_id, 'descricao' => 'Natal', 'premio' => 'Botijão']);
        $sorteio->numeros()->create(['numero' => '001']);

        // Aberto: edita normalmente.
        $this->putJson("/api/admin/sorteios/{$sorteio->id}", ['descricao' => 'Natal 2026', 'premio' => 'Botijão'])->assertOk();

        app(SorteioService::class)->sortear($sorteio->id, $gestor->id);

        $this->putJson("/api/admin/sorteios/{$sorteio->id}", ['descricao' => 'Natal 2026', 'premio' => 'Uma bicicleta'])
            ->assertStatus(422)->assertJsonValidationErrors('sorteio');
        $this->postJson("/api/admin/sorteios/{$sorteio->id}/numeros", ['numero' => '002'])
            ->assertStatus(422);

        $sorteio->refresh();
        $this->assertSame('Botijão', $sorteio->premio);
        $this->assertSame(1, $sorteio->numeros()->count());
    }
}
