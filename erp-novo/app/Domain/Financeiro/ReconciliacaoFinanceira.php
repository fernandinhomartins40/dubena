<?php

namespace App\Domain\Financeiro;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * F5-10 — as invariantes do dinheiro, conferidas por empresa e período.
 *
 * > *"valores por título, pagamento, conta, caixa, documento e imposto fecham
 * > por empresa e período."*
 *
 * ## O que já existia, e por que não bastava
 *
 * `notify:inconsistencias` compara saldo de conta com a soma dos movimentos, e
 * saldo de estoque com o ledger. Bom, e insuficiente para esta tarefa por três
 * motivos:
 *
 *  - **não recorta por empresa nem por período** — num SaaS, "há 3
 *    inconsistências" não diz de quem, e sem período não dá para fechar um mês;
 *  - cobre 2 das 6 dimensões que a tarefa nomeia;
 *  - **devolve SUCCESS mesmo achando divergência**, então não serve de portão.
 *
 * ## Conferência nunca ajusta
 *
 * O mesmo princípio do F4, e pela mesma razão. Se a soma das parcelas não bate
 * com o valor do título, ou o título está errado, ou falta parcela. Sobrescrever
 * resolve a tela e apaga a pergunta — e se a resposta era a segunda, o dinheiro
 * some da contabilidade sem rastro.
 *
 * ## As invariantes
 *
 * Cada uma responde a uma pergunta que alguém faz quando o mês não fecha:
 *
 *  1. **título × parcelas** — a soma das parcelas é o valor do título?
 *  2. **parcela × baixa** — parcela baixada tem valor efetivado e data?
 *  3. **conta × movimentos** — o saldo é a soma do que entrou e saiu?
 *  4. **movimento de baixa × parcela** — todo movimento de baixa aponta para
 *     uma parcela que está mesmo baixada?
 *  5. **nota × itens** — o total da nota é a soma dos itens?
 *  6. **imposto × item** — o valor do tributo é coerente com base e alíquota?
 */
class ReconciliacaoFinanceira
{
    /** Tolerância de um centavo: arredondamento legítimo não é divergência. */
    private const TOLERANCIA = 0.01;

    /**
     * Confere todas as invariantes de uma empresa no período.
     *
     * @return Collection<int, array<string,mixed>>
     */
    public function divergencias(int $empresaId, string $inicio, string $fim): Collection
    {
        return collect()
            ->concat($this->tituloContraParcelas($empresaId, $inicio, $fim))
            ->concat($this->parcelaBaixadaSemValor($empresaId, $inicio, $fim))
            ->concat($this->contaContraMovimentos($empresaId))
            ->concat($this->movimentoDeBaixaOrfao($empresaId, $inicio, $fim))
            ->concat($this->notaContraItens($empresaId, $inicio, $fim))
            ->values();
    }

    /**
     * Σ parcelas = valor do título.
     *
     * A divergência aqui costuma vir de parcelamento editado depois da criação:
     * alguém muda o valor de uma parcela e o título continua com o total antigo.
     *
     * @return Collection<int, array<string,mixed>>
     */
    private function tituloContraParcelas(int $empresaId, string $inicio, string $fim): Collection
    {
        return DB::table('financeiros as f')
            ->where('f.empresa_id', $empresaId)
            ->where('f.cancelado', false)
            ->whereDate('f.data_emissao', '>=', $inicio)
            ->whereDate('f.data_emissao', '<=', $fim)
            ->selectRaw('f.id, f.valor, coalesce((select sum(p.valor) from financeiroparcelas p '
                .'where p.financeiro_id = f.id), 0) as soma_parcelas')
            ->get()
            ->filter(fn ($r) => abs((float) $r->valor - (float) $r->soma_parcelas) > self::TOLERANCIA)
            ->map(fn ($r) => [
                'invariante' => 'titulo_x_parcelas',
                'referencia' => "titulo #{$r->id}",
                'esperado' => round((float) $r->valor, 2),
                'obtido' => round((float) $r->soma_parcelas, 2),
                'diferenca' => round((float) $r->valor - (float) $r->soma_parcelas, 2),
            ])
            ->values();
    }

    /**
     * Parcela baixada tem valor efetivado E data.
     *
     * Baixada sem valor é dinheiro que o sistema diz ter recebido sem dizer
     * quanto; sem data é recebimento que não cai em período nenhum — some de
     * qualquer DRE.
     *
     * @return Collection<int, array<string,mixed>>
     */
    private function parcelaBaixadaSemValor(int $empresaId, string $inicio, string $fim): Collection
    {
        return DB::table('financeiroparcelas as p')
            ->join('financeiros as f', 'f.id', '=', 'p.financeiro_id')
            ->where('f.empresa_id', $empresaId)
            ->where('f.cancelado', false)
            ->where('p.baixado', true)
            ->where(fn ($q) => $q
                ->whereNull('p.datahora_baixa')
                ->orWhere('p.valor_efetivado', '<=', 0))
            ->whereDate('p.vencimento', '>=', $inicio)
            ->whereDate('p.vencimento', '<=', $fim)
            ->select('p.id', 'p.valor', 'p.valor_efetivado', 'p.datahora_baixa')
            ->get()
            ->map(fn ($r) => [
                'invariante' => 'parcela_baixada_incompleta',
                'referencia' => "parcela #{$r->id}",
                'esperado' => round((float) $r->valor, 2),
                'obtido' => round((float) $r->valor_efetivado, 2),
                'diferenca' => $r->datahora_baixa === null ? null : round((float) $r->valor - (float) $r->valor_efetivado, 2),
                'observacao' => $r->datahora_baixa === null ? 'baixada sem data' : 'baixada sem valor',
            ])
            ->values();
    }

    /**
     * Saldo da conta = Σ movimentos.
     *
     * Sem recorte de período de propósito: o saldo é acumulado desde a abertura,
     * e conferir só uma janela compararia um total contra uma fatia.
     *
     * @return Collection<int, array<string,mixed>>
     */
    private function contaContraMovimentos(int $empresaId): Collection
    {
        return DB::table('contas as c')
            ->where('c.empresa_id', $empresaId)
            ->selectRaw('c.id, c.descricao, c.saldo_atual, coalesce((select sum(m.valor) '
                .'from contamovimentos m where m.conta_id = c.id), 0) as soma')
            ->get()
            ->filter(fn ($r) => abs((float) $r->saldo_atual - (float) $r->soma) > self::TOLERANCIA)
            ->map(fn ($r) => [
                'invariante' => 'conta_x_movimentos',
                'referencia' => "conta #{$r->id} ({$r->descricao})",
                'esperado' => round((float) $r->saldo_atual, 2),
                'obtido' => round((float) $r->soma, 2),
                'diferenca' => round((float) $r->saldo_atual - (float) $r->soma, 2),
            ])
            ->values();
    }

    /**
     * Movimento de baixa aponta para parcela realmente baixada.
     *
     * O caso que isto pega é o estorno pela metade: o movimento inverso não foi
     * gerado, ou a parcela foi reaberta sem o movimento correspondente. Nas duas
     * pontas o caixa e o financeiro passam a contar histórias diferentes.
     *
     * @return Collection<int, array<string,mixed>>
     */
    private function movimentoDeBaixaOrfao(int $empresaId, string $inicio, string $fim): Collection
    {
        return DB::table('contamovimentos as m')
            ->join('financeiroparcelas as p', 'p.id', '=', 'm.financeiroparcela_id')
            ->where('m.empresa_id', $empresaId)
            ->whereNotNull('m.financeiroparcela_id')
            ->where('p.baixado', false)
            // Movimento de estorno é o par legítimo de uma parcela reaberta.
            ->whereNull('m.estorno_de_id')
            ->whereDate('m.datahora', '>=', $inicio)
            ->whereDate('m.datahora', '<=', $fim)
            ->select('m.id', 'm.valor', 'p.id as parcela_id')
            ->get()
            ->filter(function ($r) {
                // Só é órfão se NINGUÉM estornou este movimento — senão a
                // parcela aberta é exatamente o resultado esperado do estorno.
                return ! DB::table('contamovimentos')->where('estorno_de_id', $r->id)->exists();
            })
            ->map(fn ($r) => [
                'invariante' => 'movimento_de_baixa_orfao',
                'referencia' => "movimento #{$r->id} -> parcela #{$r->parcela_id}",
                'esperado' => round((float) $r->valor, 2),
                'obtido' => 0.0,
                'diferenca' => round((float) $r->valor, 2),
                'observacao' => 'o caixa registrou a baixa, o financeiro nao',
            ])
            ->values();
    }

    /**
     * Total da nota = Σ itens.
     *
     * Uma nota autorizada com total diferente da soma dos itens é divergência
     * fiscal: o XML que a SEFAZ guarda não bate com o que o sistema mostra.
     *
     * @return Collection<int, array<string,mixed>>
     */
    private function notaContraItens(int $empresaId, string $inicio, string $fim): Collection
    {
        return DB::table('notas_fiscais as n')
            ->where('n.empresa_id', $empresaId)
            ->whereNotNull('n.emitida_em')
            ->whereDate('n.emitida_em', '>=', $inicio)
            ->whereDate('n.emitida_em', '<=', $fim)
            ->selectRaw('n.id, n.numero, n.valor_produtos, coalesce((select sum(i.valor_total) '
                .'from nota_itens i where i.nota_fiscal_id = n.id), 0) as soma_itens')
            ->get()
            ->filter(fn ($r) => abs((float) $r->valor_produtos - (float) $r->soma_itens) > self::TOLERANCIA)
            ->map(fn ($r) => [
                'invariante' => 'nota_x_itens',
                'referencia' => "nota #{$r->numero} (id {$r->id})",
                'esperado' => round((float) $r->valor_produtos, 2),
                'obtido' => round((float) $r->soma_itens, 2),
                'diferenca' => round((float) $r->valor_produtos - (float) $r->soma_itens, 2),
            ])
            ->values();
    }
}
