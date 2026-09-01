<?php

namespace App\Etl\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * F7-03 — retrato da fonte legada no instante da leitura.
 *
 * ## A pergunta que isto responde
 *
 * > *A fonte mudou entre o ensaio e o cutover?*
 *
 * Sem resposta a essa pergunta, um ensaio bem-sucedido na sexta não diz nada
 * sobre a virada no domingo: alguém pode ter editado 300 clientes no legado no
 * sábado, e a conversão os traria com o valor novo sem que ninguém percebesse
 * que o ensaio validou outra coisa.
 *
 * ## O que este snapshot NÃO é
 *
 * Não é cópia da fonte. A tarefa F7-03 pede também **LOB integral** e *"carga
 * nova nunca derruba a última boa"*, e as duas pressupõem área de staging — um
 * lugar nosso para onde copiar a fonte bruta. Este ETL lê a conexão `legado` ao
 * vivo e não tem esse lugar.
 *
 * Então: manifesto nominal, schema, hashes, contagens e watermark, sim; cópia
 * dos binários, não. O campo `lob_integral` fica **falso** de propósito, para
 * que o gate de cutover consiga reprovar enquanto a decisão de staging não for
 * tomada. Campo ausente seria lido como "não se aplica"; é o oposto.
 *
 * ## Por que hash por tabela
 *
 * Um hash único do banco responde "mudou?" e nada mais. Por tabela, responde
 * **onde** — que é o que separa a mudança inócua (uma tabela de log crescendo)
 * da fatal (cliente editado depois do ensaio).
 */
class SnapshotDaFonte
{
    /**
     * Tira o retrato de uma tabela da fonte.
     *
     * @param  string|null  $watermarkColuna  coluna de corte (data de alteração, id);
     *                                        nulo quando a tabela não tem uma
     * @return int|null o id do snapshot, ou nulo se a leitura falhou
     */
    public function registrar(
        ConnectionInterface $fonte,
        string $sistemaOrigem,
        string $tabela,
        ?string $watermarkColuna = null,
        ?int $execucaoId = null,
    ): ?int {
        try {
            $colunas = $this->colunasDe($fonte, $tabela);

            // Falha ao ler o schema não é detalhe: sem o manifesto nominal, o
            // snapshot não detecta coluna que sumiu — que é o defeito mais
            // silencioso da conversão (o migrador lê `null`, grava `null`, e
            // nada acusa).
            if ($colunas === []) {
                return null;
            }

            $linhas = (int) $fonte->table($tabela)->count();

            $marca = $watermarkColuna !== null
                ? $fonte->table($tabela)->max($watermarkColuna)
                : null;

            return (int) DB::table('conversao_snapshots')->insertGetId([
                'conversao_execucao_id' => $execucaoId,
                'sistema_origem' => $sistemaOrigem,
                'tabela' => $tabela,
                'colunas' => json_encode($colunas),
                'linhas' => $linhas,
                'hash_conteudo' => $this->hashDe($fonte, $tabela, $colunas),
                'watermark_coluna' => $watermarkColuna,
                'watermark_valor' => $marca === null ? null : mb_substr((string) $marca, 0, 120),

                // Falso até existir staging. Ver o cabeçalho da classe.
                'lob_integral' => false,

                'lido_em' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable) {
            // Fonte indisponível não pode derrubar quem chamou; devolve nulo, e
            // quem compara trata "sem snapshot" como motivo para NÃO seguir.
            return null;
        }
    }

    /**
     * O manifesto nominal: nome e tipo de cada coluna, em ordem.
     *
     * Ordem importa. Uma coluna trocada de posição no legado quebraria qualquer
     * leitura posicional, e o diff precisa mostrar isso como mudança.
     *
     * @return array<string, string>
     */
    private function colunasDe(ConnectionInterface $fonte, string $tabela): array
    {
        $saida = [];

        foreach ($fonte->getSchemaBuilder()->getColumns($tabela) as $coluna) {
            $saida[(string) $coluna['name']] = (string) ($coluna['type'] ?? '');
        }

        return $saida;
    }

    /**
     * Hash do conteúdo da tabela.
     *
     * Somando o hash de cada linha em vez de concatenar tudo: a soma é
     * **independente da ordem**, e a fonte legada não garante ordem estável
     * entre leituras. Um hash sensível à ordem acusaria mudança onde não houve —
     * e um alarme que dispara sozinho é um alarme que se aprende a ignorar,
     * exatamente quando ele estiver certo.
     *
     * @param  array<string, string>  $colunas
     */
    private function hashDe(ConnectionInterface $fonte, string $tabela, array $colunas): ?string
    {
        try {
            $nomes = array_keys($colunas);
            $acumulado = 0;

            $fonte->table($tabela)->orderBy($nomes[0])->chunk(500, function ($linhas) use (&$acumulado, $nomes) {
                foreach ($linhas as $linha) {
                    $texto = '';

                    foreach ($nomes as $n) {
                        // O separador evita a colisão em que `['ab','c']` e
                        // `['a','bc']` produziriam o mesmo texto.
                        $texto .= '|'.(string) (((array) $linha)[$n] ?? '');
                    }

                    // 8 hex é espaço suficiente para a soma não saturar em
                    // tabelas de milhões de linhas, e detecta a alteração de
                    // uma única linha.
                    $acumulado += hexdec(substr(hash('sha256', $texto), 0, 8));
                }
            });

            return hash('sha256', (string) $acumulado);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Há dois retratos para comparar?
     *
     * Existe separado de `diferencas()` porque as duas devolvem lista vazia em
     * situações opostas: *nada mudou* e *não havia com o que comparar*. Quem
     * decide um cutover precisa distinguir as duas — tratá-las igual faria a
     * primeira leitura, a única que por definição não tem anterior, passar por
     * confirmação de estabilidade.
     */
    public function temComparacao(string $sistemaOrigem, string $tabela): bool
    {
        return DB::table('conversao_snapshots')
            ->where('sistema_origem', $sistemaOrigem)
            ->where('tabela', $tabela)
            ->count() >= 2;
    }

    /**
     * O que mudou entre dois retratos da mesma tabela.
     *
     * Devolve lista vazia quando nada mudou. Quem chama trata **ausência de
     * snapshot** como impedimento, nunca como "não mudou": não ter medido não é
     * prova de estabilidade, e confundir os dois é o que faria o gate liberar
     * um cutover às cegas.
     *
     * @return list<string>
     */
    public function diferencas(string $sistemaOrigem, string $tabela): array
    {
        $dois = DB::table('conversao_snapshots')
            ->where('sistema_origem', $sistemaOrigem)
            ->where('tabela', $tabela)
            ->orderByDesc('lido_em')
            ->orderByDesc('id')
            ->limit(2)
            ->get();

        if ($dois->count() < 2) {
            return [];
        }

        [$novo, $velho] = [$dois[0], $dois[1]];
        $achados = [];

        if ((int) $novo->linhas !== (int) $velho->linhas) {
            $achados[] = "{$tabela}: linhas {$velho->linhas} → {$novo->linhas}";
        }

        // Hash ausente é ausência de MEDIÇÃO, não igualdade de conteúdo. Sem
        // esta guarda, dois retratos que falharam ao hashear dariam
        // `null !== null` = falso, e o comparador afirmaria "conteúdo igual"
        // sobre uma tabela que ninguém conseguiu ler — liberando o cutover com
        // base numa comparação que não aconteceu.
        if ($novo->hash_conteudo === null || $velho->hash_conteudo === null) {
            $achados[] = "{$tabela}: conteúdo NÃO verificado (hash ausente em um dos retratos)";
        } elseif ($novo->hash_conteudo !== $velho->hash_conteudo) {
            $achados[] = "{$tabela}: conteúdo alterado (hash diferente)";
        }

        if ($novo->watermark_valor !== $velho->watermark_valor) {
            $achados[] = "{$tabela}: watermark {$velho->watermark_valor} → {$novo->watermark_valor}";
        }

        // Coluna que some é o defeito mais silencioso: o migrador lê `null` e
        // grava `null`, sem erro nenhum. Por isso a comparação é nominal.
        $colNovo = (array) json_decode((string) $novo->colunas, true);
        $colVelho = (array) json_decode((string) $velho->colunas, true);

        foreach (array_diff(array_keys($colVelho), array_keys($colNovo)) as $sumiu) {
            $achados[] = "{$tabela}: coluna '{$sumiu}' SUMIU da fonte";
        }

        foreach (array_diff(array_keys($colNovo), array_keys($colVelho)) as $surgiu) {
            $achados[] = "{$tabela}: coluna '{$surgiu}' é nova na fonte";
        }

        foreach ($colNovo as $nome => $tipo) {
            if (isset($colVelho[$nome]) && $colVelho[$nome] !== $tipo) {
                $achados[] = "{$tabela}: coluna '{$nome}' mudou de tipo ({$colVelho[$nome]} → {$tipo})";
            }
        }

        return $achados;
    }
}
