<?php

namespace Tests\Feature;

use App\Domain\Financeiro\PlanoContaModeloService;
use App\Models\Empresa;
use App\Models\Financeiro\PlanoConta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F5-01 — plano de contas modelo, copiável para cada revenda.
 *
 * ## O buraco
 *
 * O ownership já estava resolvido: `planos_conta` é por grupo, ganhou
 * `tenant_account_id` e tem trigger recusando pai e filho de tenants distintos
 * (F1-08).
 *
 * O que não existia era a **origem**. Uma revenda nova entrava com a árvore
 * vazia — o DRE agrupava tudo em "Sem plano", a conciliação contábil não tinha
 * para onde apontar. O sistema funcionava e não servia.
 *
 * O único plano de contas do repositório vivia dentro do
 * `DemoGuarapuavaSeeder`: massa de demonstração, que não roda para revenda real.
 *
 * ## As duas decisões que o teste protege
 *
 * **Copia, não referencia.** As linhas viram registros do grupo. A revenda
 * renomeia e desativa o que quiser, e uma correção posterior no modelo não
 * reescreve o que ela ajustou — do contrário, a contabilidade dela mudaria
 * sozinha num deploy da plataforma.
 *
 * **Idempotente por descrição, não por código.** Código é o campo que a revenda
 * mais mexe: ela renumera do jeito do contador dela. Uma chave por código
 * recriaria a árvore inteira na segunda chamada, depois da primeira renumeração.
 */
class PlanoContaModeloTest extends TestCase
{
    use RefreshDatabase;

    private function contasDoGrupo(int $grupoId)
    {
        return PlanoConta::withoutGrupo()->where('grupo_id', $grupoId);
    }

    public function test_a_copia_traz_a_arvore_do_modelo(): void
    {
        $empresa = Empresa::factory()->create();

        $criadas = app(PlanoContaModeloService::class)->copiarParaGrupo((int) $empresa->grupo_id);

        $this->assertGreaterThan(0, $criadas, 'o modelo não pode chegar vazio na revenda');
        $this->assertSame(
            DB::table('plano_conta_modelos')->where('ativo', true)->count(),
            $this->contasDoGrupo((int) $empresa->grupo_id)->count(),
        );
    }

    /**
     * A hierarquia sobrevive à cópia.
     *
     * Sem o mapa de ids, `pai_id` apontaria para a linha do catálogo de
     * plataforma — outra tabela — e a árvore chegaria plana, com tudo na raiz.
     * O DRE ainda somaria, e ninguém notaria até tentar agrupar por nível.
     */
    public function test_a_hierarquia_e_preservada(): void
    {
        $empresa = Empresa::factory()->create();
        app(PlanoContaModeloService::class)->copiarParaGrupo((int) $empresa->grupo_id);

        $raiz = $this->contasDoGrupo((int) $empresa->grupo_id)
            ->where('descricao', 'Receitas')->firstOrFail();
        $filha = $this->contasDoGrupo((int) $empresa->grupo_id)
            ->where('descricao', 'Venda de GLP')->firstOrFail();

        $this->assertNull($raiz->pai_id, 'a raiz não tem pai');
        $this->assertSame($raiz->id, (int) $filha->pai_id, 'a filha aponta para a raiz COPIADA');
        $this->assertSame(2, (int) $filha->nivel);
    }

    /** Copiar duas vezes não duplica: é seguro chamar a cada empresa criada. */
    public function test_copiar_de_novo_nao_duplica(): void
    {
        $empresa = Empresa::factory()->create();
        $servico = app(PlanoContaModeloService::class);

        $primeira = $servico->copiarParaGrupo((int) $empresa->grupo_id);
        $segunda = $servico->copiarParaGrupo((int) $empresa->grupo_id);

        $this->assertGreaterThan(0, $primeira);
        $this->assertSame(0, $segunda, 'a segunda cópia não cria nada');
        $this->assertSame($primeira, $this->contasDoGrupo((int) $empresa->grupo_id)->count());
    }

    /**
     * O que a revenda ajustou permanece ajustado.
     *
     * Este é o teste que dá sentido a "copia, não referencia": a renumeração
     * dela sobrevive a uma nova cópia, porque a chave natural é a descrição.
     */
    public function test_ajuste_da_revenda_sobrevive_a_uma_nova_copia(): void
    {
        $empresa = Empresa::factory()->create();
        $servico = app(PlanoContaModeloService::class);
        $servico->copiarParaGrupo((int) $empresa->grupo_id);

        // O contador da revenda renumera a conta.
        $conta = $this->contasDoGrupo((int) $empresa->grupo_id)
            ->where('descricao', 'Venda de GLP')->firstOrFail();
        $conta->update(['codigo' => '3.14']);

        $servico->copiarParaGrupo((int) $empresa->grupo_id);

        $this->assertSame(
            '3.14',
            (string) $this->contasDoGrupo((int) $empresa->grupo_id)
                ->where('descricao', 'Venda de GLP')->value('codigo'),
            'a renumeração da revenda não é desfeita por uma nova cópia',
        );
        $this->assertSame(
            1,
            $this->contasDoGrupo((int) $empresa->grupo_id)->where('descricao', 'Venda de GLP')->count(),
            'e nem gera uma segunda linha com o código antigo',
        );
    }

    /** Uma revenda não enxerga a árvore da outra. */
    public function test_a_copia_nao_atravessa_grupos(): void
    {
        $a = Empresa::factory()->create();
        $b = Empresa::factory()->create();

        app(PlanoContaModeloService::class)->copiarParaGrupo((int) $a->grupo_id);

        $this->assertGreaterThan(0, $this->contasDoGrupo((int) $a->grupo_id)->count());
        $this->assertSame(0, $this->contasDoGrupo((int) $b->grupo_id)->count());
    }

    /** Linha desativada no modelo não chega em quem copiar depois. */
    public function test_linha_desativada_no_modelo_nao_e_copiada(): void
    {
        DB::table('plano_conta_modelos')->where('descricao', 'Venda de água')->update(['ativo' => false]);

        $empresa = Empresa::factory()->create();
        app(PlanoContaModeloService::class)->copiarParaGrupo((int) $empresa->grupo_id);

        $this->assertSame(
            0,
            $this->contasDoGrupo((int) $empresa->grupo_id)->where('descricao', 'Venda de água')->count(),
        );
    }

    /**
     * Perfil inexistente não inventa árvore.
     *
     * Devolver o modelo padrão como consolo seria pior: a revenda receberia
     * contas que não pediu, achando que são as do perfil escolhido.
     */
    public function test_perfil_desconhecido_nao_copia_nada(): void
    {
        $empresa = Empresa::factory()->create();

        $criadas = app(PlanoContaModeloService::class)
            ->copiarParaGrupo((int) $empresa->grupo_id, null, 'perfil-que-nao-existe');

        $this->assertSame(0, $criadas);
        $this->assertSame(0, $this->contasDoGrupo((int) $empresa->grupo_id)->count());
    }

    /**
     * O tenant é propagado para a árvore inteira.
     *
     * A trigger de hierarquia (F1-08) recusa pai e filho de tenants distintos —
     * então copiar só os pais com tenant faria a inserção falhar no meio,
     * deixando a revenda com meia árvore.
     */
    public function test_o_tenant_e_propagado_para_toda_a_arvore(): void
    {
        $empresa = Empresa::factory()->create();
        $tenantId = DB::table('tenant_accounts')->insertGetId([
            'legal_name' => 'Revenda F5-01', 'status' => 'ACTIVE',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        app(PlanoContaModeloService::class)->copiarParaGrupo((int) $empresa->grupo_id, $tenantId);

        $semTenant = $this->contasDoGrupo((int) $empresa->grupo_id)
            ->whereNull('tenant_account_id')->count();

        $this->assertSame(0, $semTenant, 'nenhum nó pode ficar sem tenant');
    }
}
