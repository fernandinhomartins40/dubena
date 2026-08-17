<?php

namespace Tests\Feature;

use App\Domain\Satelite\ComodatoPdfService;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Produto\Produto;
use App\Models\Satelite\Comodato;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GATE do item 20 da triagem — contrato de comodato.
 *
 * A triagem: *"o contrato é o documento que protege o patrimônio da revenda (o
 * vasilhame)"*. O botijão fica na casa do cliente; sem contrato assinado, a
 * revenda não tem como reaver nem cobrar.
 *
 * A regra que estes testes fixam: o contrato descreve uma **obrigação vigente**.
 * Depois da devolução total não há posse a documentar, e imprimir mesmo assim
 * produziria papel afirmando que o cliente está com um vasilhame que já
 * devolveu — base para uma cobrança indevida.
 */
class ComodatoPdfTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Empresa,2:Cliente,3:Produto} */
    private function cenario(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'support' => true,
        ]);
        $cliente = Cliente::create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'nome' => 'João Pereira',
            'cpf' => '12345678909',
            'endereco' => 'Rua das Flores',
            'numero' => '45',
            'cep' => '85010000',
            'uf' => 'PR',
            'cliente' => true,
        ]);
        $produto = Produto::create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Vasilhame P13',
            'vasilhame_retornavel' => true,
            'ativo' => true,
        ]);

        return [$user, $empresa, $cliente, $produto];
    }

    private function comodato(Empresa $e, Cliente $c, Produto $p, array $extra = []): Comodato
    {
        return Comodato::create([
            'empresa_id' => $e->id,
            'grupo_id' => $e->grupo_id,
            'cliente_id' => $c->id,
            'produto_id' => $p->id,
            'quantidade' => 2,
            'quantidade_devolvida' => 0,
            'situacao' => 'ATIVO',
            'data_emprestimo' => now()->toDateString(),
            ...$extra,
        ]);
    }

    public function test_comodato_ativo_gera_contrato(): void
    {
        [$user, $e, $c, $p] = $this->cenario();
        $comodato = $this->comodato($e, $c, $p);

        $resposta = $this->actingAs($user, 'sanctum')->get("/api/admin/comodatos/{$comodato->id}/contrato");

        $resposta->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $resposta->getContent());
    }

    public function test_devolucao_parcial_ainda_gera_contrato(): void
    {
        [$user, $e, $c, $p] = $this->cenario();
        $comodato = $this->comodato($e, $c, $p, [
            'quantidade_devolvida' => 1,
            'situacao' => 'PARCIAL',
        ]);

        // Sobrou 1 vasilhame com o cliente: a obrigação continua vigente, e o
        // contrato tem de refletir o saldo em poder dele.
        $this->actingAs($user, 'sanctum')
            ->get("/api/admin/comodatos/{$comodato->id}/contrato")
            ->assertOk();
    }

    public function test_comodato_devolvido_nao_gera_contrato(): void
    {
        [$user, $e, $c, $p] = $this->cenario();
        $comodato = $this->comodato($e, $c, $p, [
            'quantidade_devolvida' => 2,
            'situacao' => 'DEVOLVIDO',
            'data_devolucao' => now()->toDateString(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/comodatos/{$comodato->id}/contrato")
            ->assertStatus(422);
    }

    public function test_sem_saldo_em_poder_do_cliente_nao_gera_contrato(): void
    {
        [, $e, $c, $p] = $this->cenario();

        // Situação ainda não fechada, mas nada pendente: não há o que contratar.
        $comodato = $this->comodato($e, $c, $p, [
            'quantidade_devolvida' => 2,
            'situacao' => 'PARCIAL',
        ]);

        $this->expectException(\DomainException::class);
        app(ComodatoPdfService::class)->contrato($comodato);
    }

    public function test_exige_permissao(): void
    {
        [, $e, $c, $p] = $this->cenario();
        $comodato = $this->comodato($e, $c, $p);
        $leitor = User::factory()->create([
            'empresa_id' => $e->id, 'grupo_id' => $e->grupo_id, 'support' => false,
        ]);

        $this->actingAs($leitor, 'sanctum')
            ->getJson("/api/admin/comodatos/{$comodato->id}/contrato")
            ->assertStatus(403);
    }

    public function test_contrato_nao_altera_o_comodato(): void
    {
        [$user, $e, $c, $p] = $this->cenario();
        $comodato = $this->comodato($e, $c, $p);
        $antes = $comodato->only(['quantidade', 'quantidade_devolvida', 'situacao', 'data_devolucao']);

        $this->actingAs($user, 'sanctum')->get("/api/admin/comodatos/{$comodato->id}/contrato")->assertOk();

        // Imprimir é leitura. Diferente do legado, onde a impressão disparava
        // efeito colateral, aqui gerar o papel não pode mexer no estado.
        $this->assertSame($antes, $comodato->fresh()->only(array_keys($antes)));
    }
}
