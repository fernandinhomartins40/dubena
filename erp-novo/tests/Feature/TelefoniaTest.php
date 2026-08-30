<?php

namespace Tests\Feature;

use App\Domain\Telefonia\TelefoniaService;
use App\Models\Cliente\Cliente;
use App\Models\Cliente\ClienteTelefone;
use App\Models\Empresa;
use App\Models\Telefonia\ChamadaEntrante;
use App\Models\Telefonia\Ligacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T4.4 — bina no atendimento.
 *
 * ⚠️ Condicionada à decisão do dono (o call-center usa bina hoje?). Os testes
 * existem para que "sim" não custe nada e para registrar a regra de forma
 * verificável.
 *
 * **O que eles protegem, acima de tudo: o casamento telefone → cliente.** O
 * cadastro guarda com máscara (`(42) 99960-2233`) e o PABX manda cru
 * (`4299602233`). O legado formatava na GRAVAÇÃO, o que tornava a busca refém
 * do formato. Aqui guarda cru e compara só dígitos, pelos últimos 8 — que
 * atravessa máscara, DDD ausente e o 9º dígito de celular.
 */
class TelefoniaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Empresa} */
    private function cenario(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);

        return [$user, $empresa];
    }

    private function cliente(Empresa $e, string $nome, string $telefone): Cliente
    {
        $c = Cliente::create([
            'empresa_id' => $e->id, 'grupo_id' => $e->grupo_id,
            'nome' => $nome, 'cliente' => true,
            'endereco' => 'Rua Teste', 'numero' => '10',
        ]);

        ClienteTelefone::create(['cliente_id' => $c->id, 'telefone' => $telefone]);

        return $c;
    }

    // ── O casamento de telefone (o coração da bina) ──────────────────────────

    public function test_acha_o_cliente_mesmo_com_mascara_no_cadastro(): void
    {
        [, $empresa] = $this->cenario();
        $this->cliente($empresa, 'Maria', '(42) 99960-2233');

        // Como o PABX manda: só dígitos.
        $r = app(TelefoniaService::class)->clientesPorTelefone($empresa->id, '42999602233');

        $this->assertCount(1, $r);
        $this->assertSame('Maria', $r[0]['nome']);
    }

    public function test_acha_sem_ddd(): void
    {
        [, $empresa] = $this->cenario();
        $this->cliente($empresa, 'João', '(42) 3627-7559');

        // Chamada local: o PABX às vezes entrega sem o DDD.
        $r = app(TelefoniaService::class)->clientesPorTelefone($empresa->id, '36277559');

        $this->assertCount(1, $r);
        $this->assertSame('João', $r[0]['nome']);
    }

    public function test_acha_apesar_do_nono_digito(): void
    {
        [, $empresa] = $this->cenario();
        // Cadastro antigo, gravado antes do 9º dígito existir.
        $this->cliente($empresa, 'Antiga', '(42) 9960-2233');

        // O PABX moderno entrega com o 9.
        $r = app(TelefoniaService::class)->clientesPorTelefone($empresa->id, '42999602233');

        $this->assertCount(1, $r);
        $this->assertSame('Antiga', $r[0]['nome']);
    }

    public function test_numero_curto_nao_casa_com_meia_base(): void
    {
        [, $empresa] = $this->cenario();
        $this->cliente($empresa, 'Alguém', '(42) 99960-2233');

        // Um sufixo de 4 dígitos casaria com dezenas de clientes e abriria a
        // ficha errada — pior que não abrir nenhuma.
        $this->assertSame([], app(TelefoniaService::class)->clientesPorTelefone($empresa->id, '2233'));
    }

    public function test_nao_vaza_cliente_de_outra_empresa(): void
    {
        [, $empresa] = $this->cenario();
        $outra = Empresa::factory()->create();
        $this->cliente($outra, 'De outra revenda', '(42) 99960-2233');

        $this->assertSame([], app(TelefoniaService::class)->clientesPorTelefone($empresa->id, '42999602233'));
    }

    // ── Fila ─────────────────────────────────────────────────────────────────

    public function test_chamada_com_um_cliente_ja_vincula(): void
    {
        [, $empresa] = $this->cenario();
        $maria = $this->cliente($empresa, 'Maria', '(42) 99960-2233');

        $r = app(TelefoniaService::class)->receber($empresa->id, $empresa->grupo_id, '42999602233');

        $this->assertSame($maria->id, $r['chamada']->cliente_id);
    }

    public function test_telefone_de_dois_clientes_nao_vincula_nenhum(): void
    {
        [, $empresa] = $this->cenario();
        $this->cliente($empresa, 'Comércio A', '(42) 3621-9900');
        $this->cliente($empresa, 'Comércio B', '(42) 3621-9900');

        $r = app(TelefoniaService::class)->receber($empresa->id, $empresa->grupo_id, '4236219900');

        // Escolher o primeiro abriria a ficha errada, e o atendente trataria a
        // pessoa pelo nome de outra. A tela mostra os dois.
        $this->assertNull($r['chamada']->cliente_id);
        $this->assertCount(2, $r['clientes']);
    }

    public function test_telefone_e_guardado_cru(): void
    {
        [, $empresa] = $this->cenario();

        $r = app(TelefoniaService::class)->receber($empresa->id, $empresa->grupo_id, '04236219900');

        // O legado formatava na gravação, o que fazia `04236219900` virar
        // `(0423) 6219-900` e nunca mais casar com o cadastro.
        $this->assertSame('04236219900', $r['chamada']->telefone);
    }

    public function test_fila_ignora_chamada_velha(): void
    {
        [, $empresa] = $this->cenario();
        app(TelefoniaService::class)->receber($empresa->id, $empresa->grupo_id, '4299602233');

        ChamadaEntrante::query()->update(['recebida_em' => now()->subHours(2)]);

        // Ninguém atende o que já desligou faz duas horas.
        $this->assertSame([], app(TelefoniaService::class)->fila($empresa->id));
    }

    // ── Atender / rejeitar ───────────────────────────────────────────────────

    public function test_atender_tira_da_fila_e_registra(): void
    {
        [$user, $empresa] = $this->cenario();
        $maria = $this->cliente($empresa, 'Maria', '(42) 99960-2233');
        $r = app(TelefoniaService::class)->receber($empresa->id, $empresa->grupo_id, '42999602233');

        $ligacao = app(TelefoniaService::class)->atender($r['chamada']->id, $user->id);

        $this->assertTrue($ligacao->atendida);
        $this->assertSame($maria->id, $ligacao->cliente_id);
        $this->assertSame(0, ChamadaEntrante::count());
        $this->assertSame(1, Ligacao::count());
    }

    public function test_rejeitar_guarda_o_motivo(): void
    {
        [$user, $empresa] = $this->cenario();
        $r = app(TelefoniaService::class)->receber($empresa->id, $empresa->grupo_id, '4299999999');

        $ligacao = app(TelefoniaService::class)->rejeitar($r['chamada']->id, $user->id, 'Trote');

        $this->assertTrue($ligacao->rejeitada);
        $this->assertFalse($ligacao->atendida);
        $this->assertSame('Trote', $ligacao->motivo);
        $this->assertSame(0, ChamadaEntrante::count());
    }

    // ── Webhook do PABX ──────────────────────────────────────────────────────

    public function test_webhook_sem_segredo_configurado_recusa(): void
    {
        [, $empresa] = $this->cenario();
        config(['services.pabx.webhook_secret' => '']);

        // Fail-closed: um endpoint público que grava no banco sem segredo é
        // convite a inundar a fila do atendimento.
        $this->postJson('/api/pabx/chamada', ['empresa_id' => $empresa->id, 'telefone' => '4299602233'])
            ->assertStatus(503);
    }

    public function test_webhook_com_token_errado_recusa(): void
    {
        [, $empresa] = $this->cenario();
        config(['services.pabx.webhook_secret' => 'segredo-real']);
        config(['services.pabx.empresa_id' => $empresa->id]);

        $this->postJson('/api/pabx/chamada',
            ['empresa_id' => $empresa->id, 'telefone' => '4299602233'],
            ['X-Pabx-Token' => 'chute'],
        )->assertStatus(401);
    }

    public function test_webhook_com_token_certo_enfileira(): void
    {
        [, $empresa] = $this->cenario();
        $maria = $this->cliente($empresa, 'Maria', '(42) 99960-2233');
        config(['services.pabx.webhook_secret' => 'segredo-real']);
        config(['services.pabx.empresa_id' => $empresa->id]);

        $this->postJson('/api/pabx/chamada',
            ['empresa_id' => $empresa->id, 'telefone' => '42999602233', 'ramal' => '201'],
            ['X-Pabx-Token' => 'segredo-real'],
        )
            ->assertStatus(201)
            ->assertJsonPath('data.clientes.0.nome', 'Maria');

        $this->assertSame(1, ChamadaEntrante::count());
    }

    public function test_webhook_nao_usa_segredo_global_em_outra_empresa(): void
    {
        [, $empresa] = $this->cenario();
        $outra = Empresa::factory()->create();
        config([
            'services.pabx.webhook_secret' => 'segredo-real',
            'services.pabx.empresa_id' => $empresa->id,
        ]);

        $this->postJson('/api/pabx/chamada',
            ['empresa_id' => $outra->id, 'telefone' => '42999602233'],
            ['X-Pabx-Token' => 'segredo-real'],
        )->assertStatus(401);

        $this->assertSame(0, ChamadaEntrante::count());
    }

    // ── API do operador ──────────────────────────────────────────────────────

    public function test_endpoints_do_operador(): void
    {
        [$user, $empresa] = $this->cenario();
        $this->cliente($empresa, 'Maria', '(42) 99960-2233');
        $r = app(TelefoniaService::class)->receber($empresa->id, $empresa->grupo_id, '42999602233');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/telefonia/fila')
            ->assertOk()
            ->assertJsonPath('data.0.cliente', 'Maria')
            ->assertJsonPath('data.0.telefone_formatado', '(42) 99960-2233');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/telefonia/buscar?telefone=42999602233')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/telefonia/chamadas/{$r['chamada']->id}/atender")
            ->assertOk();
    }

    public function test_exige_permissao(): void
    {
        [, $empresa] = $this->cenario();
        $semAcesso = User::factory()->semPapel()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        $this->actingAs($semAcesso, 'sanctum')
            ->getJson('/api/admin/telefonia/fila')
            ->assertStatus(403);
    }
}
