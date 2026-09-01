<?php

namespace App\Console\Commands;

use App\Domain\Saas\TransformationFreeze;
use App\Domain\Saas\TransformationFrozenException;
use App\Etl\MigratorRegistry;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\RegistroDaConversao;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Runner do ETL (migração legado → novo).
 *
 *   php artisan etl:run                  # roda todos os migrators (ordem de dependência)
 *   php artisan etl:run estados          # roda só um
 *   php artisan etl:run --dry-run        # simula (não grava)
 *   php artisan etl:run --check          # roda e VALIDA invariantes (portão do cutover)
 *
 * É o esqueleto do Bloco 2/3 do plano: carga + validação de invariantes.
 */
class EtlRun extends Command
{
    protected $signature = 'etl:run {migrator? : nome do migrador (vazio = todos)}
                                    {--dry-run : não grava, apenas simula}
                                    {--check : valida invariantes após a carga}
                                    {--eu-sei-o-que-estou-fazendo : libera a recarga DEPOIS do cutover (T6.6.5)}';

    protected $description = 'Executa a migração de dados do banco legado para o schema novo (ETL).';

    /**
     * A partir de quanto o descarte deixa de ser aviso e vira falha.
     *
     * 50% é folgado de propósito: há migrador que legitimamente descarta muito
     * (o espelho traz linhas órfãs do legado). O que este portão persegue não é
     * o descarte comum — é o caso em que o ETL perde quase tudo e mesmo assim
     * imprime "concluído", que foi o que aconteceu quando ele passou a ler o
     * destino sob RLS.
     *
     * Um limiar apertado viraria ruído, e portão que vira ruído é desligado.
     */
    private const LIMIAR_DESCARTE_PCT = 50;

    /**
     * F7-09 — exclusão mútua entre execuções.
     *
     * Duas horas é folga sobre a carga completa (o dump inteiro leva ~40 min) e
     * curto o bastante para não deixar o sistema travado se o processo morrer
     * por OOM sem liberar o lock.
     */
    private const LOCK_SEGUNDOS = 7200;

    public function handle(TransformationFreeze $freeze): int
    {
        // F7-09 — dois `etl:run` simultâneos não podem existir.
        //
        // O ETL escreve por upsert PRESERVANDO id (`PreservaIdsDoLegado`), então
        // duas execuções competem pelas mesmas linhas: a segunda sobrescreve o
        // que a primeira acabou de gravar, e nenhuma das duas falha. O resultado
        // é uma carga que parece bem-sucedida e tem estado misturado das duas.
        //
        // `Isolatable` do Laravel resolveria isso, mas só quando alguém passa
        // `--isolated` — e a proteção que depende de lembrar não protege. Aqui é
        // fail-closed: sem o lock, o comando não roda.
        //
        // `--dry-run` fica de fora: simular não grava, e travar a simulação
        // enquanto uma carga roda tiraria justamente a ferramenta de diagnóstico
        // de quem está acompanhando a carga.
        $lock = null;

        if (! $this->option('dry-run')) {
            $lock = Cache::lock('etl:run', self::LOCK_SEGUNDOS);

            if (! $lock->get()) {
                $this->error('Outra carga de ETL esta em andamento.');
                $this->line('Duas execucoes simultaneas competem pelas mesmas linhas (upsert por id) '
                    .'e produzem um estado misturado que nao acusa erro.');

                return self::FAILURE;
            }
        }

        try {
            return $this->executar($freeze);
        } finally {
            // `finally`: exceção no meio da carga não pode deixar o lock preso
            // pelas duas horas inteiras — a próxima tentativa ficaria bloqueada
            // sem motivo, e alguém acabaria removendo a trava à mão.
            $lock?->release();
        }
    }

    private function executar(TransformationFreeze $freeze): int
    {
        if (! $this->option('dry-run')) {
            try {
                $freeze->assertMigrationWritesAllowed();
            } catch (TransformationFrozenException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            if (app()->isProduction() && ! $this->option('check')) {
                $this->error('ETL de producao exige --check; carga sem invariantes e bloqueada.');

                return self::FAILURE;
            }
        }

        if (($erro = $this->travaPosCutover()) !== null) {
            $this->error($erro);

            return self::FAILURE;
        }

        // Carga de migração legítima: os migradores materializam mapas de FK do
        // dump inteiro (443 mil títulos, 475 mil parcelas, 442 mil rateios) —
        // o limite padrão de 512M do CLI derruba o financeiro no meio.
        ini_set('memory_limit', '3G');

        $ctx = new MigrationContext(dryRun: (bool) $this->option('dry-run'));
        $alvo = $this->argument('migrator');

        $migrators = MigratorRegistry::resolved();
        if ($alvo) {
            $migrators = array_values(array_filter($migrators, fn ($m) => $m->nome() === $alvo));
            if ($migrators === []) {
                $this->error("Migrador '{$alvo}' não encontrado.");

                return self::FAILURE;
            }
        }

        // F7-04A — lista vazia NUNCA produz sucesso.
        //
        // Sem esta trava, um registry vazio faz o loop não rodar, nada falhar, e
        // o comando imprimir "ETL concluído" com SUCCESS. Um script de deploy
        // leria isso como carga bem-sucedida, e a operação descobriria pelo
        // sistema vazio.
        //
        // É a mesma família do guardião que varria zero arquivos e passava: o
        // verde que não prova nada é pior que o vermelho, porque ninguém
        // investiga.
        if ($migrators === []) {
            $this->error('Nenhum migrador registrado — a carga nao teria o que fazer.');
            $this->line('Isto e falha, nao sucesso: um registry vazio significa configuracao quebrada.');

            return self::FAILURE;
        }

        // F7 — a execucao passa a deixar registro. O `iniciar` nunca derruba a
        // carga: se a tabela nao existir (banco antigo), devolve null e tudo
        // segue — instrumentacao que interrompe o processo que ela observa
        // inverte a prioridade.
        $registro = app(RegistroDaConversao::class);
        $registro->iniciar(
            $alvo ?: null,
            (bool) $this->option('dry-run'),
            (bool) $this->option('check'),
        );

        $falhou = false;
        /** @var list<string> $todosAvisos */
        $todosAvisos = [];
        /** @var list<string> $inconclusivas — invariantes que nao puderam ser verificadas. */
        $inconclusivas = [];

        // Descarte em massa: quanto foi lido e quanto foi jogado fora.
        //
        // Medido separado do resto porque descartar é a forma mais silenciosa de
        // perder dado: cada migrador trata "referência ausente" pulando a linha,
        // e o resultado sai como AVISO — o ETL imprime "concluído" tendo
        // descartado tudo.
        //
        // Aconteceu: com a RLS ligada e o ETL lendo pelo runtime restrito, o
        // dry-run descartou 507.006 linhas de estoque (100%) e 806.953 pedidos
        // (99,99%) e saiu com SUCESSO. A causa foi corrigida
        // (`MigrationContext::novo()`), mas a causa não é o ponto: o ponto é que
        // o ETL não pode ter um jeito de perder tudo e chamar isso de sucesso.
        $descartePorMigrator = [];

        // O total lido de TODAS as origens. Serve a uma pergunta que nenhum
        // aviso responde: a conversão chegou a ler alguma coisa?
        $totalLido = 0;

        /** @var array<string,int> quanto cada migrador leu — usado abaixo. */
        $lidoPorMigrator = [];

        foreach ($migrators as $m) {
            $this->info("→ {$m->nome()}".($ctx->dryRun ? ' (dry-run)' : ''));
            $res = $m->migrar($ctx);
            $this->line('  '.$res->resumo());
            foreach ($res->avisos as $aviso) {
                $this->warn('  ! '.$aviso);
                $todosAvisos[] = "{$m->nome()}: {$aviso}";
            }

            $totalLido += $res->lidos;
            $lidoPorMigrator[$m->nome()] = $res->lidos;

            if ($res->lidos > 0 && $res->pulados > 0) {
                $descartePorMigrator[$m->nome()] = [
                    'lidos' => $res->lidos,
                    'pulados' => $res->pulados,
                    'percentual' => (int) round($res->pulados * 100 / $res->lidos),
                ];
            }

            if ($this->option('check')) {
                foreach ($m->invariantes() as $inv) {
                    $r = $inv->verificar();

                    // F7-10 — tres desfechos, nao dois.
                    //
                    // Inconclusiva BLOQUEIA (nao verificado nunca e aprovacao),
                    // mas aparece distinta da reprovacao: as duas exigem acoes
                    // opostas. "Legado indisponivel" se resolve religando a
                    // conexao; "soma nao bate" se resolve investigando o dado.
                    // Misturar as duas manda quem opera para o lugar errado.
                    match (true) {
                        $r->naoVerificada() => $this->warn('  '.$r->resumo()),
                        $r->ok => $this->line('  '.$r->resumo()),
                        default => $this->error('  '.$r->resumo()),
                    };

                    if ($r->naoVerificada()) {
                        $inconclusivas[] = "{$m->nome()}: {$r->invariante}";
                    }

                    $falhou = $falhou || ! $r->ok;
                }
            }
        }

        // Bloco consolidado (T2.6): com 28 migrators, um aviso isolado no meio
        // do log rola para fora da tela e ninguém vê. O resumo final é o que
        // torna o aviso acionável.
        $this->newLine();
        $this->line('Avisos:');
        if ($todosAvisos === []) {
            $this->line('  (nenhum)');
        } else {
            foreach ($todosAvisos as $aviso) {
                $this->warn('  ! '.$aviso);
            }
        }

        if ($inconclusivas !== []) {
            $this->newLine();
            $this->warn('Invariantes INCONCLUSIVAS (nao verificadas):');
            foreach ($inconclusivas as $i) {
                $this->warn('  ? '.$i);
            }
        }

        if ($falhou) {
            $registro->encerrar('FALHOU', $inconclusivas !== []
                ? 'invariante reprovada ou inconclusiva'
                : 'invariante reprovada');
            $this->error('ETL concluído COM FALHA de invariante (portão NÃO liberado).');

            return self::FAILURE;
        }

        // ── A ORIGEM não pôde ser lida ──
        //
        // "Origem indisponível" é diferente de "origem vazia": a primeira
        // significa que a carga rodou com dado FALTANDO e não deve ser tratada
        // como sucesso por um script de deploy.
        //
        // O filtro procurava só por `leitura falhou`, e por isso deixava passar
        // os dois casos mais comuns: quando a TABELA não existe no espelho, os
        // migradores dizem "ausente no espelho"; quando o schema inteiro está
        // inacessível, dizem "legado indisponível".
        //
        // Reproduzi por acidente apontando o ETL para um banco onde a role não
        // tinha permissão no schema `legado`: os 27 migradores leram ZERO e o
        // comando saiu com sucesso. Num cutover isso entrega o sistema vazio
        // dizendo que deu certo.
        $frasesDeOrigemIlegivel = [
            'leitura falhou',
            'ausente no espelho',
            'legado indisponível',
            'legado indisponivel',
        ];

        // A frase sozinha não basta: um migrador pode dizer "legado indisponível"
        // e MESMO ASSIM ter lido — o `EstadosMigrator` cai num conjunto-semente
        // de 27 UFs, e isso é fallback funcionando, não origem ilegível.
        //
        // O que reprova é a combinação: avisou que não conseguiu ler E não leu
        // nada. Sem essa segunda parte, o portão bloquearia o próprio dry-run
        // que existe para diagnosticar — e portão que atrapalha o diagnóstico é
        // portão que se desliga.
        $indisponiveis = array_values(array_filter(
            $todosAvisos,
            function (string $a) use ($frasesDeOrigemIlegivel, $lidoPorMigrator): bool {
                $casa = false;

                foreach ($frasesDeOrigemIlegivel as $frase) {
                    if (str_contains($a, $frase)) {
                        $casa = true;

                        break;
                    }
                }

                if (! $casa) {
                    return false;
                }

                // O aviso vem prefixado com o nome do migrador ("estados: ...").
                $nome = str_contains($a, ':') ? trim(explode(':', $a, 2)[0]) : '';

                return ($lidoPorMigrator[$nome] ?? 0) === 0;
            },
        ));

        if ($indisponiveis !== []) {
            $registro->encerrar('FALHOU', implode(' | ', $indisponiveis));
            $this->error(sprintf(
                'ETL concluído com %d falha(s) de LEITURA da origem — a carga está incompleta.',
                count($indisponiveis),
            ));

            // A orientação vai aqui TAMBÉM, e não só no portão de origem vazia:
            // este filtro dispara primeiro, e é por ele que passa o caso mais
            // comum — schema errado ou role sem permissão no `legado`.
            $this->line('Confira a conexão do legado: banco, schema (`LEGADO_DB_SCHEMA`) e');
            $this->line('permissão da role sobre esse schema.');

            return self::FAILURE;
        }

        // ── A origem inteira veio VAZIA ──
        //
        // O filtro de frases acima depende de o migrador AVISAR. Mas um migrador
        // pode ler zero e não avisar nada — e aí zero linhas em tudo passa por
        // sucesso, que é o pior desfecho possível: o script de deploy recebe
        // exit 0 e o sistema entra em produção vazio.
        //
        // Nenhum filtro de texto pega isso. O que pega é a soma: uma conversão
        // que não leu UMA linha sequer não é uma conversão bem-sucedida — é uma
        // conversão que não aconteceu.
        //
        // Mesma família do registry vazio imprimindo "ETL concluído", e do teste
        // que varria zero arquivos e passava. Ausência precisa ser afirmada,
        // nunca inferida do vazio.
        if ($totalLido === 0) {
            $registro->encerrar('FALHOU', 'a origem não devolveu nenhuma linha');

            $this->newLine();
            $this->error('A ORIGEM NÃO DEVOLVEU NENHUMA LINHA — isto não é uma carga concluída.');
            $this->line('Confira a conexão do legado: banco, schema (`LEGADO_DB_SCHEMA`) e');
            $this->line('permissão da role. Ler zero e sair com sucesso entregaria o sistema vazio.');

            return self::FAILURE;
        }

        // ── Portão de DESCARTE ──
        //
        // Descartar em massa não é aviso, é falha. A diferença entre os dois é
        // quem precisa agir: aviso alguém lê depois; falha impede o deploy de
        // seguir tratando a carga como boa.
        //
        // O limiar é por MIGRADOR, não global: 90% de descarte em `pedidos` é
        // catastrófico e some numa média com dez migradores limpos.
        $graves = array_filter(
            $descartePorMigrator,
            fn (array $d) => $d['percentual'] >= self::LIMIAR_DESCARTE_PCT,
        );

        if ($graves !== []) {
            $detalhe = [];

            foreach ($graves as $nome => $d) {
                $detalhe[] = "{$nome}: {$d['pulados']}/{$d['lidos']} ({$d['percentual']}%)";
            }

            $registro->encerrar('FALHOU', 'descarte em massa: '.implode(' | ', $detalhe));

            $this->newLine();
            $this->error('DESCARTE EM MASSA — a carga NÃO pode ser tratada como concluída:');

            foreach ($detalhe as $d) {
                $this->error('  '.$d);
            }

            $this->newLine();
            $this->line('Descarte alto costuma ser referência que o ETL não ENXERGA, não que');
            $this->line('falta: confira se o destino está sendo lido pela conexão de owner');
            $this->line('(a RLS esconde tudo do runtime sem envelope de tenant).');

            return self::FAILURE;
        }

        $registro->encerrar('CONCLUIDA', implode(' | ', $todosAvisos));
        $this->info('ETL concluído.');

        return self::SUCCESS;
    }

    /**
     * T6.6.5 — impede a recarga depois que o sistema novo virou fonte da verdade.
     *
     * **O perigo concreto.** A recarga é idempotente por upsert preservando id
     * (`PreservaIdsDoLegado`): re-rodar o ETL **sobrescreve** qualquer linha de
     * id legado que tenha sido editada no sistema novo. Antes do cutover isso é
     * a característica desejada — é o que torna a recarga final possível. Depois
     * dele, é destruição silenciosa de trabalho real: o cliente cujo endereço o
     * atendente corrigiu ontem volta ao endereço errado do legado, sem erro,
     * sem log, sem ninguém perceber até a entrega falhar.
     *
     * **Como detecta.** Não por flag de configuração que alguém precisa lembrar
     * de ligar, mas por evidência no próprio banco: existe pedido criado no
     * sistema novo (id acima da faixa preservada do legado). Se a operação já
     * está gerando pedidos aqui, o cutover aconteceu — independentemente do que
     * qualquer arquivo diga.
     *
     * `--dry-run` passa livre: simular não grava nada.
     *
     * @return string|null mensagem de erro, ou null se pode prosseguir
     */
    private function travaPosCutover(): ?string
    {
        if ($this->option('dry-run')) {
            return null;
        }

        if ($this->option('eu-sei-o-que-estou-fazendo')) {
            Log::critical('Override manual da trava pos-cutover solicitado no ETL.', [
                'ambiente' => app()->environment(),
                'migrador' => $this->argument('migrator'),
            ]);

            return null;
        }

        try {
            $novo = DB::connection();

            if (! $novo->getSchemaBuilder()->hasTable('pedidos')) {
                return null;   // banco ainda não migrado: nada a proteger
            }

            $maxLegado = $this->maiorIdDoLegado('pedidos');

            if ($maxLegado === null) {
                return 'RECARGA BLOQUEADA: origem legado/tabela pedidos indisponível; '
                    .'não é possível provar que o cutover ainda não ocorreu.';
            }

            $nascidosAqui = (int) $novo->table('pedidos')->where('id', '>', $maxLegado)->count();

            if ($nascidosAqui === 0) {
                return null;
            }

            return "RECARGA BLOQUEADA: existem {$nascidosAqui} pedido(s) criados NESTE sistema "
                ."(id > {$maxLegado}, a faixa do legado).
"
                .'  O cutover ja aconteceu. Re-rodar o ETL sobrescreveria, via upsert por id, '
                ."toda edicao feita aqui sobre linhas herdadas — sem erro e sem log.\n"
                .'  Se ainda assim for necessario, use --eu-sei-o-que-estou-fazendo (e tenha backup).';
        } catch (\Throwable $e) {
            return 'RECARGA BLOQUEADA: falha ao inspecionar o estado de cutover: '.$e->getMessage();
        }
    }

    /** Maior id da tabela no legado, ou null se a origem não estiver acessível. */
    private function maiorIdDoLegado(string $tabela): ?int
    {
        try {
            $legado = DB::connection('legado');

            if (! $legado->getSchemaBuilder()->hasTable($tabela)) {
                return null;
            }

            $max = (int) ($legado->table($tabela)->max('id') ?? 0);

            return $max > 0 ? $max : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
