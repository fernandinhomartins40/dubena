<?php

namespace App\Domain\Legado;

use Illuminate\Support\Facades\DB;

/**
 * F9-07 — quem ainda chama as pontes legadas.
 *
 * As pontes `legado/*` (MovelApp) e `legado/nfweb/*` nasceram com "data para
 * morrer". Para que essa data chegue, alguém precisa saber **quais** dos 29
 * endpoints ainda são chamados, por qual revenda e por qual versão de APK.
 *
 * Sem o número, remover é apostar: o MovelApp está em `targetSdk 28` e não
 * publica na Play Store, então um endpoint removido cedo demais aparece como
 * venda travada em campo, sem correção rápida do outro lado.
 *
 * ## Por que agregado por dia
 *
 * O app faz polling de `getPedidosPendentes`. Uma linha por chamada cresceria
 * mais rápido que o pedido que ela acompanha. O agregado por (ponte, endpoint,
 * empresa, dia) responde as perguntas que decidem a remoção e cabe num índice.
 *
 * ## Por que nunca lança
 *
 * Medição não pode derrubar o que mede. Uma falha ao contar viraria erro numa
 * venda em campo — e o preço de perder uma linha de estatística é
 * incomparavelmente menor que o de perder a venda.
 */
class UsoDaPonte
{
    /**
     * Registra uma chamada.
     *
     * @param  string  $ponte  `movelapp` ou `nfweb`
     * @param  string  $endpoint  o nome no dialeto do legado (`getPedidosPendentes`)
     * @param  bool  $recusada  a resposta foi recusa de REGRA (o `OPS` do legado)
     */
    public function registrar(
        string $ponte,
        string $endpoint,
        ?int $empresaId = null,
        bool $recusada = false,
        ?string $versaoApp = null,
    ): void {
        try {
            $dia = now()->toDateString();

            // `empresa_id` NULO é o caso normal aqui — `login` e `init`
            // acontecem antes de haver tenant resolvido, e são justamente as
            // chamadas que provam que um app segue vivo em campo.
            //
            // `whereNull` explícito, e não `where('empresa_id', $empresaId)`
            // com nulo. O query builder do Laravel até converte o segundo em
            // `IS NULL` sozinho — conferi —, mas o que precisa sobreviver aqui
            // é a leitura: quem trocar isto por um `updateOrInsert` com a chave
            // montada em array, ou por SQL escrito à mão, cai no `= NULL` que
            // nunca casa, e o registrador passa a inserir uma linha nova a cada
            // chamada.
            //
            // O sintoma seria cruel: a soma total continuaria certa, e só o
            // "quantas revendas" e o "uma linha por dia" estariam destruídos.
            $existente = DB::table('ponte_usos')
                ->where('ponte', $ponte)
                ->where('endpoint', $endpoint)
                ->where('dia', $dia)
                ->when(
                    $empresaId === null,
                    fn ($q) => $q->whereNull('empresa_id'),
                    fn ($q) => $q->where('empresa_id', $empresaId),
                )
                ->first();

            if ($existente === null) {
                DB::table('ponte_usos')->insert([
                    'ponte' => $ponte,
                    'endpoint' => $endpoint,
                    'empresa_id' => $empresaId,
                    'tenant_account_id' => $this->tenantDa($empresaId),
                    'dia' => $dia,
                    'chamadas' => 1,
                    'recusas' => $recusada ? 1 : 0,
                    'ultima_versao_app' => $versaoApp,
                    'ultima_chamada_em' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return;
            }

            // `chamadas + 1` no banco, não leitura-soma-escrita: vários
            // vendedores em campo batem o mesmo endpoint ao mesmo tempo, e a
            // versão em PHP perderia contagem sem ninguém notar.
            DB::table('ponte_usos')->where('id', $existente->id)->update([
                'chamadas' => DB::raw('chamadas + 1'),
                'recusas' => DB::raw('recusas + '.($recusada ? 1 : 0)),
                'ultima_versao_app' => $this->versaoMaisNova($existente->ultima_versao_app, $versaoApp),
                'ultima_chamada_em' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable) {
            // Sem tabela (banco antigo) ou falha de escrita: a ponte segue.
            // Medição não derruba o que mede.
        }
    }

    /**
     * O tenant vem da EMPRESA.
     *
     * Coluna criada e deixada nula é o defeito que esta base já encontrou duas
     * vezes: a tabela parece resolvida e não responde pergunta nenhuma.
     */
    private function tenantDa(?int $empresaId): ?int
    {
        if ($empresaId === null) {
            return null;
        }

        $valor = DB::table('empresas')->where('id', $empresaId)->value('tenant_account_id');

        return $valor === null ? null : (int) $valor;
    }

    /**
     * A maior das duas versões, comparadas como versão e não como string.
     *
     * `'1.10' > '1.9'` é falso em comparação de string, e guardar `1.9` como
     * "mais nova" inverteria a decisão de remoção: pareceria que só APK velho
     * chama o endpoint, quando o novo também chama.
     */
    private function versaoMaisNova(?string $atual, ?string $nova): ?string
    {
        if ($nova === null || $nova === '') {
            return $atual;
        }

        if ($atual === null || $atual === '') {
            return $nova;
        }

        return version_compare($nova, $atual, '>') ? $nova : $atual;
    }

    /**
     * O que responde "posso remover este endpoint?".
     *
     * Devolve, por endpoint: chamadas no período, quantas revendas distintas
     * chamaram, quantas chamadas vieram sem tenant resolvido, e a última
     * chamada. Endpoint que não aparece na lista teve **zero** chamadas no
     * recorte — e é esse o fato que autoriza a remoção.
     *
     * As duas contagens de origem andam juntas de propósito: `login` e `init`
     * acontecem antes de haver tenant, e um relatório que só mostrasse
     * "revendas" os exibiria como zero — fazendo o endpoint mais vivo da ponte
     * parecer o mais morto, que é o pior erro possível aqui.
     *
     * @return list<array<string,mixed>>
     */
    public function resumo(string $inicio, string $fim, ?string $ponte = null): array
    {
        try {
            return DB::table('ponte_usos')
                ->when($ponte !== null, fn ($q) => $q->where('ponte', $ponte))
                // `whereBetween` em coluna `date` é seguro; o que quebra é em
                // datetime, onde o último dia se perde na comparação de string.
                ->whereBetween('dia', [$inicio, $fim])
                ->selectRaw(
                    'ponte, endpoint,'.
                    ' sum(chamadas) as chamadas,'.
                    ' sum(recusas) as recusas,'.
                    // `count(distinct)` IGNORA nulos em SQL, e nulo aqui é o
                    // caso normal (`login`/`init`, antes de resolver tenant).
                    // Sem esta soma, `login` apareceria como
                    // "5000 chamadas, 0 revendas" — leitura contraditória que
                    // faria o endpoint mais vivo da ponte parecer o mais morto.
                    ' count(distinct empresa_id) as revendas_identificadas,'.
                    ' sum(case when empresa_id is null then chamadas else 0 end) as chamadas_sem_tenant,'.
                    ' max(ultima_chamada_em) as ultima_chamada_em'
                )
                ->groupBy('ponte', 'endpoint')
                ->orderByDesc('chamadas')
                ->get()
                ->map(fn ($l) => (array) $l)
                ->all();
        } catch (\Throwable $e) {
            // Fail-closed, ao contrário de `registrar()`.
            //
            // As duas direções não são simétricas: perder uma linha de
            // estatística é barato, mas devolver lista vazia aqui significa
            // "zero chamadas em todos os endpoints" — a exata saída que
            // AUTORIZA remover as 29 rotas de ponte. Uma falha de leitura
            // viraria permissão para desligar os apps em campo.
            throw new \RuntimeException(
                'Não consegui ler o uso das pontes: '.$e->getMessage().
                '. Sem essa leitura não dá para afirmar que endpoint nenhum é usado.',
                previous: $e,
            );
        }
    }
}
