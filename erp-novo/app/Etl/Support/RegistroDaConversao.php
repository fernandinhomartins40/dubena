<?php

namespace App\Etl\Support;

use Illuminate\Support\Facades\DB;

/**
 * F7 — a execução da conversão deixa registro.
 *
 * Antes, tudo vivia no terminal de quem rodou: quando a carga terminava, não
 * sobrava resposta para "que execução foi essa", "de onde veio esta linha" nem
 * "o que foi descartado e por quê".
 *
 * O terceiro é o que mais dói num cutover. A conferência acontece dias depois
 * — *"faltam 40 clientes"* —, e sem quarentena a única saída é rodar tudo de
 * novo e comparar. Com o sistema já em produção isso é impossível, e com razão:
 * a trava pós-cutover existe justamente para impedir a recarga que sobrescreve
 * trabalho real.
 *
 * ## Nunca derruba a carga
 *
 * Toda escrita aqui é protegida: se o registro falhar, a **conversão continua**.
 * Instrumentação que interrompe o processo que ela observa inverte a prioridade
 * — o dado migrado vale mais que o registro de que ele migrou.
 */
class RegistroDaConversao
{
    private ?int $execucaoId = null;

    /** Abre a execução e devolve o id (ou null se não deu para registrar). */
    public function iniciar(?string $alvo, bool $dryRun, bool $comInvariantes, ?int $userId = null): ?int
    {
        try {
            $this->execucaoId = (int) DB::table('conversao_execucoes')->insertGetId([
                'user_id' => $userId,
                'situacao' => 'EM_ANDAMENTO',
                'alvo' => $alvo,
                'dry_run' => $dryRun,
                'com_invariantes' => $comInvariantes,
                'iniciada_em' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable) {
            // Sem tabela (banco antigo) ou sem permissão: a carga segue.
            $this->execucaoId = null;
        }

        return $this->execucaoId;
    }

    /**
     * Fecha a execução, com CAS (F7-02).
     *
     * `INTERROMPIDA` é um estado de verdade: um ETL de 3 GB morre por OOM ou por
     * sessão fechada, e o registro precisa dizer isso em vez de ficar "em
     * andamento" para sempre — linha eternamente aberta é indistinguível de
     * carga rodando agora.
     *
     * @param  string  $situacao  um caso de `SituacaoDaConversao`; qualquer outro
     *                            valor LANÇA (é bug, não infortúnio de infra)
     * @return bool true se ESTA chamada encerrou. `false` significa que outro
     *              processo chegou antes — e quem já encerrou, encerrou.
     */
    public function encerrar(string $situacao, string $resumo = '', array $totais = []): bool
    {
        if ($this->execucaoId === null) {
            return false;
        }

        // Estado inválido é DEFEITO DE CÓDIGO, e por isso lança em vez de ser
        // engolido pelo `catch` de baixo.
        //
        // A regra "registro não derruba carga" existe para falha de
        // INFRAESTRUTURA — banco fora, tabela ausente. Um `'CONCLUÍDA'` com
        // acento não é infortúnio: é bug, e gravá-lo em silêncio deixaria a
        // execução invisível para o gate de cutover, que procura `CONCLUIDA`
        // exato. Some do relatório sem sumir do banco — o pior desfecho.
        $destino = SituacaoDaConversao::tryFrom($situacao);

        if ($destino === null) {
            throw new \InvalidArgumentException(
                "Situação de conversão desconhecida: '{$situacao}'. ".
                'Use um caso de '.SituacaoDaConversao::class.'.',
            );
        }

        if (! $destino->final()) {
            throw new \InvalidArgumentException(
                "encerrar() só aceita estado final; '{$situacao}' não encerra nada.",
            );
        }

        try {
            // CAS: só encerra quem ainda está EM_ANDAMENTO.
            //
            // Sem o `where` da situação, o `update` era incondicional e o
            // ÚLTIMO a escrever venceria. Dois desfechos ruins que isso abria:
            //
            //  - um processo que morreu e foi marcado INTERROMPIDA por um
            //    supervisor voltava a CONCLUIDA se a thread agonizante ainda
            //    conseguisse escrever;
            //  - uma segunda execução do mesmo id sobrescreveria o desfecho da
            //    primeira, apagando a evidência de que houve falha.
            //
            // Quem já encerrou, encerrou. A condição é resolvida pelo BANCO,
            // não em PHP — verificação em PHP perderia a corrida que ela mesma
            // deveria arbitrar.
            $linhas = DB::table('conversao_execucoes')
                ->where('id', $this->execucaoId)
                ->where('situacao', SituacaoDaConversao::EM_ANDAMENTO->value)
                ->update([
                    'situacao' => $destino->value,
                    'encerrada_em' => now(),
                    'resumo' => $resumo !== '' ? mb_substr($resumo, 0, 60000) : null,
                    'linhas_lidas' => $totais['lidas'] ?? 0,
                    'linhas_gravadas' => $totais['gravadas'] ?? 0,
                    'linhas_quarentena' => $totais['quarentena'] ?? 0,
                    'updated_at' => now(),
                ]);

            return $linhas === 1;
        } catch (\Throwable) {
            // idem: registro não derruba carga.
            //
            // ⚠️ Este `false` e o de cima significam coisas DIFERENTES: aqui a
            // escrita falhou e a execução ficou eternamente `EM_ANDAMENTO`; lá
            // em cima outro processo encerrou primeiro, que é o desfecho
            // esperado.
            //
            // Nenhum chamador usa o retorno hoje, então não há defeito ativo.
            // Quem passar a usá-lo para decidir algo precisa separar os dois —
            // e o lugar de separar é aqui, devolvendo um enum ou lançando, não
            // no chamador tentando adivinhar.
            return false;
        }
    }

    /**
     * Registra de onde veio uma linha.
     *
     * Upsert por (origem, entidade, pk): reprocessar atualiza em vez de
     * duplicar. A unicidade é garantida pelo índice do banco — duas execuções
     * simultâneas passariam por qualquer verificação feita aqui em PHP.
     */
    public function linhagem(
        string $sistemaOrigem,
        string $entidade,
        string $pkOrigem,
        ?string $tabelaDestino = null,
        ?int $idDestino = null,
        ?string $versaoTransformador = null,
    ): void {
        if ($this->execucaoId === null) {
            return;
        }

        try {
            DB::table('conversao_linhagem')->updateOrInsert(
                [
                    'sistema_origem' => $sistemaOrigem,
                    'entidade' => $entidade,
                    'pk_origem' => $pkOrigem,
                ],
                [
                    'conversao_execucao_id' => $this->execucaoId,
                    'tabela_destino' => $tabelaDestino,
                    'id_destino' => $idDestino,
                    'versao_transformador' => $versaoTransformador,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        } catch (\Throwable) {
        }
    }

    /**
     * Põe uma linha em quarentena.
     *
     * O `payload` bruto vai junto de propósito: uma quarentena que diz que algo
     * foi descartado sem permitir recuperar o dado responde metade da pergunta.
     * Com ele, alguém decide caso a caso e reprocessa o que for legítimo.
     */
    public function quarentena(
        string $sistemaOrigem,
        string $entidade,
        ?string $pkOrigem,
        string $motivo,
        ?string $detalhe = null,
        ?array $payload = null,
    ): void {
        if ($this->execucaoId === null) {
            return;
        }

        try {
            DB::table('conversao_quarentena')->insert([
                'conversao_execucao_id' => $this->execucaoId,
                'sistema_origem' => $sistemaOrigem,
                'entidade' => $entidade,
                'pk_origem' => $pkOrigem,
                'motivo' => $motivo,
                'detalhe' => $detalhe,
                'payload' => $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
                'decisao' => 'PENDENTE',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable) {
        }
    }

    public function execucaoId(): ?int
    {
        return $this->execucaoId;
    }
}
