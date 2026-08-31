<?php

namespace Tests\Feature;

use App\Domain\Contrato\ColetorDeSchema;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F2-01 — o contrato passa a descrever a FORMA, não só a existência.
 *
 * O manifesto de API já pegava rota removida ou acrescentada. O que ele não
 * pegava é o que quebra a SPA e os apps com muito mais frequência: um campo que
 * some do payload, um obrigatório que aparece, um tipo que muda de número para
 * string. Nada disso altera a lista de endpoints, e todos derrubam o consumidor.
 *
 * Este arquivo prova que a captura funciona. O contrato em si é gerado por
 * `php artisan api:schema`, que roda a suíte com a coleta ligada — a cobertura
 * do contrato é, honestamente, a cobertura da suíte.
 */
class ApiSchemaContratoTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        ColetorDeSchema::desligar();
        parent::tearDown();
    }

    /** @return array{User, Empresa} */
    private function cenario(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        return [$user, $empresa];
    }

    /** Desligado, não observa nada — o custo em produção é um booleano. */
    public function test_coleta_desligada_nao_registra(): void
    {
        // Durante `API_SCHEMA_CAPTURA=1` a coleta é ligada para a suíte inteira,
        // de propósito. Afirmar "nada foi observado" ali mediria o contrário do
        // que a execução está fazendo.
        if (getenv('API_SCHEMA_CAPTURA') === '1') {
            $this->markTestSkipped('coleta ligada globalmente nesta execução.');
        }

        [$user] = $this->cenario();
        ColetorDeSchema::desligar();

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/clientes')->assertOk();

        $this->assertSame([], ColetorDeSchema::coletado());
    }

    /** Os campos que a rota EXIGE entram no contrato, com a obrigatoriedade. */
    public function test_captura_os_campos_obrigatorios_do_request(): void
    {
        [$user] = $this->cenario();
        // `zerar: true` isola do que a suíte já acumulou nesta execução.
        ColetorDeSchema::ligar();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/clientes', [
                'nome' => 'Fulano', 'telefones' => [['telefone' => '42999990001']],
            ])
            ->assertCreated();

        $contrato = ColetorDeSchema::coletado();
        $rota = $contrato['POST api/admin/clientes'] ?? null;

        $this->assertNotNull($rota, 'a rota exercitada precisa aparecer no contrato');
        $this->assertArrayHasKey('nome', $rota['request']);
        $this->assertTrue($rota['request']['nome'], '`nome` é obrigatório e o contrato tem de dizer isso');
    }

    /** A forma da resposta: caminhos e tipos, para detectar campo que some. */
    public function test_captura_a_forma_da_resposta(): void
    {
        [$user, $empresa] = $this->cenario();
        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        ColetorDeSchema::ligar();
        $this->actingAs($user, 'sanctum')->getJson("/api/admin/clientes/{$cliente->id}")->assertOk();

        $resposta = ColetorDeSchema::coletado()['GET api/admin/clientes/{id}']['response'] ?? [];

        $this->assertArrayHasKey('data.id', $resposta);
        $this->assertArrayHasKey('data.nome', $resposta);
        $this->assertSame('string', $resposta['data.nome']);
    }

    /** Lista vira a forma do ITEM: o contrato é o formato, não a quantidade. */
    public function test_lista_descreve_o_item_e_nao_a_quantidade(): void
    {
        [$user, $empresa] = $this->cenario();
        Cliente::factory()->count(3)->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        ColetorDeSchema::ligar();
        $this->actingAs($user, 'sanctum')->getJson('/api/admin/clientes')->assertOk();

        $resposta = ColetorDeSchema::coletado()['GET api/admin/clientes']['response'] ?? [];

        $this->assertNotEmpty($resposta);
        foreach (array_keys($resposta) as $caminho) {
            $this->assertStringNotContainsString('.0.', $caminho, 'índice numérico não pertence ao contrato');
        }
    }

    /**
     * Resposta de ERRO não entra: o corpo de um 422 é a forma da falha, e
     * misturá-la faria o contrato prometer campos que só existem quando algo
     * deu errado.
     */
    public function test_resposta_de_erro_nao_entra_no_contrato(): void
    {
        [$user] = $this->cenario();
        ColetorDeSchema::ligar();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/clientes', [])
            ->assertStatus(422);

        $rota = ColetorDeSchema::coletado()['POST api/admin/clientes'] ?? null;

        $this->assertNotNull($rota);
        $this->assertSame([], $rota['response'], 'o corpo do 422 descreve o erro, não o contrato');
        $this->assertContains(422, $rota['status'], 'mas o status observado fica registrado');
    }

    /** Rota fora de `api/` não é contrato de API e não é observada. */
    public function test_so_observa_rotas_de_api(): void
    {
        [$user] = $this->cenario();
        ColetorDeSchema::ligar();

        $this->get('/');
        // Uma rota de api no meio, para o teste não passar por vazio.
        $this->actingAs($user, 'sanctum')->getJson('/api/admin/clientes')->assertOk();

        $chaves = array_keys(ColetorDeSchema::coletado());

        $this->assertNotEmpty($chaves);
        foreach ($chaves as $chave) {
            $this->assertStringContainsString(' api/', $chave);
        }
    }
}
