<?php

namespace Tests\Feature;

use App\Etl\Support\RegistroDaConversao;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * F7 — o plano de controle da conversão ganha memória.
 *
 * ## O que já existia, e é bastante
 *
 * O ETL é bom: 28 migradores com ordenação topológica por dependência,
 * invariantes por migrador, `--dry-run`, e uma trava pós-cutover que detecta o
 * cutover **pela evidência no banco** (existe pedido criado aqui) em vez de por
 * flag que alguém precisa lembrar de ligar. Ele já distingue "origem
 * indisponível" de "origem vazia" — que é o espírito do F7-08.
 *
 * ## O que faltava
 *
 * **Nada disso era persistido.** Tudo vivia no terminal de quem rodou. Quando a
 * carga terminava não sobrava resposta para: que execução foi essa, de onde veio
 * cada linha, e — a que mais dói — **o que foi descartado e por quê**.
 *
 * A conferência de um cutover acontece dias depois (*"faltam 40 clientes"*), e
 * sem quarentena a única saída seria rodar tudo de novo e comparar. Com o
 * sistema já em produção isso é impossível, e com razão: a trava pós-cutover
 * existe justamente para impedir a recarga que sobrescreve trabalho real.
 *
 * ## Três tabelas, não oito
 *
 * O plano nomeia oito entidades. Três resolvem o que a operação precisa agora;
 * as outras cinco descrevem um pipeline de *staging* que este ETL não usa — ele
 * lê do dump e escreve no destino, sem área intermediária.
 *
 * Criá-las vazias seria pior que não criar: tabela sem escritor **parece**
 * resolvida e não responde nada. Foi exatamente o que aconteceu com
 * `tenant_account_id` em F1 — criada e deixada nula, e por isso invisível como
 * problema.
 */
class PlanoDeControleDaConversaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_execucao_fica_registrada_do_inicio_ao_fim(): void
    {
        $registro = app(RegistroDaConversao::class);

        $id = $registro->iniciar('clientes', dryRun: false, comInvariantes: true);
        $this->assertNotNull($id);

        $aberta = DB::table('conversao_execucoes')->find($id);
        $this->assertSame('EM_ANDAMENTO', $aberta->situacao);
        $this->assertSame('clientes', $aberta->alvo);
        $this->assertNotNull($aberta->iniciada_em);
        $this->assertNull($aberta->encerrada_em);

        $registro->encerrar('CONCLUIDA', 'tudo certo', ['lidas' => 100, 'gravadas' => 98, 'quarentena' => 2]);

        $fechada = DB::table('conversao_execucoes')->find($id);
        $this->assertSame('CONCLUIDA', $fechada->situacao);
        $this->assertNotNull($fechada->encerrada_em);
        $this->assertSame(98, (int) $fechada->linhas_gravadas);
        $this->assertSame(2, (int) $fechada->linhas_quarentena);
    }

    /** A linhagem responde "de onde veio esta linha". */
    public function test_linhagem_liga_o_destino_a_origem(): void
    {
        $registro = app(RegistroDaConversao::class);
        $registro->iniciar('clientes', false, false);

        $registro->linhagem('oracle', 'clientes', '4218', 'clientes', 991, 'v2');

        $linha = DB::table('conversao_linhagem')->where('pk_origem', '4218')->first();

        $this->assertSame('oracle', $linha->sistema_origem);
        $this->assertSame(991, (int) $linha->id_destino);
        $this->assertSame('v2', $linha->versao_transformador);
    }

    /**
     * Reprocessar atualiza, não duplica.
     *
     * A recarga do ETL é rotina antes do cutover — é o que torna a carga final
     * possível. Uma linhagem que duplicasse a cada rodada ficaria inútil
     * justamente no momento em que é mais consultada.
     */
    public function test_reprocessar_atualiza_a_linhagem_sem_duplicar(): void
    {
        $registro = app(RegistroDaConversao::class);
        $registro->iniciar(null, false, false);

        $registro->linhagem('oracle', 'clientes', '4218', 'clientes', 991, 'v1');
        $registro->linhagem('oracle', 'clientes', '4218', 'clientes', 991, 'v2');

        $this->assertSame(1, DB::table('conversao_linhagem')->where('pk_origem', '4218')->count());
        $this->assertSame(
            'v2',
            DB::table('conversao_linhagem')->where('pk_origem', '4218')->value('versao_transformador'),
            'a versão do transformador é atualizada',
        );
    }

    /**
     * A unicidade é garantida pelo BANCO.
     *
     * Verificação feita em PHP não sobrevive a duas execuções simultâneas — e o
     * ETL é exatamente o tipo de processo que alguém roda duas vezes por engano
     * em terminais diferentes.
     */
    public function test_o_indice_impede_linhagem_duplicada(): void
    {
        $registro = app(RegistroDaConversao::class);
        $id = $registro->iniciar(null, false, false);

        $registro->linhagem('oracle', 'clientes', '4218', 'clientes', 991);

        $this->expectException(QueryException::class);

        // Inserção CRUA, sem passar pelo upsert: é o índice que precisa recusar.
        DB::table('conversao_linhagem')->insert([
            'conversao_execucao_id' => $id,
            'sistema_origem' => 'oracle', 'entidade' => 'clientes', 'pk_origem' => '4218',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * A quarentena guarda o PAYLOAD.
     *
     * Uma quarentena que diz que algo foi descartado sem permitir recuperar o
     * dado responde metade da pergunta — e é a metade menos útil.
     */
    public function test_quarentena_guarda_o_motivo_e_o_dado_bruto(): void
    {
        $registro = app(RegistroDaConversao::class);
        $registro->iniciar(null, false, false);

        $registro->quarentena(
            'oracle', 'clientes', '7731',
            motivo: 'OWNER_AMBIGUO',
            detalhe: 'cliente aparece em duas empresas do dump',
            payload: ['id' => 7731, 'nome' => 'Fulano', 'empresa' => null],
        );

        $linha = DB::table('conversao_quarentena')->where('pk_origem', '7731')->first();

        $this->assertSame('OWNER_AMBIGUO', $linha->motivo);
        $this->assertSame('PENDENTE', $linha->decisao, 'quarentena nasce esperando decisão humana');
        $this->assertStringContainsString('Fulano', (string) $linha->payload);
    }

    /**
     * O registro NUNCA derruba a carga.
     *
     * Instrumentação que interrompe o processo que ela observa inverte a
     * prioridade: o dado migrado vale mais que o registro de que ele migrou.
     */
    public function test_falha_ao_registrar_nao_interrompe_a_conversao(): void
    {
        Schema::drop('conversao_quarentena');
        Schema::drop('conversao_linhagem');
        Schema::drop('conversao_execucoes');

        $registro = app(RegistroDaConversao::class);

        // Nenhuma destas chamadas pode lançar.
        $this->assertNull($registro->iniciar('clientes', false, false));
        $registro->linhagem('oracle', 'clientes', '1', 'clientes', 1);
        $registro->quarentena('oracle', 'clientes', '1', 'ESTRUTURA');
        $registro->encerrar('CONCLUIDA');

        $this->assertTrue(true, 'a carga segue mesmo sem as tabelas de controle');
    }

    /**
     * F7-04A — lista de migradores vazia NUNCA produz sucesso.
     *
     * Sem esta trava, um registry vazio faz o loop não rodar, nada falhar, e o
     * comando imprimir "ETL concluído" com SUCCESS. Um script de deploy leria
     * isso como carga bem-sucedida, e a operação descobriria pelo sistema vazio.
     *
     * Mesma família do guardião que varria zero arquivos: o verde que não prova
     * nada é pior que o vermelho, porque ninguém investiga.
     */
    public function test_migrador_desconhecido_reprova(): void
    {
        $this->artisan('etl:run migrador-que-nao-existe --dry-run')
            ->expectsOutputToContain('não encontrado')
            ->assertFailed();
    }
}
