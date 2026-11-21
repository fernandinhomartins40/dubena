<?php

namespace Tests\Feature;

use App\Models\Saas\PlatformAdmin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * PIE P2-A-01 — teste de aceite da revenda criada pelo superadmin, sob RLS de
 * verdade (runtime `erp_app`, envelope SaaS ligado).
 *
 * `POST /superadmin/revendas` → o dono define a senha pelo convite → login →
 * `GET /api/admin/produtos` devolve o catálogo inicial → um pedido concretiza
 * com baixa de estoque. Duas revendas, e nenhuma enxerga a outra.
 *
 * O que só o Postgres prova: cada linha do catálogo nasceu com a chave SaaS
 * (sem ela a policy canônica esconde o cadastro do próprio dono, sem erro
 * nenhum — foi o sintoma da carga de 2026-10-11), e o login/escrita do dono
 * passam pela fronteira tenant-empresa.
 *
 * `DatabaseTransactions` só na conexão do runtime: o provisionamento grava pela
 * conexão de owner e commita (a plataforma não tem tenant), como em
 * `SuperAdminSobRlsTest`. Por isso o efeito do pedido é conferido pela própria
 * API, que roda na transação do teste — o owner, outra conexão, não a enxerga.
 */
class ProvisionamentoRevendaSobRlsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('RLS é específico do PostgreSQL; pulado em '.DB::connection()->getDriverName().'.');
        }

        config(['saas_transformation.enforcement.tenant_envelope' => true]);
    }

    /**
     * Troca de ator entre requests do MESMO processo. O login grava o usuário na
     * sessão (store em memória, que sobrevive entre requests do teste), e o
     * guard do Sanctum consulta o `web` antes do Bearer: sem limpar, o token de
     * A seria ignorado e a resposta viria como o último que logou — o que
     * fabricaria um "vazamento" que em produção não existe.
     */
    private function como(?string $token): static
    {
        $this->app['session.store']->flush();
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        $this->defaultHeaders = [];

        return $token !== null ? $this->withToken($token) : $this;
    }

    /** @return array{0: array<string, mixed>, 1: string} dados da revenda e token do dono */
    private function revenda(string $tokenPlataforma, int $planoId, string $marca): array
    {
        $email = "dono.{$marca}@teste.invalid";
        $d = $this->como($tokenPlataforma)->postJson('/api/superadmin/revendas', [
            'razao_social' => "Revenda {$marca}", 'dono_nome' => 'Dono', 'dono_email' => $email,
            'plano_id' => $planoId, 'trial_dias' => 14,
        ])->assertCreated()->json('data');

        $this->como(null)->postJson('/api/definir-senha', [
            'email' => $email, 'token' => $d['convite']['token'],
            'password' => 'Senha-do-Dono-2026', 'password_confirmation' => 'Senha-do-Dono-2026',
        ])->assertOk();

        $token = $this->como(null)->postJson('/api/login', ['email' => $email, 'password' => 'Senha-do-Dono-2026'])
            ->assertOk()->json('token');

        return [$d, $token];
    }

    private function ler(string $token, string $uri): TestResponse
    {
        return $this->como($token)->getJson($uri);
    }

    public function test_revenda_provisionada_vende_e_fica_isolada_da_outra(): void
    {
        $owner = DB::connection('pgsql_owner');
        $marca = substr(str_replace('-', '', fake()->uuid()), 0, 12);
        $agora = now();
        $plano = $owner->table('planos')->insertGetId([
            'slug' => "a1-{$marca}", 'nome' => 'Plano A1', 'preco_mensal' => 1, 'ativo' => true,
            'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $adminId = $owner->table('platform_admins')->insertGetId([
            'nome' => 'SA A1', 'email' => "sa.a1.{$marca}@teste.invalid", 'password' => Hash::make('x'),
            'ativo' => true, 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $tokenSa = PlatformAdmin::query()->findOrFail($adminId)->createToken('a1')->plainTextToken;

        [$a, $tokenA] = $this->revenda($tokenSa, $plano, "a{$marca}");
        [$b, $tokenB] = $this->revenda($tokenSa, $plano, "b{$marca}");

        // Pré-condição que dá sentido ao teste: sem envelope, o runtime não vê nada.
        $this->assertSame(0, DB::table('produtos')->where('empresa_id', $a['empresa_id'])->count());

        // ── O dono enxerga o catálogo inicial (com chave SaaS, senão viria vazio).
        $produtosA = collect($this->ler($tokenA, '/api/admin/produtos')->assertOk()->json('data'));
        $this->assertEqualsCanonicalizing(['Gás GLP P13', 'Gás GLP P20', 'Gás GLP P45', 'Água mineral 20 L'],
            $produtosA->pluck('descricao')->all());
        $produtosB = collect($this->ler($tokenB, '/api/admin/produtos')->assertOk()->json('data'));
        $this->assertCount(4, $produtosB);

        // ── Isolamento nos dois sentidos.
        $this->assertSame([], array_values(array_intersect($produtosA->pluck('id')->all(), $produtosB->pluck('id')->all())));
        $this->ler($tokenB, '/api/admin/produtos/'.$produtosA->first()['id'])->assertNotFound();
        $this->ler($tokenA, '/api/admin/produtos/'.$produtosB->first()['id'])->assertNotFound();

        // ── Um pedido concretiza, com entrada e baixa de estoque.
        $grupoA = (int) $a['grupo_id'];
        $id = fn (string $tabela, array $onde) => (int) $owner->table($tabela)->where($onde)->value('id');
        $p13 = (int) $produtosA->firstWhere('descricao', 'Gás GLP P13')['id'];
        $deposito = $id('setores', ['empresa_id' => $a['empresa_id'], 'descricao' => 'Depósito']);

        $this->como($tokenA)->postJson('/api/admin/estoque/entrada', [
            'setor_id' => $deposito, 'produto_id' => $p13, 'quantidade' => 10, 'custo_unitario' => 80,
        ])->assertCreated();
        // O tipo "Celular" vem do catálogo inicial: o cadastro usa o que a revenda nasceu tendo.
        $cliente = $this->como($tokenA)->postJson('/api/admin/clientes', [
            'nome' => 'Primeiro Cliente',
            'telefones' => [['telefone' => '42999990000', 'telefonetipo_id' => $id('telefonetipos', ['grupo_id' => $grupoA, 'descricao' => 'Celular'])]],
        ])->assertCreated()->json('data.id');

        $pedido = $this->como($tokenA)->postJson('/api/admin/pedidos', [
            'cliente_id' => $cliente,
            'pedidosituacao_id' => $id('pedidosituacoes', ['grupo_id' => $grupoA, 'descricao' => 'Em Aberto']),
            'pedidooperacao_id' => $id('pedidooperacoes', ['grupo_id' => $grupoA, 'descricao' => 'Venda']),
            'condicaopagamento_id' => $id('condicaopagamentos', ['grupo_id' => $grupoA, 'descricao' => 'À vista']),
            'setor_id' => $deposito,
            'itens' => [['produto_id' => $p13, 'quantidade' => 1, 'preco_unitario' => 120]],
        ])->assertCreated()->json('data.id');

        $this->como($tokenA)->putJson("/api/admin/pedidos/{$pedido}/situacao", [
            'pedidosituacao_id' => $id('pedidosituacoes', ['grupo_id' => $grupoA, 'descricao' => 'Concluído']),
        ])->assertOk();

        $this->assertTrue($this->ler($tokenA, "/api/admin/pedidos/{$pedido}")->assertOk()->json('data.estoque_movimentado'));
        $saldo = collect($this->ler($tokenA, '/api/admin/estoque/saldos')->assertOk()->json('data'))
            ->first(fn ($s) => $s['produto_id'] === $p13 && $s['setor_id'] === $deposito);
        $this->assertEquals(9, $saldo['quantidade'], 'a venda baixou o estoque');

        // B não alcança o pedido nem o saldo de A.
        $this->ler($tokenB, "/api/admin/pedidos/{$pedido}")->assertNotFound();
        $this->assertSame([], collect($this->ler($tokenB, '/api/admin/estoque/saldos')->assertOk()->json('data'))
            ->where('produto_id', $p13)->values()->all());

        // O checklist de A reconhece estoque e condição, e cobra o preço zero
        // do catálogo (o pedido acima informou o preço no item, não no cadastro).
        $itens = collect($this->ler($tokenA, '/api/admin/dashboard/pronto-para-vender')->assertOk()->json('data.itens'))->keyBy('chave');
        $this->assertTrue($itens['estoque']['ok']);
        $this->assertTrue($itens['condicao']['ok']);
        $this->assertFalse($itens['preco']['ok']);
    }
}
