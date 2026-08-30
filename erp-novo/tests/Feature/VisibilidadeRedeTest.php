<?php

namespace Tests\Feature;

use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Grupo;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Factories\Support\FronteiraTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Visibilidade numa REDE (matriz + filiais) — o comportamento do ctrl-web.
 *
 * O legado tem dois conceitos distintos, e confundi-los foi o defeito que estes
 * testes travam:
 *
 *  - `empresa_padrao`: a empresa de CONTEXTO (config, caixa, numeração fiscal);
 *  - `empresas_permitidas`: o CONJUNTO que as listagens mostram
 *    (`whereIn('pedido.empresa_id', ...)` em PedidoRepository).
 *
 * Tratar a troca de empresa como interruptor exclusivo fazia a operação inteira
 * sumir da tela ao selecionar uma filial vazia — com dados reais, 400 mil
 * pedidos desapareciam ao clicar numa unidade com 12 clientes.
 *
 * O que NÃO muda: a fronteira entre redes continua absoluta.
 */
class VisibilidadeRedeTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $matriz;

    private Empresa $filial;

    private Empresa $concorrente;

    private string $token;

    private User $dono;

    protected function setUp(): void
    {
        parent::setUp();

        $rede = Grupo::factory()->create(['descricao' => 'Rede A']);
        $this->matriz = Empresa::factory()->create([
            'grupo_id' => $rede->id, 'razao_social' => 'Matriz', 'matriz' => true,
        ]);
        $this->filial = Empresa::factory()->create([
            'grupo_id' => $rede->id, 'razao_social' => 'Filial',
        ]);

        $outraRede = Grupo::factory()->create(['descricao' => 'Rede B']);
        $this->concorrente = Empresa::factory()->create([
            'grupo_id' => $outraRede->id, 'razao_social' => 'Concorrente',
        ]);

        // Volume desigual de propósito: espelha o caso real (a matriz concentra).
        $this->cliente('Cliente Matriz 1', $this->matriz);
        $this->cliente('Cliente Matriz 2', $this->matriz);
        $this->cliente('Cliente Matriz 3', $this->matriz);
        $this->cliente('Cliente da Filial', $this->filial);
        $this->cliente('Cliente do Concorrente', $this->concorrente);

        $this->dono = User::factory()->create([
            'empresa_id' => $this->matriz->id,
            'grupo_id' => $rede->id,
        ]);
        $this->dono->empresas()->attach($this->filial->id);
        FronteiraTenant::sincronizarVinculosLegados($this->dono->refresh());

        $papel = Role::create(['grupo_id' => $rede->id, 'nome' => 'Admin']);
        $papel->permissions()->sync([
            Permission::firstOrCreate(['chave' => 'cliente.view'])->id,
            Permission::firstOrCreate(['chave' => 'empresa.view'])->id,
        ]);
        $this->dono->roles()->attach($papel->id, ['empresa_id' => null]);

        $this->token = $this->dono->createToken('teste')->plainTextToken;
    }

    private function cliente(string $nome, Empresa $empresa): void
    {
        Cliente::withoutTenant()->create([
            'nome' => $nome,
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'cliente' => true,
            'ativo' => true,
        ]);
    }

    public function test_listagem_mostra_a_rede_inteira_por_padrao(): void
    {
        // 3 da matriz + 1 da filial = a operação da rede, como no ctrl-web.
        $this->withToken($this->token)
            ->getJson('/api/admin/clientes')
            ->assertOk()
            ->assertJsonCount(4, 'data');
    }

    public function test_filtro_por_empresa_refina_a_visao(): void
    {
        // O combo da tela: `?empresa_id=` restringe a UMA.
        $this->withToken($this->token)
            ->getJson("/api/admin/clientes?empresa_id={$this->filial->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nome', 'Cliente da Filial');
    }

    public function test_trocar_a_empresa_ativa_nao_esconde_a_rede(): void
    {
        // Estar posicionado na filial muda o CONTEXTO (config, caixa), mas a
        // listagem continua mostrando a rede — era exatamente o que quebrava.
        $this->withToken($this->token)
            ->withHeader('X-Empresa-Id', (string) $this->filial->id)
            ->getJson('/api/admin/clientes')
            ->assertOk()
            ->assertJsonCount(4, 'data');
    }

    public function test_rede_diferente_permanece_invisivel(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/admin/clientes')
            ->assertOk()
            ->assertJsonMissing(['nome' => 'Cliente do Concorrente']);
    }

    public function test_filtrar_por_empresa_de_outra_rede_nao_concede_acesso(): void
    {
        // Filtro de tela REFINA, nunca amplia: pedir uma empresa fora do
        // conjunto é ignorado, e a visão continua a da rede.
        $this->withToken($this->token)
            ->getJson("/api/admin/clientes?empresa_id={$this->concorrente->id}")
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonMissing(['nome' => 'Cliente do Concorrente']);
    }

    public function test_usuario_de_uma_filial_so_ve_a_dela(): void
    {
        // Sem vínculo com a matriz: a visibilidade é só a própria empresa.
        $gerente = User::factory()->create([
            'empresa_id' => $this->filial->id,
            'grupo_id' => $this->filial->grupo_id,
        ]);
        $papel = Role::create(['grupo_id' => $this->filial->grupo_id, 'nome' => 'Gerente']);
        $papel->permissions()->sync([Permission::firstOrCreate(['chave' => 'cliente.view'])->id]);
        $gerente->roles()->attach($papel->id, ['empresa_id' => $this->filial->id]);

        $this->withToken($gerente->createToken('t')->plainTextToken)
            ->getJson('/api/admin/clientes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nome', 'Cliente da Filial');
    }

    public function test_empresas_visiveis_nunca_cruza_a_rede(): void
    {
        // Mesmo com um vínculo indevido a uma empresa de OUTRA rede, a lista
        // de visíveis é restrita ao grupo — a fronteira é dura.
        $this->dono->empresas()->attach($this->concorrente->id);
        FronteiraTenant::sincronizarVinculosLegados($this->dono->refresh());

        $visiveis = $this->dono->fresh()->empresasVisiveis((int) $this->matriz->grupo_id);

        $this->assertContains($this->matriz->id, $visiveis);
        $this->assertContains($this->filial->id, $visiveis);
        $this->assertNotContains($this->concorrente->id, $visiveis);
    }

    public function test_uma_empresa_so_comporta_se_como_antes(): void
    {
        $solo = User::factory()->create([
            'empresa_id' => $this->matriz->id,
            'grupo_id' => $this->matriz->grupo_id,
        ]);
        $papel = Role::create(['grupo_id' => $this->matriz->grupo_id, 'nome' => 'Solo']);
        $papel->permissions()->sync([Permission::firstOrCreate(['chave' => 'cliente.view'])->id]);
        $solo->roles()->attach($papel->id, ['empresa_id' => $this->matriz->id]);

        // Sem vínculos extras: vê só a matriz (3), como no comportamento antigo.
        $this->withToken($solo->createToken('t')->plainTextToken)
            ->getJson('/api/admin/clientes')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }
}
