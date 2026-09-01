<?php

namespace App\Etl\Invariants;

use App\Etl\Contracts\Invariant;
use App\Etl\Support\InvariantResult;
use App\Etl\Support\MigrationContext;
use Closure;

/**
 * Invariante de CONTAGEM: nº de registros no legado == nº no novo, ajustado por
 * descartes e acréscimos legítimos. É o check #1 da regra de ouro do plano.
 *
 * A fórmula é: `origem - descartes + acrescimos == destino`.
 *
 * **Por que existe `acrescimosEsperados` (T2.4).** A versão anterior só tinha o
 * lado dos descartes. Migrators que criam linhas a partir de uma SEGUNDA origem
 * — o caso do `AppGasEmCasaMigrator`, que traz pedidos do app posteriores ao
 * corte do dump — eram estruturalmente incapazes de passar. O efeito não foi um
 * falso negativo isolado: falhas legítimas (a duplicação 4× de clientes) e
 * falhas por desenho ficavam indistinguíveis no mesmo placar vermelho, e foi
 * assim que a corrupção real virou ruído de fundo e passou despercebida.
 *
 * Ambos os ajustes aceitam `Closure` além de `int`. **Prefira a closure**: um
 * número fixo é verdade no dia em que foi medido e vira mentira na próxima
 * recarga. A closure recalcula consultando a origem.
 */
final class CountInvariant implements Invariant
{
    /**
     * @param  Closure():int|int  $descartesEsperados  linhas da origem que não devem chegar ao destino
     * @param  Closure():int|int  $acrescimosEsperados  linhas do destino vindas de outra origem
     */
    public function __construct(
        private MigrationContext $ctx,
        private string $tabelaLegado,
        private string $tabelaNova,
        private Closure|int $descartesEsperados = 0,
        private ?string $whereLegado = null,
        private Closure|int $acrescimosEsperados = 0,
    ) {}

    public function nome(): string
    {
        return "contagem {$this->tabelaLegado}→{$this->tabelaNova}";
    }

    public function verificar(): InvariantResult
    {
        // Sem a origem não existe prova: o gate fica inconclusivo e deve falhar.
        //
        // MAS: conexão disponível com a TABELA ausente é FALHA, não skip.
        // Foi exatamente assim que módulos inteiros migraram "0 linhas com
        // sucesso" sem ninguém ver (auditoria 2026-08-14): o nome errado ou o
        // espelho incompleto silenciava a própria checagem que o detectaria.
        try {
            $temTabela = $this->ctx->legado()->getSchemaBuilder()->hasTable($this->tabelaLegado);
        } catch (\Throwable $e) {
            // F7-10 — estado proprio, e nao `falha` com esperado/obtido = -1.
            //
            // Continua bloqueando (nao verificado nunca e aprovacao), mas a
            // mensagem para de misturar "nao consegui verificar" com "verifiquei
            // e esta errado" — que exigem acoes opostas: uma se resolve
            // religando o legado, a outra investigando o dado.
            return InvariantResult::inconclusiva(
                $this->nome(),
                'legado indisponível: '.$e->getMessage(),
            );
        }

        if (! $temTabela) {
            return InvariantResult::falha(
                $this->nome(),
                "origem `{$this->tabelaLegado}` NÃO existe no legado — nome errado "
                    .'ou tabela fora do MAPA do espelho (espelhar_oracle.py)',
                -1,
                (int) $this->ctx->novo()->table($this->tabelaNova)->count(),
            );
        }

        $qLegado = $this->ctx->legado()->table($this->tabelaLegado);
        if ($this->whereLegado) {
            $qLegado->whereRaw($this->whereLegado);
        }

        $descartes = $this->resolver($this->descartesEsperados);
        $acrescimos = $this->resolver($this->acrescimosEsperados);

        $origem = (int) $qLegado->count() - $descartes + $acrescimos;
        $destino = (int) $this->ctx->novo()->table($this->tabelaNova)->count();

        if ($origem === $destino) {
            $detalhe = "origem=destino={$destino}";
            if ($descartes !== 0 || $acrescimos !== 0) {
                $detalhe .= sprintf(' (descartes=%d, acrescimos=%d)', $descartes, $acrescimos);
            }

            return InvariantResult::ok($this->nome(), $detalhe);
        }

        return InvariantResult::falha($this->nome(), 'contagem divergente', $origem, $destino);
    }

    /** Resolve o ajuste, que pode ser um número fixo ou um cálculo sobre a origem. */
    private function resolver(Closure|int $ajuste): int
    {
        return $ajuste instanceof Closure ? (int) ($ajuste)() : $ajuste;
    }
}
