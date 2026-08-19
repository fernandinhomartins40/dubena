<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Portão do banco de produção recém-criado (T6.2 do PLANO_PRODUCAO).
 *
 * **Por que existe.** A T6.2 define cinco verificações SQL binárias — banco
 * vazio, só as contas do `DeploySeeder`, role restrita, schema `legado`
 * espelhado. Elas estavam no plano como queries para copiar e colar **na
 * janela do cutover**, uma por uma. Passo manual em janela de madrugada é
 * passo que se erra: cola-se a query no banco errado, lê-se `0` de uma tabela
 * que não é a esperada, pula-se a última porque as anteriores passaram.
 *
 * **O que ele responde.** "Este banco está pronto para receber o ETL?" — antes
 * da carga, não depois. Rodar depois do ETL é esperado dar falha em quase tudo
 * (o banco deixa de estar vazio), e o comando avisa isso em vez de assustar.
 *
 * **O que ele NÃO faz.** Não cria banco, não roda migration, não semeia. É
 * read-only por construção, como o `cutover:check`.
 */
class BancoProducaoCheck extends Command
{
    protected $signature = 'banco:producao-check
                            {--pos-etl : afrouxa as checagens de "vazio" (uso depois da carga)}';

    protected $description = 'Verifica se o banco de produção está pronto para receber o ETL (T6.2).';

    private int $fail = 0;

    private int $warn = 0;

    /**
     * Tabelas que precisam nascer vazias.
     *
     * São as três do critério binário da T6.2. `clientes` e `pedidos` porque é
     * onde a massa demo de homolog apareceria; `users` tem tratamento próprio,
     * porque o `DeploySeeder` legitimamente cria contas.
     */
    private const DEVEM_ESTAR_VAZIAS = ['clientes', 'pedidos', 'financeiros', 'contamovimentos'];

    public function handle(): int
    {
        $this->info('== banco:producao-check — prontidão do banco para o ETL (T6.2) ==');
        $this->newLine();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->warn('Banco não é PostgreSQL — este portão só faz sentido em produção.');

            return self::SUCCESS;
        }

        $this->verificarVazio();
        $this->verificarUsuarios();
        $this->verificarSeedDemo();
        $this->verificarRoleRestrita();
        $this->verificarEspelhoLegado();
        $this->verificarConexaoLegado();
        $this->verificarCadastrosDeApoio();
        $this->verificarNumeracaoFiscal();

        $this->newLine();
        $this->line("Resultado: {$this->fail} FALHA(s), {$this->warn} aviso(s).");

        if ($this->fail > 0) {
            $this->error('PORTÃO FECHADO — o banco não está pronto para o ETL.');

            return self::FAILURE;
        }

        $this->info('PORTÃO LIBERADO — banco pronto para a carga.');

        return self::SUCCESS;
    }

    /**
     * As tabelas de massa nascem vazias.
     *
     * O achado que justifica (Auditoria §9 §7.7.4): *"dados criados em homolog
     * (massa demo Guarapuava/marketplace) não podem existir no banco de
     * produção"*. Reaproveitar o banco de homolog é o erro que esta checagem
     * pega — e ele é silencioso, porque tudo "funciona" com dados de mentira.
     */
    private function verificarVazio(): void
    {
        $posEtl = (bool) $this->option('pos-etl');

        foreach (self::DEVEM_ESTAR_VAZIAS as $tabela) {
            if (! Schema::hasTable($tabela)) {
                $this->item("Tabela {$tabela} existe", false, 'rode as migrations antes');

                continue;
            }

            $n = (int) DB::table($tabela)->count();

            if ($posEtl) {
                // Depois da carga, vazio é que seria suspeito.
                $this->item("{$tabela}: {$n} linha(s) após o ETL", $n > 0, 'tabela vazia depois da carga — o migrator rodou?');

                continue;
            }

            $this->item(
                "{$tabela} vazia ({$n})",
                $n === 0,
                "tem {$n} linha(s) — este banco NÃO nasceu limpo; não reaproveite o de homolog",
            );
        }
    }

    /**
     * Só as contas do `DeploySeeder`.
     *
     * Não fixo um número: o seeder pode ganhar contas legítimas. O que denuncia
     * massa demo é a ORDEM DE GRANDEZA — dezenas de usuários num banco que
     * deveria ter os administradores iniciais.
     */
    private function verificarUsuarios(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $n = (int) DB::table('users')->count();

        if ((bool) $this->option('pos-etl')) {
            $this->item("users: {$n}", $n > 0);

            return;
        }

        $this->item(
            "users: {$n} (esperado: só as contas do DeploySeeder)",
            $n > 0 && $n <= 10,
            $n === 0
                ? 'nenhum usuário — o DeploySeeder não rodou; ninguém consegue logar'
                : "{$n} usuários é massa demo, não seed de deploy",
        );
    }

    /**
     * Nenhum vestígio do seeder de demonstração.
     *
     * O `DemoGuarapuavaSeeder` cria clientes reconhecíveis. Se o nome da cidade
     * aparecer no cadastro de uma produção que ainda não recebeu ETL, o banco
     * veio de homolog.
     */
    private function verificarSeedDemo(): void
    {
        if ((bool) $this->option('pos-etl') || ! Schema::hasTable('cidades')) {
            return;
        }

        $demo = (int) DB::table('cidades')->count();

        $this->item(
            "cidades: {$demo} (esperado 0 antes do ETL)",
            $demo === 0,
            'cadastro de apoio já populado — sinal de banco de homolog reaproveitado',
            aviso: true,
        );
    }

    /**
     * A conexão de runtime não pode ignorar a RLS.
     *
     * Repete o que o `golive:check` já verifica, de propósito: este comando roda
     * ANTES dele na sequência do runbook, e um banco criado com a role errada
     * precisa ser descoberto agora — não depois de carregar 16 milhões de linhas.
     */
    private function verificarRoleRestrita(): void
    {
        try {
            $atual = DB::selectOne(
                'SELECT current_user AS usuario, rolsuper, rolbypassrls
                 FROM pg_roles WHERE rolname = current_user',
            );

            $restrita = $atual !== null && ! $atual->rolsuper && ! $atual->rolbypassrls;

            $this->item(
                'Role de runtime restrita (current_user='.($atual->usuario ?? '?').')',
                $restrita,
                'a conexão tem SUPERUSER/BYPASSRLS — o PostgreSQL IGNORA a RLS. '
                .'Confirme RLS_APP_DB_PASSWORD no .env: sem ela a migration é NO-OP silencioso',
            );
        } catch (\Throwable $e) {
            $this->item('Role de runtime verificável', false, $e->getMessage());
        }
    }

    /**
     * O schema `legado` precisa estar espelhado antes da carga.
     *
     * Critério do plano: ≥ 121 tabelas. Sem o espelho, `etl:run` roda e devolve
     * zero em quase tudo — e a falha aparece só no `cutover:check`, horas
     * depois.
     */
    private function verificarEspelhoLegado(): void
    {
        try {
            $n = (int) DB::scalar(
                "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'legado'",
            );

            $this->item(
                "Schema legado espelhado ({$n} tabelas, esperado >= 121)",
                $n >= 121,
                $n === 0
                    ? 'schema `legado` vazio ou ausente — rode database/etl/espelhar_oracle.py contra o Oracle de produção'
                    : "só {$n} tabelas — espelho incompleto; o ETL devolveria zero em vários migrators",
            );
        } catch (\Throwable $e) {
            $this->item('Espelho do legado verificável', false, $e->getMessage());
        }
    }

    /**
     * A app CONSEGUE LER o espelho? (18/08/2026)
     *
     * Ter o schema espelhado não basta: a conexão `legado` precisa apontar para
     * ele e a role de runtime precisa ter SELECT. Em produção os dois falharam
     * ao mesmo tempo — o `.env` apontava para um banco `ctrl` inexistente (o
     * default de `config/database.php`) e o schema pertencia ao owner.
     *
     * **O sintoma não era erro.** Os migrators avisam "tabela ausente no
     * espelho" e PULAM, então colaboradores, frota, plano de contas e centro de
     * custo ficaram vazios sem nada quebrar. Descoberto só quando alguém
     * estranhou as telas zeradas — que é tarde demais numa janela de cutover.
     */
    private function verificarConexaoLegado(): void
    {
        try {
            $tabelas = DB::connection('legado')
                ->getSchemaBuilder()
                ->hasTable('clientes');

            $this->item(
                'App consegue LER o espelho (conexão `legado`)',
                $tabelas,
                'a conexão `legado` não enxerga o espelho. Confira LEGADO_DB_HOST/DATABASE/USERNAME '
                .'(devem ser os MESMOS de DB_*) e LEGADO_DB_SCHEMA=legado',
            );
        } catch (\Throwable $e) {
            $msg = $e->getMessage();

            $dica = str_contains($msg, 'does not exist') || str_contains($msg, 'permission denied')
                ? 'falta GRANT: `GRANT USAGE ON SCHEMA legado TO erp_app; '
                    .'GRANT SELECT ON ALL TABLES IN SCHEMA legado TO erp_app;`'
                : 'conexão `legado` mal configurada — o espelho vive no MESMO banco, schema `legado`';

            $this->item('App consegue LER o espelho (conexão `legado`)', false, $dica);
        }
    }

    /**
     * Cadastros de apoio carregados E com o id do legado preservado.
     *
     * O modo de falha que isto pega e silencioso: com a conexao `legado`
     * quebrada, o migrator lia zero linhas, gravava zero e reportava sucesso —
     * a CountInvariant comparava 0 origem = 0 destino e passava. Sem
     * `tipopessoas`, o `anularFksInvalidas` do ClientesMigrator zerou o
     * `tipopessoa_id` de 44 mil clientes, e o formulario passou a exibir
     * cliente sem tipo nem segmento.
     */
    private function verificarCadastrosDeApoio(): void
    {
        if (! $this->option('pos-etl')) {
            return; // antes da carga estas tabelas DEVEM estar vazias
        }

        foreach (['tipopessoas', 'segmentos', 'telefonetipos'] as $tabela) {
            if (! Schema::hasTable($tabela)) {
                continue;
            }

            $this->item(
                "Cadastro de apoio `{$tabela}` carregado",
                DB::table($tabela)->count() > 0,
                'rode `php artisan etl:run cadastros-apoio` — sem isto os selects da SPA vem vazios',
            );
        }

        if (! Schema::hasTable('clientes') || DB::table('clientes')->count() === 0) {
            return;
        }

        // Vinculo preservado: se a origem tinha tipo de pessoa, o destino tambem tem.
        $this->item(
            'Clientes mantiveram o vinculo com tipo de pessoa',
            DB::table('clientes')->whereNotNull('tipopessoa_id')->exists(),
            'todos os clientes estao sem `tipopessoa_id` — sinal de que `tipopessoas` estava vazia '
            .'na carga e as FKs foram anuladas. Recarregue apoio e depois clientes',
        );
    }

    /**
     * A numeração fiscal foi herdada do legado?
     *
     * O modo de falha e catastrofico e silencioso: sem a sequencia semeada, a
     * PRIMEIRA nota emitida no sistema novo sai com numero 1 e colide com as
     * dezenas de milhares ja autorizadas na Receita. A SEFAZ rejeita, e isso so
     * aparece na hora de faturar — com o legado ja desligado.
     */
    private function verificarNumeracaoFiscal(): void
    {
        if (! $this->option('pos-etl') || ! Schema::hasTable('notas_fiscais')) {
            return;
        }

        $emitidas = DB::table('notas_fiscais')
            ->selectRaw('empresa_id, modelo, serie, MAX(numero) AS maxnum')
            ->groupBy('empresa_id', 'modelo', 'serie')
            ->get();

        if ($emitidas->isEmpty()) {
            return; // nada emitido no legado: nao ha numeracao a herdar
        }

        foreach ($emitidas as $n) {
            $chave = "nf:{$n->empresa_id}:{$n->modelo}:{$n->serie}";
            $valor = (int) (DB::table('sequencias')->where('chave', $chave)->value('valor') ?? 0);

            $this->item(
                "Numeracao fiscal herdada (empresa {$n->empresa_id} modelo {$n->modelo} serie {$n->serie})",
                $valor >= (int) $n->maxnum,
                sprintf(
                    'sequencia em %d, mas ja existe nota numero %d — a proxima emissao REPETIRIA '
                    .'numero autorizado na Receita. Rode `etl:run fiscal`',
                    $valor, (int) $n->maxnum,
                ),
            );
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
            $this->warn++;

            return;
        }

        $this->error("  FAIL {$label}".($detalhe ? " — {$detalhe}" : ''));
        $this->fail++;
    }
}
