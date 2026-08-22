<?php

namespace Tests\Feature;

use App\Domain\Cliente\GeocodificarClienteJob;
use App\Models\Apoio\Segmento;
use App\Models\Apoio\TipoPessoa;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Geografico\Cidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * N2 — Cliente: CRUD escopado por empresa, telefones aninhados, anti-duplicidade
 * de endereço, convênio, geocoding assíncrono, RBAC.
 */
class ClienteTest extends TestCase
{
    use RefreshDatabase;

    private function suporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'support' => true,
        ]);

        return [$user, $empresa];
    }

    /**
     * O formulario de edicao le `tipopessoa_label`/`segmento_label` para exibir
     * o valor ja escolhido: o AsyncSelect so busca a lista quando o usuario abre
     * o popover, entao sem o rotulo o campo preenchido aparece como
     * "Selecione..." — foi assim que clientes migrados pareceram sem vinculo.
     */
    public function test_show_devolve_os_rotulos_das_fks(): void
    {
        [$user, $empresa] = $this->suporte();

        $tipo = TipoPessoa::create(['grupo_id' => $empresa->grupo_id, 'descricao' => 'Fisica', 'ativo' => true]);
        $segmento = Segmento::create(['grupo_id' => $empresa->grupo_id, 'descricao' => 'Residencial', 'ativo' => true]);
        $cidade = Cidade::factory()->create(['grupo_id' => $empresa->grupo_id]);

        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'tipopessoa_id' => $tipo->id,
            'segmento_id' => $segmento->id,
            'cidade_id' => $cidade->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/clientes/{$cliente->id}")
            ->assertOk()
            ->assertJsonPath('data.tipopessoa_id', $tipo->id)
            ->assertJsonPath('data.tipopessoa_label', 'Fisica')
            ->assertJsonPath('data.segmento_label', 'Residencial')
            ->assertJsonPath('data.cidade_label', $cidade->descricao);
    }

    public function test_lista_apenas_clientes_da_empresa_ativa(): void
    {
        [$user, $empresa] = $this->suporte();
        Cliente::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'nome' => 'Maria']);
        Cliente::factory()->create(['nome' => 'Outro Tenant']); // outra empresa

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/clientes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nome', 'Maria');
    }

    public function test_cria_cliente_com_telefones_aninhados(): void
    {
        Queue::fake();
        [$user] = $this->suporte();

        $resp = $this->actingAs($user, 'sanctum')->postJson('/api/admin/clientes', [
            'nome' => 'João da Silva',
            'cpf' => '12345678900',
            'telefones' => [
                ['telefone' => '11999990000', 'whatsapp' => true],
                ['telefone' => '1133334444', 'whatsapp' => false],
            ],
        ])->assertCreated();

        $clienteId = $resp->json('data.id');
        $this->assertDatabaseCount('clientetelefones', 2);
        $this->assertDatabaseHas('clientetelefones', ['cliente_id' => $clienteId, 'telefone' => '11999990000', 'whatsapp' => true]);
        Queue::assertPushed(GeocodificarClienteJob::class);
    }

    public function test_bloqueia_endereco_duplicado_na_empresa(): void
    {
        Queue::fake();
        [$user, $empresa] = $this->suporte();
        $cidade = Cidade::factory()->create(['grupo_id' => $empresa->grupo_id]);

        Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'cidade_id' => $cidade->id, 'numero' => '100', 'endereco' => 'Rua A',
        ]);

        $this->actingAs($user, 'sanctum')->postJson('/api/admin/clientes', [
            'nome' => 'Duplicado',
            'cidade_id' => $cidade->id, 'numero' => '100', 'endereco' => 'Rua A',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('endereco');
    }

    public function test_atualiza_cliente_e_substitui_telefones(): void
    {
        Queue::fake();
        [$user, $empresa] = $this->suporte();
        $cliente = Cliente::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $cliente->telefones()->create(['telefone' => 'antigo']);

        $this->actingAs($user, 'sanctum')->putJson("/api/admin/clientes/{$cliente->id}", [
            'nome' => 'Nome Novo',
            'telefones' => [['telefone' => '11888887777', 'whatsapp' => true]],
        ])->assertOk()->assertJsonPath('data.nome', 'Nome Novo');

        $this->assertDatabaseCount('clientetelefones', 1);
        $this->assertDatabaseHas('clientetelefones', ['cliente_id' => $cliente->id, 'telefone' => '11888887777']);
    }

    public function test_telefone_subrecurso_add_e_del(): void
    {
        [$user, $empresa] = $this->suporte();
        $cliente = Cliente::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        $tel = $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/clientes/{$cliente->id}/telefones", ['telefone' => '11999998888', 'whatsapp' => true])
            ->assertCreated()->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/clientes/{$cliente->id}/telefones")
            ->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/admin/clientes/{$cliente->id}/telefones/{$tel}")
            ->assertOk();
        $this->assertDatabaseCount('clientetelefones', 0);
    }

    public function test_convenio_e_dependentes(): void
    {
        [$user, $empresa] = $this->suporte();
        $cliente = Cliente::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/clientes/{$cliente->id}/convenio", ['convenio' => true, 'convenio_ativo' => true, 'convenio_limite' => 500])
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/clientes/{$cliente->id}/convenio/dependentes", ['nome' => 'Filho'])
            ->assertCreated();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/clientes/{$cliente->id}/convenio")
            ->assertOk()
            // Envelope uniforme {data} (API-1).
            ->assertJsonPath('data.convenio', true)
            ->assertJsonPath('data.convenio_limite', 500)
            ->assertJsonCount(1, 'data.dependentes');
    }

    /**
     * "Excluir" DESATIVA e PRESERVA tudo. O comportamento anterior (delete
     * fisico + cascade nas sub-relacoes) destruia dados: com pedido o Postgres
     * recusava e o operador renomeava o cadastro para "FULANO - EXCLUIDO";
     * sem pedido, telefones e enderecos sumiam junto.
     */
    public function test_excluir_cliente_desativa_e_preserva_subrelacoes(): void
    {
        [$user, $empresa] = $this->suporte();
        $cliente = Cliente::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'ativo' => true]);
        $cliente->telefones()->create(['telefone' => 'x']);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/admin/clientes/{$cliente->id}", ['motivo' => 'Mudou de cidade'])
            ->assertOk()
            ->assertJsonPath('data.ativo', false);

        $this->assertDatabaseHas('clientes', ['id' => $cliente->id, 'ativo' => false, 'motivo_desativacao' => 'Mudou de cidade']);
        $this->assertDatabaseCount('clientetelefones', 1); // nada de cascade
        $this->assertSame($user->id, $cliente->fresh()->desativado_por);
    }

    /** Desativado sai do default da lista, mas aparece em `situacao=inativos`. */
    public function test_lista_mostra_apenas_ativos_por_padrao(): void
    {
        [$user, $empresa] = $this->suporte();
        $base = ['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id];
        $ativo = Cliente::factory()->create($base + ['nome' => 'Ana Ativa', 'ativo' => true]);
        $inativo = Cliente::factory()->create($base + ['nome' => 'Bruno Inativo', 'ativo' => false]);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/clientes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ativo->id);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/clientes?situacao=inativos')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $inativo->id);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/clientes?situacao=todos')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_reativar_devolve_cliente_a_lista(): void
    {
        [$user, $empresa] = $this->suporte();
        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'ativo' => false, 'motivo_desativacao' => 'engano',
        ]);

        $this->actingAs($user, 'sanctum')->postJson("/api/admin/clientes/{$cliente->id}/reativar")
            ->assertOk()
            ->assertJsonPath('data.ativo', true);

        // A trilha e limpa: reativado nao pode parecer desativado.
        $this->assertDatabaseHas('clientes', ['id' => $cliente->id, 'ativo' => true, 'desativado_em' => null, 'motivo_desativacao' => null]);
    }

    /** A trava que impede a desativacao virar sumico de divida. */
    public function test_nao_desativa_cliente_com_pedido_em_aberto(): void
    {
        [$user, $empresa] = $this->suporte();
        $cliente = Cliente::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'ativo' => true]);

        $situacao = \App\Models\Pedido\PedidoSituacao::query()->create([
            'grupo_id' => $empresa->grupo_id, 'descricao' => 'Em aberto', 'efeito' => 'PENDENTE',
        ]);
        \App\Models\Pedido\Pedido::query()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $situacao->id,
        ]);

        $this->actingAs($user, 'sanctum')->deleteJson("/api/admin/clientes/{$cliente->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('clientes', ['id' => $cliente->id, 'ativo' => true]);
    }

    public function test_sem_permissao_recebe_403(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => false]);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/clientes')->assertStatus(403);
    }
}
