<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * F7-12 — o pós-check do cutover.
 *
 * ## Por que os portões que já existem não servem aqui
 *
 * | Comando | Pergunta que responde | Quando |
 * |---|---|---|
 * | `cutover:check` | os dados batem com a origem? | **antes** — precisa da conexão legada |
 * | `golive:check` | a configuração está pronta? | **antes** — config, não operação |
 * | este | o sistema está SADIO agora que recebeu o tráfego? | **depois** |
 *
 * A distinção não é acadêmica. Depois do switch a conexão legada pode nem
 * existir mais, e ninguém tem como reexecutar as invariantes de comparação.
 * A pergunta muda: não é mais *"a carga trouxe tudo?"* e sim *"a operação
 * consegue trabalhar?"*.
 *
 * ## O que se mede depois do switch
 *
 * As coisas que, quebradas, param a revenda dentro de minutos — e que só se
 * manifestam com tráfego real:
 *
 *  - **sequências atrás do maior id.** É o defeito clássico de carga por
 *    `insert` com id explícito: a sequence continua em 1, e o primeiro pedido
 *    novo colide com um migrado. A venda simplesmente não fecha;
 *  - **conversão sem desfecho registrado.** Execução eternamente `EM_ANDAMENTO`
 *    é indistinguível de carga rodando agora — alguém vai esperar por um
 *    processo morto (F7-02);
 *  - **quarentena pendente.** Caso não resolvido é dado que não entrou, e
 *    ninguém sabe qual;
 *  - **fila e agendador vivos.** Sem worker, nota não é emitida e boleto não é
 *    registrado — e o sintoma aparece só quando o cliente cobra;
 *  - **empresa sem tenant.** Fica invisível para a RLS: a revenda existe no
 *    banco e não enxerga o próprio dado.
 *
 * ## Read-only, sempre
 *
 * O pós-check roda com o sistema no ar. Nada aqui escreve — um diagnóstico que
 * altera o que diagnostica é pior que diagnóstico nenhum.
 */
class CutoverPosCheck extends Command
{
    protected $signature = 'cutover:pos-check
        {--minutos=60 : janela recente considerada para atividade}';

    protected $description = 'Verifica a saúde do sistema DEPOIS do switch do cutover (F7-12). Não altera nada.';

    private int $falhas = 0;

    private int $avisos = 0;

    public function handle(): int
    {
        $this->info('== cutover:pos-check — saúde depois do switch ==');
        $this->newLine();

        $this->verificarSequencias();
        $this->verificarConversao();
        $this->verificarTenancy();
        $this->verificarInfraAssincrona();

        $this->newLine();
        $this->line("Resultado: {$this->falhas} FALHA(s), {$this->avisos} aviso(s).");

        if ($this->falhas > 0) {
            $this->error('O sistema NÃO está sadio depois do switch — considere o rollback.');

            return self::FAILURE;
        }

        $this->info($this->avisos > 0
            ? 'Sadio, com avisos que valem conferir.'
            : 'Sadio.');

        return self::SUCCESS;
    }

    /**
     * O defeito que trava a primeira venda.
     *
     * Carga por `insert` com id explícito não avança a sequence: ela continua em
     * 1 enquanto a tabela tem 40 mil linhas. O primeiro pedido novo colide com
     * um migrado, e o erro que aparece na tela é uma violação de chave — que
     * ninguém associa ao cutover.
     *
     * É verificação de PLATAFORMA (varre o catálogo), então roda como owner ou
     * não roda: `information_schema` só mostra o que a role possui.
     */
    private function verificarSequencias(): void
    {
        $this->line('Sequências');

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->item('sequências conferidas', false, 'só verificável em PostgreSQL', aviso: true);

            return;
        }

        try {
            // `pg_get_serial_sequence` em vez de montar o nome por convenção:
            // uma sequence renomeada existe e o nome convencionado não, e a
            // verificação passaria sem ter olhado nada.
            $linhas = DB::select(<<<'SQL'
                SELECT c.relname AS tabela,
                       pg_get_serial_sequence(quote_ident(c.relname), 'id') AS seq
                  FROM pg_class c
                  JOIN pg_namespace n ON n.oid = c.relnamespace
                 WHERE n.nspname = 'public'
                   AND c.relkind = 'r'
                   AND EXISTS (
                         SELECT 1 FROM information_schema.columns col
                          WHERE col.table_schema = 'public'
                            AND col.table_name = c.relname
                            AND col.column_name = 'id'
                       )
                 ORDER BY c.relname
            SQL);

            $comSequence = array_values(array_filter($linhas, fn ($l) => $l->seq !== null));

            if ($comSequence === []) {
                // Zero tabelas verificadas não é "tudo certo" — é verificação
                // que não aconteceu. Mesmo defeito do registry vazio que
                // imprimia "ETL concluído".
                $this->item('sequências verificadas', false, 'nenhuma tabela com sequence encontrada — a varredura não enxergou o schema');

                return;
            }

            $atrasadas = [];

            foreach ($comSequence as $l) {
                $r = DB::selectOne(
                    "SELECT (SELECT max(id) FROM {$l->tabela}) AS maior, last_value, is_called FROM {$l->seq}",
                );

                if ($r === null || $r->maior === null) {
                    continue;   // tabela vazia: nada a comparar
                }

                // `is_called = false` significa que `last_value` ainda será
                // ENTREGUE, não que já foi usado — comparar sem isso acusaria
                // atraso onde não há.
                $proximo = $r->is_called ? (int) $r->last_value + 1 : (int) $r->last_value;

                if ($proximo <= (int) $r->maior) {
                    $atrasadas[] = "{$l->tabela} (próximo {$proximo} ≤ maior id {$r->maior})";
                }
            }

            $this->item(
                count($comSequence).' sequência(s) à frente do maior id',
                $atrasadas === [],
                $atrasadas === [] ? null : implode('; ', array_slice($atrasadas, 0, 8)),
            );
        } catch (\Throwable $e) {
            $this->item('sequências conferidas', false, $e->getMessage());
        }
    }

    /**
     * A conversão terminou, e terminou bem?
     *
     * Depois do switch não dá para reexecutar as invariantes de comparação — a
     * origem pode não existir mais. O que resta é o registro que a própria
     * conversão deixou (F7).
     */
    private function verificarConversao(): void
    {
        $this->line('Conversão');

        try {
            $abertas = DB::table('conversao_execucoes')->where('situacao', 'EM_ANDAMENTO')->count();

            $this->item(
                'nenhuma execução eternamente EM_ANDAMENTO',
                $abertas === 0,
                $abertas === 0 ? null : "{$abertas} execução(ões) sem desfecho — indistinguível de carga rodando agora",
            );

            $ultima = DB::table('conversao_execucoes')
                ->where('dry_run', false)
                ->orderByDesc('id')
                ->first(['id', 'situacao']);

            $this->item(
                'a última carga real terminou CONCLUIDA',
                $ultima !== null && $ultima->situacao === 'CONCLUIDA',
                $ultima === null
                    ? 'nenhuma execução real registrada — o cutover rodou sem registro?'
                    : "execução #{$ultima->id} terminou {$ultima->situacao}",
            );

            $pendentes = DB::table('conversao_quarentena')->where('decisao', 'PENDENTE')->count();

            $this->item(
                'quarentena sem casos pendentes',
                $pendentes === 0,
                $pendentes === 0 ? null : "{$pendentes} caso(s) não resolvidos — é dado que não entrou",
            );
        } catch (\Throwable $e) {
            // Não conseguir LER não é o mesmo que estar limpo. Falha, não passa.
            $this->item('registro da conversão legível', false, $e->getMessage());
        }
    }

    /**
     * Empresa sem tenant fica invisível para a RLS.
     *
     * `app_tenant_can_read` compara o `tenant_account_id` DA LINHA com o do
     * envelope: nulo nunca casa. A revenda existe no banco, faz login, e não
     * enxerga o próprio dado — o suporte recebe "sumiu tudo" e não há erro em
     * log nenhum.
     *
     * ## Por que AVISO, e não falha
     *
     * A coluna é aditiva (`2026_08_29_000300`) e quem a preenche é a conversão.
     * Num banco que ainda não converteu — desenvolvimento, uma instalação nova —
     * nulo é o estado **normal**, e reprovar por isso faria o comando reprovar
     * sempre fora do cutover: um portão que sempre reprova é um portão que se
     * aprende a ignorar, justamente quando ele estiver certo.
     *
     * Quem trata isso como bloqueio é o `golive:check`, que verifica prontidão.
     * Aqui a informação é o que importa: se apareceu depois do switch, alguém
     * precisa saber antes de o cliente ligar.
     */
    private function verificarTenancy(): void
    {
        $this->line('Tenancy');

        try {
            $total = DB::table('empresas')->count();

            // Zero empresas passaria por "nenhuma sem tenant" — o vazio
            // satisfaz a condição sem satisfazer a intenção. Isso SIM é falha:
            // um sistema recém-virado sem empresa nenhuma não tem o que operar.
            $this->item(
                'há empresa cadastrada',
                $total > 0,
                $total > 0 ? null : 'nenhuma empresa cadastrada — o sistema não tem o que operar',
            );

            if ($total === 0) {
                return;
            }

            $semTenant = DB::table('empresas')->whereNull('tenant_account_id')->count();

            $this->item(
                'toda empresa tem tenant',
                $semTenant === 0,
                $semTenant > 0 ? "{$semTenant} de {$total} sem tenant: ficam invisíveis para a RLS" : null,
                aviso: true,
            );
        } catch (\Throwable $e) {
            $this->item('tenancy verificável', false, $e->getMessage());
        }
    }

    /**
     * Sem worker, nota não sai e boleto não é registrado.
     *
     * O sintoma é cruel: a tela diz "pedido criado" e a nota fica na fila. O
     * cliente descobre dias depois, e a revenda descobre pelo cliente.
     */
    private function verificarInfraAssincrona(): void
    {
        $this->line('Fila e agendador');

        $minutos = max(1, (int) $this->option('minutos'));

        try {
            $falhados = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subMinutes($minutos))
                ->count();

            $this->item(
                "nenhum job falhado nos últimos {$minutos} min",
                $falhados === 0,
                $falhados === 0 ? null : "{$falhados} job(s) falharam depois do switch",
            );

            $presos = DB::table('jobs')
                ->where('created_at', '<=', now()->subMinutes($minutos)->timestamp)
                ->count();

            // Fila acumulando é AVISO, não falha: pode ser pico legítimo logo
            // depois da virada. Falha seria alarme falso na hora em que ninguém
            // pode perder tempo com alarme falso.
            $this->item(
                'fila sem job preso',
                $presos === 0,
                $presos === 0 ? null : "{$presos} job(s) na fila há mais de {$minutos} min — worker parado?",
                aviso: true,
            );
        } catch (\Throwable $e) {
            $this->item('fila verificável', false, $e->getMessage());
        }
    }

    private function item(string $label, bool $ok, ?string $detalhe = null, bool $aviso = false): void
    {
        if ($ok) {
            $this->line("  <fg=green>PASS</> {$label}");

            return;
        }

        if ($aviso) {
            $this->warn("  WARN {$label}".($detalhe ? " — {$detalhe}" : ''));
            $this->avisos++;

            return;
        }

        $this->error("  FAIL {$label}".($detalhe ? " — {$detalhe}" : ''));
        $this->falhas++;
    }
}
