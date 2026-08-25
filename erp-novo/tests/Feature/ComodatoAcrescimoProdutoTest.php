<?php

namespace Tests\Feature;

use App\Domain\Satelite\ComodatoPdfService;
use App\Domain\Satelite\ComodatoService;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Produto\Produto;
use App\Models\Satelite\Comodato;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Acrescentar um vasilhame de OUTRO tipo à relação com o mesmo cliente.
 *
 * O diálogo dizia "Acrescentar itens" mas só aceitava quantidade do vasilhame
 * já emprestado. Para dar um P20 a quem tinha P13 o operador precisava fechar a
 * tela e abrir um comodato do zero — que é justamente o que gerava dois
 * contratos para a mesma relação.
 *
 * O comodato continua sendo um por produto (é o que permite à vigilância medir
 * giro por capacidade). Quem consolida é o contrato.
 */
class ComodatoAcrescimoProdutoTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $user;

    private Cliente $cliente;

    private Produto $p13;

    private Produto $p20;

    private Comodato $comodato;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::factory()->create();
        $this->user = User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
            'support' => true,
        ]);

        $this->cliente = Cliente::factory()->create([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
        ]);

        $this->p13 = $this->vasilhame('Vasilha P13 Kg');
        $this->p20 = $this->vasilhame('Vasilha P20 Kg');

        $this->comodato = Comodato::create([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $this->cliente->id,
            'produto_id' => $this->p13->id,
            'quantidade' => 5,
            'quantidade_devolvida' => 0,
            'situacao' => 'ATIVO',
            'data_emprestimo' => now()->toDateString(),
            'nome_representante' => 'Maria Souza',
            'data_vencimento' => now()->addYear()->toDateString(),
        ]);
    }

    private function vasilhame(string $descricao, ?Empresa $de = null): Produto
    {
        $e = $de ?? $this->empresa;

        return Produto::create([
            'empresa_id' => $e->id,
            'grupo_id' => $e->grupo_id,
            'descricao' => $descricao,
            'ativo' => true,
        ]);
    }

    /** O caso que motivou a mudança: cliente com P13 passa a ter também P20. */
    public function test_acrescenta_vasilhame_de_outro_tipo(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/admin/comodatos/{$this->comodato->id}/acrescentar", [
                'produto_id' => $this->p20->id,
                'quantidade' => 3,
            ])
            ->assertOk();

        // A linha original não se mexe: são posses distintas.
        $this->assertSame(5.0, (float) $this->comodato->refresh()->quantidade);

        $novo = Comodato::query()
            ->where('cliente_id', $this->cliente->id)
            ->where('produto_id', $this->p20->id)
            ->first();

        $this->assertNotNull($novo, 'Deveria ter nascido o comodato do P20.');
        $this->assertSame(3.0, (float) $novo->quantidade);
        $this->assertSame('ATIVO', (string) $novo->situacao);
    }

    /**
     * O acordo é o mesmo. Sem herdar signatário e vencimento o contrato novo
     * sairia sem quem assina — e nasceria já disparando alerta de vencimento.
     */
    public function test_linha_nova_herda_o_acordo_em_curso(): void
    {
        app(ComodatoService::class)->acrescentarProduto(
            $this->comodato, $this->p20->id, 3, $this->user->id,
        );

        $novo = Comodato::query()->where('produto_id', $this->p20->id)->firstOrFail();

        $this->assertSame('Maria Souza', $novo->nome_representante);
        $this->assertSame(
            $this->comodato->data_vencimento?->toDateString(),
            $novo->data_vencimento?->toDateString(),
        );
    }

    /** Se o cliente já tem aquele tipo, a linha existente cresce em vez de duplicar. */
    public function test_soma_na_linha_existente_do_mesmo_produto(): void
    {
        $existente = Comodato::create([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $this->cliente->id,
            'produto_id' => $this->p20->id,
            'quantidade' => 2,
            'quantidade_devolvida' => 0,
            'situacao' => 'ATIVO',
            'data_emprestimo' => now()->toDateString(),
        ]);

        app(ComodatoService::class)->acrescentarProduto(
            $this->comodato, $this->p20->id, 3, $this->user->id,
        );

        $this->assertSame(5.0, (float) $existente->refresh()->quantidade);
        $this->assertSame(
            1,
            Comodato::query()->where('cliente_id', $this->cliente->id)
                ->where('produto_id', $this->p20->id)->count(),
            'Não pode nascer uma segunda linha do mesmo produto.',
        );
    }

    /** Sem `produto_id` o comportamento antigo continua valendo. */
    public function test_sem_produto_acrescenta_no_mesmo_vasilhame(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/admin/comodatos/{$this->comodato->id}/acrescentar", [
                'quantidade' => 2,
            ])
            ->assertOk();

        $this->assertSame(7.0, (float) $this->comodato->refresh()->quantidade);
        $this->assertSame(1, Comodato::query()->where('cliente_id', $this->cliente->id)->count());
    }

    /**
     * A fronteira de empresa. `exists:produtos,id` deixaria o comodato nascer
     * apontando para o vasilhame de outra revenda do grupo.
     */
    public function test_nao_aceita_vasilhame_de_outra_empresa(): void
    {
        $outra = Empresa::factory()->create(['grupo_id' => $this->empresa->grupo_id]);
        $alheio = $this->vasilhame('Vasilha P20 Kg', $outra);

        $this->expectException(ValidationException::class);

        app(ComodatoService::class)->acrescentarProduto(
            $this->comodato, $alheio->id, 3, $this->user->id,
        );
    }

    /** O contrato descreve a relação: lista os dois vasilhames e o total. */
    public function test_contrato_consolida_os_itens_do_cliente(): void
    {
        app(ComodatoService::class)->acrescentarProduto(
            $this->comodato, $this->p20->id, 3, $this->user->id,
        );

        $pdf = app(ComodatoPdfService::class)->contrato($this->comodato->refresh());

        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    /**
     * O contrato de uma empresa não pode listar o vasilhame emprestado por
     * outra — o cliente pode ser atendido por mais de uma revenda do grupo.
     */
    public function test_contrato_nao_lista_comodato_de_outra_empresa(): void
    {
        $outra = Empresa::factory()->create(['grupo_id' => $this->empresa->grupo_id]);

        Comodato::create([
            'empresa_id' => $outra->id,
            'grupo_id' => $outra->grupo_id,
            'cliente_id' => $this->cliente->id,
            'produto_id' => $this->vasilhame('Vasilha P45 Kg', $outra)->id,
            'quantidade' => 9,
            'quantidade_devolvida' => 0,
            'situacao' => 'ATIVO',
            'data_emprestimo' => now()->toDateString(),
        ]);

        $metodo = new \ReflectionMethod(ComodatoPdfService::class, 'outrosDoCliente');
        $metodo->setAccessible(true);

        $outros = $metodo->invoke(app(ComodatoPdfService::class), $this->comodato);

        $this->assertCount(0, $outros, 'O comodato da outra empresa não pode entrar no contrato.');
    }
}
