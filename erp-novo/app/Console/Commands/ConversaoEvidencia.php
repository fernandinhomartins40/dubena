<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * F7-13 — o bundle de evidência da conversão.
 *
 * ## Por que um arquivo, e não uma tela
 *
 * O plano pede um bundle **imutável** com mapeamentos, checks, contagens,
 * hashes, quarentena, aprovações e versões. A palavra que decide o formato é
 * *imutável*: uma tela consulta o banco e mostra o estado de **agora**; a
 * evidência precisa mostrar o estado **daquele momento**, meses depois, quando
 * alguém perguntar por que a conversão foi aprovada.
 *
 * Por isso um JSON gravado em disco, com hash. O hash é o que torna a
 * imutabilidade verificável — sem ele, "imutável" é promessa.
 *
 * ## O que entra, e de onde vem
 *
 * Nada é inventado aqui. Tudo já é registrado por outra peça:
 *
 * | Seção | Origem |
 * |---|---|
 * | execuções | `conversao_execucoes` (F7) |
 * | linhagem | `conversao_linhagem` — contagem por sistema/entidade |
 * | quarentena | `conversao_quarentena`, com o que ficou PENDENTE |
 * | contagens | as tabelas de destino, por empresa |
 * | versões | migrations aplicadas + commit do código |
 *
 * ## O que NÃO entra, e por quê
 *
 * **Aprovações** e **resultado do rollback ensaiado**. As duas são atos humanos:
 * alguém assina, alguém cronometra. Gerá-las a partir do banco seria inventar
 * assinatura — exatamente o oposto do que uma evidência serve para fazer.
 *
 * O bundle deixa os dois campos declarados e vazios, para quem preencher saber
 * que faltam. Um relatório que omite o que não tem é pior que um que mostra a
 * lacuna.
 */
class ConversaoEvidencia extends Command
{
    protected $signature = 'conversao:evidencia
        {--saida= : caminho do arquivo; padrão = storage/app/evidencia-<timestamp>.json}
        {--execucao= : limita a uma execução de conversão}';

    protected $description = 'Gera o bundle imutável de evidência da conversão (F7-13). Não altera nada.';

    public function handle(): int
    {
        $bundle = [
            'gerado_em' => now()->toIso8601String(),
            'versoes' => $this->versoes(),
            'execucoes' => $this->execucoes(),
            'linhagem' => $this->linhagem(),
            'quarentena' => $this->quarentena(),
            'contagens' => $this->contagens(),

            // Atos HUMANOS: declarados e vazios de propósito. Preenchê-los a
            // partir do banco seria inventar assinatura.
            'aprovacoes' => [
                'tecnica' => null,
                'operacional' => null,
                'financeira_fiscal' => null,
                'controlador_dos_dados' => null,
            ],
            'rollback_ensaiado' => [
                'executado_em' => null,
                'duracao_minutos' => null,
                'resultado' => null,
                'responsavel' => null,
            ],
        ];

        // O hash cobre o conteúdo SEM ele mesmo — senão não haveria como
        // recalcular para conferir.
        $json = json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $bundle['hash_sha256'] = hash('sha256', (string) $json);

        $caminho = (string) ($this->option('saida')
            ?: storage_path('app/evidencia-'.now()->format('Ymd-His').'.json'));

        $final = json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (@file_put_contents($caminho, $final) === false) {
            $this->error("Não consegui escrever em {$caminho}.");

            return self::FAILURE;
        }

        $this->info('Bundle gravado em '.$caminho);
        $this->line('SHA-256: '.$bundle['hash_sha256']);

        $pendentes = $bundle['quarentena']['pendentes'] ?? null;

        // Não ter conseguido LER a quarentena não é o mesmo que ela estar
        // limpa. Tratar os dois igual faria uma falha de leitura sair como
        // evidência aprovada — o oposto do que uma evidência serve para fazer.
        if ($pendentes === null) {
            $this->newLine();
            $this->error('Não consegui ler a quarentena: '.($bundle['quarentena']['erro_de_leitura'] ?? 'motivo desconhecido'));
            $this->line('Sem essa leitura o bundle não prova nada sobre casos em aberto.');

            return self::FAILURE;
        }

        if ($pendentes > 0) {
            $this->newLine();
            $this->warn("{$pendentes} registro(s) em quarentena ainda PENDENTES.");
            $this->line('O gate F8 exige zero quarentena bloqueante — o bundle documenta, não aprova.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Versão do código e do schema.
     *
     * Sem isto o bundle não responde "que código produziu este resultado", e a
     * evidência vira um retrato sem data.
     */
    private function versoes(): array
    {
        return [
            'commit' => $this->commitAtual(),
            'migrations_aplicadas' => $this->contarSeguro('migrations'),
            'ambiente' => app()->environment(),
        ];
    }

    /**
     * O commit do codigo, lido do `.git` — sem `shell_exec`.
     *
     * `shell_exec('git ...')` funciona no Linux e escreve "O sistema nao pode
     * encontrar o caminho especificado" no stderr do Windows, poluindo a saida
     * do comando. E ha ambientes onde `shell_exec` e desabilitado por politica.
     *
     * Ler o arquivo e deterministico e nao depende de shell: `HEAD` aponta para
     * a ref, e a ref guarda o hash. Se algo nao bater, devolve null — evidencia
     * sem commit e pior que evidencia que nao gera.
     */
    private function commitAtual(): ?string
    {
        $head = base_path('../.git/HEAD');

        if (! is_file($head)) {
            return null;
        }

        $conteudo = trim((string) @file_get_contents($head));

        // `HEAD` detached ja e o proprio hash.
        if (preg_match('/^[0-9a-f]{40}$/', $conteudo) === 1) {
            return $conteudo;
        }

        if (! str_starts_with($conteudo, 'ref: ')) {
            return null;
        }

        $ref = base_path('../.git/'.substr($conteudo, 5));
        $hash = is_file($ref) ? trim((string) @file_get_contents($ref)) : '';

        return preg_match('/^[0-9a-f]{40}$/', $hash) === 1 ? $hash : null;
    }

    /** @return list<array<string,mixed>> */
    private function execucoes(): array
    {
        try {
            return DB::table('conversao_execucoes')
                ->when($this->option('execucao'), fn ($q, $id) => $q->where('id', (int) $id))
                ->orderByDesc('id')
                ->limit(50)
                ->get([
                    'id', 'situacao', 'alvo', 'dry_run', 'com_invariantes',
                    'iniciada_em', 'encerrada_em',
                    'linhas_lidas', 'linhas_gravadas', 'linhas_quarentena',
                ])
                ->map(fn ($l) => (array) $l)
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Linhagem AGREGADA por sistema e entidade.
     *
     * O detalhe linha a linha fica na tabela; o bundle carrega o que responde
     * "quanto veio de onde" sem virar um arquivo de centenas de MB.
     */
    private function linhagem(): array
    {
        try {
            return DB::table('conversao_linhagem')
                ->selectRaw('sistema_origem, entidade, count(*) as linhas')
                ->groupBy('sistema_origem', 'entidade')
                ->orderBy('sistema_origem')
                ->get()
                ->map(fn ($l) => (array) $l)
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function quarentena(): array
    {
        try {
            $porMotivo = DB::table('conversao_quarentena')
                ->selectRaw('motivo, decisao, count(*) as total')
                ->groupBy('motivo', 'decisao')
                ->get()
                ->map(fn ($l) => (array) $l)
                ->all();

            return [
                'total' => DB::table('conversao_quarentena')->count(),
                'pendentes' => DB::table('conversao_quarentena')->where('decisao', 'PENDENTE')->count(),
                'por_motivo' => $porMotivo,
            ];
        } catch (\Throwable $e) {
            // Fail-closed. Devolver `pendentes => 0` aqui seria dizer "não há
            // caso em aberto" quando a verdade é "não consegui olhar" — e o
            // comando sairia com SUCESSO, que um script de deploy leria como
            // aprovação para virar.
            //
            // `null` obriga quem lê a distinguir as duas situações, e o
            // `handle` trata a leitura falha como impedimento.
            return [
                'total' => null,
                'pendentes' => null,
                'por_motivo' => [],
                'erro_de_leitura' => $e->getMessage(),
            ];
        }
    }

    /**
     * Contagens por empresa nas tabelas que mais importam.
     *
     * É o número que a operação confere primeiro — "meus clientes vieram
     * todos?" — e o que torna a evidência conferível sem abrir o sistema.
     */
    private function contagens(): array
    {
        $tabelas = ['clientes', 'pedidos', 'produtos', 'financeiros', 'notas_fiscais'];
        $saida = [];

        foreach ($tabelas as $tabela) {
            try {
                $saida[$tabela] = DB::table($tabela)
                    ->selectRaw('empresa_id, count(*) as total')
                    ->groupBy('empresa_id')
                    ->orderBy('empresa_id')
                    ->get()
                    ->map(fn ($l) => (array) $l)
                    ->all();
            } catch (\Throwable) {
                $saida[$tabela] = [];
            }
        }

        return $saida;
    }

    private function contarSeguro(string $tabela): int
    {
        try {
            return DB::table($tabela)->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
