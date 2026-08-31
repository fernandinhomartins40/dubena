<?php

namespace Tests\Feature;

use App\Domain\Cliente\ClienteService;
use App\Domain\Cliente\PapelPessoa;
use App\Domain\Tenant\TenantContext;
use App\Models\Cliente\Cliente;
use App\Models\Cliente\ClientePapel;
use App\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F3-01 (primeira peça) — os papéis da pessoa ganham vigência.
 *
 * `clientes` tinha três booleanos paralelos: `cliente`, `fornecedor`,
 * `transportador`. Eles respondem "é?" e não conseguem responder "era, quando?".
 *
 * O caso que motivou: um fornecedor que deixou de fornecer não tinha como sair
 * da lista sem apagar o histórico. Desmarcar o booleano faz parecer que ele
 * nunca forneceu — e as notas de entrada antigas passam a apontar para alguém
 * que "não é fornecedor".
 *
 * As duas fontes convivem por enquanto: os booleanos ainda são escritos pelo
 * formulário e lidos pelo `ClienteResource` e pelo ETL. Removê-los no mesmo lote
 * deixaria a leitura sem fonte antes de o consumo migrar.
 */
class PapelDaPessoaTest extends TestCase
{
    use RefreshDatabase;

    private function empresa(): Empresa
    {
        return Empresa::factory()->create();
    }

    /** @param array<string,mixed> $extra */
    private function criar(Empresa $empresa, array $extra = []): Cliente
    {
        return app(ClienteService::class)->criar(array_merge([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'nome' => 'Fulano',
        ], $extra));
    }

    /** Marcar um papel abre uma vigência. */
    public function test_marcar_papel_abre_vigencia(): void
    {
        $empresa = $this->empresa();
        $cliente = $this->criar($empresa, ['fornecedor' => true]);

        $papel = ClientePapel::query()
            ->where('cliente_id', $cliente->id)
            ->where('papel', PapelPessoa::FORNECEDOR->value)
            ->firstOrFail();

        $this->assertNotNull($papel->inicio);
        $this->assertNull($papel->fim, 'papel recém-aberto não tem fim');
    }

    /**
     * O ponto da tarefa: desmarcar ENCERRA a vigência, não apaga a linha.
     *
     * É o que permite tirar alguém da lista de hoje sem fazer o passado dele
     * desaparecer.
     */
    public function test_desmarcar_encerra_a_vigencia_sem_apagar_o_historico(): void
    {
        $empresa = $this->empresa();
        $cliente = $this->criar($empresa, ['fornecedor' => true]);

        app(ClienteService::class)->atualizar($cliente, ['fornecedor' => false]);

        $papel = ClientePapel::query()
            ->where('cliente_id', $cliente->id)
            ->where('papel', PapelPessoa::FORNECEDOR->value)
            ->firstOrFail();

        $this->assertNotNull($papel->fim, 'a vigência foi encerrada');
        $this->assertFalse($cliente->fresh()->temPapel(PapelPessoa::FORNECEDOR));
    }

    /** Papel encerrado sai do vigente, mas continua consultável. */
    public function test_papel_encerrado_sai_do_escopo_vigente(): void
    {
        $empresa = $this->empresa();
        $cliente = $this->criar($empresa, ['fornecedor' => true]);
        app(ClienteService::class)->atualizar($cliente, ['fornecedor' => false]);

        $doPapel = fn () => ClientePapel::query()
            ->where('cliente_id', $cliente->id)
            ->where('papel', PapelPessoa::FORNECEDOR->value);

        $this->assertSame(0, $doPapel()->vigentes()->count(), 'saiu do vigente');
        $this->assertSame(1, $doPapel()->count(), 'mas continua consultável');
    }

    /** Voltar a marcar abre uma vigência NOVA — o histórico registra as duas. */
    public function test_voltar_a_marcar_abre_nova_vigencia(): void
    {
        $empresa = $this->empresa();
        $cliente = $this->criar($empresa, ['fornecedor' => true]);
        $servico = app(ClienteService::class);

        $servico->atualizar($cliente, ['fornecedor' => false]);
        $servico->atualizar($cliente->fresh(), ['fornecedor' => true]);

        // Duas linhas de FORNECEDOR: a encerrada e a nova. (O papel CLIENTE
        // tambem existe, porque a coluna `cliente` tem default true — por isso
        // a contagem filtra pelo papel em questao.)
        $this->assertSame(
            2,
            ClientePapel::query()
                ->where('cliente_id', $cliente->id)
                ->where('papel', PapelPessoa::FORNECEDOR->value)
                ->count(),
        );
        $this->assertTrue($cliente->fresh()->temPapel(PapelPessoa::FORNECEDOR));
    }

    /** A mesma pessoa pode acumular papéis — e isso sempre foi correto. */
    public function test_pessoa_pode_ter_varios_papeis(): void
    {
        $empresa = $this->empresa();
        $cliente = $this->criar($empresa, ['cliente' => true, 'fornecedor' => true]);

        $this->assertTrue($cliente->temPapel(PapelPessoa::CLIENTE));
        $this->assertTrue($cliente->temPapel(PapelPessoa::FORNECEDOR));
        $this->assertFalse($cliente->temPapel(PapelPessoa::TRANSPORTADOR));
    }

    /**
     * O fallback existe enquanto as duas fontes convivem: um cadastro criado
     * por um caminho ainda não migrado (ETL, por exemplo) tem só o booleano, e
     * não pode desaparecer da lista por causa disso.
     */
    public function test_cadastro_so_com_booleano_ainda_e_reconhecido(): void
    {
        $empresa = $this->empresa();

        // Criado direto pelo model, sem passar pelo serviço — como o ETL faz.
        $cliente = Cliente::query()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'nome' => 'Vindo do ETL',
            'fornecedor' => true,
        ]);

        $this->assertSame(0, ClientePapel::query()->where('cliente_id', $cliente->id)->count());
        $this->assertTrue($cliente->temPapel(PapelPessoa::FORNECEDOR));
    }

    /** Os papéis são escopados por empresa, como todo dado de tenant. */
    public function test_papel_nao_atravessa_empresa(): void
    {
        $empresaA = $this->empresa();
        $empresaB = $this->empresa();

        $daA = $this->criar($empresaA, ['fornecedor' => true]);

        app(TenantContext::class)->set($empresaB->id, $empresaB->grupo_id);

        $this->assertSame(
            0,
            ClientePapel::query()->where('cliente_id', $daA->id)->count(),
            'papel de outra empresa não é visível',
        );
    }
}
