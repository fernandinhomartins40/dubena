<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Desfaz a duplicação de clientes do app (T2.2 do PLANO_PRODUCAO).
 *
 * O `AppGasEmCasaMigrator` não era idempotente (ver T2.1): cada reexecução
 * recriava a base de clientes do app numa faixa de ids nova. Medido no ambiente
 * local: 11.104 origens viraram 44.416 linhas, exatamente 4× cada.
 *
 * **Por que não é um DELETE.** 430 pedidos já apontam para as cópias. Apagar
 * antes de remapear criaria órfãos onde hoje há zero — a integridade referencial
 * é o aspecto impecável desta migração e não pode regredir. Então:
 * elege-se um sobrevivente por grupo, REMAPEIAM-SE todas as FKs para ele,
 * fundem-se os dados divergentes, e só então as cópias são removidas.
 *
 * As tabelas que referenciam `clientes` são descobertas no catálogo do Postgres
 * em tempo de execução — não há lista fixa no código, porque uma lista fixa
 * envelhece e o esquecimento vira órfão silencioso.
 *
 * Roda em --dry-run por default: executar de verdade exige --executar.
 */
class DedupClientesApp extends Command
{
    protected $signature = 'dados:dedup-clientes-app
        {--executar : Aplica as mudanças. Sem esta flag o comando é somente leitura.}
        {--csv= : Caminho para gravar o relatório de conflitos irreconciliáveis.}';

    protected $description = 'Deduplica clientes do app (T2.2) remapeando FKs antes de remover as cópias.';

    /** @var list<array{tabela:string,coluna:string}> */
    private array $referencias = [];

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->error('Este comando só roda no Postgres (usa o catálogo para descobrir as FKs).');

            return self::FAILURE;
        }

        if (! Schema::hasColumn('clientes', 'api_id')) {
            $this->error('A coluna clientes.api_id não existe — rode as migrations da T2.1 primeiro.');

            return self::FAILURE;
        }

        $executar = (bool) $this->option('executar');

        $grupos = $this->gruposDuplicados();
        if ($grupos === []) {
            $this->info('Nada a fazer: nenhum api_id duplicado em public.clientes.');

            return self::SUCCESS;
        }

        $this->referencias = $this->referenciasAClientes();

        $totalCopias = array_sum(array_map(fn ($g) => count($g['duplicatas']), $grupos));

        $this->info(sprintf(
            '%d grupos duplicados | %d linhas a remover | %d tabelas referenciam clientes.id',
            count($grupos), $totalCopias, count($this->referencias),
        ));

        $conflitos = $this->conflitosDeDados($grupos);
        if ($conflitos !== []) {
            $this->registrarConflitos($conflitos);

            $this->error(sprintf(
                '%d conflito(s) irreconciliável(is): sobrevivente e cópia divergem em campo de negócio.',
                count($conflitos),
            ));

            return self::FAILURE;
        }

        if (! $executar) {
            $this->linhaDeImpacto($grupos);
            $this->warn('DRY-RUN: nada foi alterado. Rode com --executar para aplicar.');

            return self::SUCCESS;
        }

        return $this->aplicar($grupos);
    }

    /**
     * Grupos com mais de uma linha por (empresa_id, api_id).
     *
     * O sobrevivente é o de MENOR id: é o da primeira execução do ETL, o mais
     * antigo, e o mais provável de já ter FKs apontando para ele.
     *
     * @return list<array{empresa_id:int,api_id:int,sobrevivente:int,duplicatas:list<int>}>
     */
    private function gruposDuplicados(): array
    {
        $linhas = DB::table('clientes')
            ->select('empresa_id', 'api_id', DB::raw('array_agg(id ORDER BY id) AS ids'))
            ->whereNotNull('api_id')
            ->groupBy('empresa_id', 'api_id')
            ->havingRaw('count(*) > 1')
            ->get();

        $grupos = [];
        foreach ($linhas as $l) {
            $ids = array_map('intval', explode(',', trim((string) $l->ids, '{}')));
            $sobrevivente = array_shift($ids);

            $grupos[] = [
                'empresa_id' => (int) $l->empresa_id,
                'api_id' => (int) $l->api_id,
                'sobrevivente' => $sobrevivente,
                'duplicatas' => $ids,
            ];
        }

        return $grupos;
    }

    /**
     * Todas as colunas que referenciam `clientes.id`, lidas do catálogo.
     *
     * @return list<array{tabela:string,coluna:string}>
     */
    private function referenciasAClientes(): array
    {
        // Lê de `pg_constraint`, NÃO de `information_schema`.
        //
        // Descoberto ao rodar em produção: `information_schema.constraint_column_usage`
        // só expõe constraints de objetos que o usuário POSSUI. A aplicação roda como
        // `erp_app` (a role restrita NOSUPERUSER NOBYPASSRLS do multi-tenant), que não
        // é dona das tabelas — então o comando via ZERO FKs e teria apagado as cópias
        // sem remapear nada, criando ~40 mil órfãos.
        //
        // `pg_constraint` é o catálogo real e é legível por qualquer role com acesso
        // às tabelas. O ANY(conkey) cobre FK composta, embora aqui todas sejam de
        // coluna única.
        $linhas = DB::select(<<<'SQL'
            SELECT c.conrelid::regclass::text AS tabela,
                   a.attname                  AS coluna
              FROM pg_constraint c
              JOIN pg_attribute a
                ON a.attrelid = c.conrelid
               AND a.attnum   = ANY (c.conkey)
             WHERE c.contype   = 'f'
               AND c.confrelid = 'public.clientes'::regclass
             ORDER BY 1, 2
        SQL);

        $refs = array_map(
            fn ($l) => [
                'tabela' => str_replace('public.', '', $l->tabela),
                'coluna' => $l->coluna,
            ],
            $linhas,
        );

        // Rede de segurança: `clientes` tem dezenas de tabelas filhas. Uma lista
        // vazia aqui significa catálogo inacessível, não "não há FK" — e seguir
        // em frente apagaria as cópias deixando as filhas órfãs.
        if ($refs === []) {
            throw new \RuntimeException(
                'Nenhuma FK para clientes.id foi encontrada no catálogo. Isso é '
                .'implausível e provavelmente indica falta de permissão de leitura '
                .'em pg_constraint. Abortando: remover as cópias sem remapear as '
                .'referências criaria órfãos.'
            );
        }

        return $refs;
    }

    /**
     * Divergência de dado entre o sobrevivente e suas cópias.
     *
     * As cópias nasceram da MESMA origem, na mesma leitura de
     * `clienteimportacoes` — os campos devem ser idênticos. Se não forem, algo
     * além da duplicação aconteceu (edição manual numa das cópias, por exemplo)
     * e a fusão deixaria de ser inócua. Nesse caso o comando aborta em vez de
     * escolher por conta própria qual dado sobrevive.
     *
     * @param  list<array{empresa_id:int,api_id:int,sobrevivente:int,duplicatas:list<int>}>  $grupos
     * @return list<array<string,mixed>>
     */
    private function conflitosDeDados(array $grupos): array
    {
        $campos = ['nome', 'cpf', 'email', 'datanascimento'];
        $conflitos = [];

        foreach (array_chunk($grupos, 500) as $bloco) {
            $ids = [];
            foreach ($bloco as $g) {
                $ids[] = $g['sobrevivente'];
                foreach ($g['duplicatas'] as $d) {
                    $ids[] = $d;
                }
            }

            $linhas = DB::table('clientes')
                ->whereIn('id', $ids)
                ->get(array_merge(['id'], $campos))
                ->keyBy('id');

            foreach ($bloco as $g) {
                $base = $linhas[$g['sobrevivente']] ?? null;
                if ($base === null) {
                    continue;
                }

                foreach ($g['duplicatas'] as $dupId) {
                    $copia = $linhas[$dupId] ?? null;
                    if ($copia === null) {
                        continue;
                    }

                    foreach ($campos as $campo) {
                        // Null numa das pontas não é conflito: é dado ausente
                        // que a fusão pode completar sem ambiguidade.
                        if ($base->$campo === null || $copia->$campo === null) {
                            continue;
                        }

                        if ((string) $base->$campo !== (string) $copia->$campo) {
                            $conflitos[] = [
                                'api_id' => $g['api_id'],
                                'sobrevivente' => $g['sobrevivente'],
                                'copia' => $dupId,
                                'campo' => $campo,
                                'valor_sobrevivente' => (string) $base->$campo,
                                'valor_copia' => (string) $copia->$campo,
                            ];
                        }
                    }
                }
            }
        }

        return $conflitos;
    }

    /** @param  list<array<string,mixed>>  $conflitos */
    private function registrarConflitos(array $conflitos): void
    {
        $caminho = (string) ($this->option('csv') ?: storage_path('app/dedup-clientes-conflitos.csv'));

        $arquivo = fopen($caminho, 'w');
        fputcsv($arquivo, array_keys($conflitos[0]));
        foreach ($conflitos as $c) {
            fputcsv($arquivo, $c);
        }
        fclose($arquivo);

        $this->warn("Conflitos gravados em: {$caminho}");
    }

    /** @param  list<array{empresa_id:int,api_id:int,sobrevivente:int,duplicatas:list<int>}>  $grupos */
    private function linhaDeImpacto(array $grupos): void
    {
        $duplicatas = $this->todasAsDuplicatas($grupos);

        $this->line('');
        $this->line('Linhas que serão REMAPEADAS por tabela:');

        foreach ($this->referencias as $ref) {
            $n = 0;
            foreach (array_chunk($duplicatas, 10000) as $bloco) {
                $n += DB::table($ref['tabela'])->whereIn($ref['coluna'], $bloco)->count();
            }

            if ($n > 0) {
                $this->line(sprintf('  %-32s %s  %d', $ref['tabela'], $ref['coluna'], $n));
            }
        }
    }

    /**
     * @param  list<array{empresa_id:int,api_id:int,sobrevivente:int,duplicatas:list<int>}>  $grupos
     * @return list<int>
     */
    private function todasAsDuplicatas(array $grupos): array
    {
        $ids = [];
        foreach ($grupos as $g) {
            foreach ($g['duplicatas'] as $d) {
                $ids[] = $d;
            }
        }

        return $ids;
    }

    /**
     * Remapeia FKs, funde dados e remove as cópias — tudo em UMA transação.
     *
     * @param  list<array{empresa_id:int,api_id:int,sobrevivente:int,duplicatas:list<int>}>  $grupos
     */
    private function aplicar(array $grupos): int
    {
        $duplicatas = $this->todasAsDuplicatas($grupos);

        // Mapa copia_id => sobrevivente_id, usado no UPDATE ... FROM (VALUES ...).
        $para = [];
        foreach ($grupos as $g) {
            foreach ($g['duplicatas'] as $d) {
                $para[$d] = $g['sobrevivente'];
            }
        }

        DB::transaction(function () use ($duplicatas, $para) {
            $this->completarCamposVazios($para);

            foreach ($this->referencias as $ref) {
                // `clientes.convenio_id` é auto-referência: precisa ser
                // remapeada como qualquer outra, senão sobra apontando para
                // uma linha que este mesmo comando vai apagar.
                $n = $this->remapear($ref['tabela'], $ref['coluna'], $para);
                if ($n > 0) {
                    $this->line(sprintf('  remapeado %-30s %s → %d linha(s)', $ref['tabela'], $ref['coluna'], $n));
                }
            }

            $removidas = 0;
            foreach (array_chunk($duplicatas, 5000) as $bloco) {
                $removidas += DB::table('clientes')->whereIn('id', $bloco)->delete();
            }

            $this->info("Cópias removidas: {$removidas}");

            // O remapeamento converge para o sobrevivente os telefones e
            // endereços que pertenciam às cópias. Como as cópias eram idênticas,
            // o cliente fica com o MESMO telefone repetido N vezes: dado
            // redundante que a ficha exibiria como várias linhas iguais.
            // Só aqui, depois do remapeamento, é possível saber quais colidem.
            $this->fundirDuplicatasConvergidas();

            // A sequence precisa voltar para o topo real da tabela: o mesmo
            // mecanismo do PreservaIdsDoLegado, para o próximo insert da
            // aplicação não colidir com um id ainda ocupado.
            DB::statement(
                "SELECT setval(pg_get_serial_sequence('clientes', 'id'), "
                .'COALESCE((SELECT MAX(id) FROM public.clientes), 1))'
            );
        });

        $this->info('Deduplicação concluída.');

        return self::SUCCESS;
    }

    /**
     * Remove as linhas-filhas que ficaram idênticas após o remapeamento.
     *
     * As cópias de um cliente carregavam telefone e endereço iguais (nasceram
     * da mesma leitura da origem). Ao convergirem para o sobrevivente, o mesmo
     * número aparece N vezes no mesmo cliente. Mantém-se a de MENOR id — a mais
     * antiga, e a que outras FKs teriam mais chance de referenciar.
     *
     * Só faz sentido depois do remapeamento: antes dele as linhas pertenciam a
     * clientes diferentes e não eram duplicatas coisa nenhuma.
     */
    private function fundirDuplicatasConvergidas(): void
    {
        // (tabela => colunas que definem "a mesma coisa" para aquele cliente)
        $chaves = [
            'clientetelefones' => ['cliente_id', 'telefone'],
            'cliente_enderecos' => ['cliente_id', 'endereco', 'numero'],
        ];

        foreach ($chaves as $tabela => $colunas) {
            if (! Schema::hasTable($tabela)) {
                continue;
            }

            $lista = implode(', ', $colunas);

            // `IS NOT DISTINCT FROM` por coluna (não `=`): telefone/número podem
            // ser NULL, e NULL = NULL é NULL — o par não casaria e a linha
            // redundante sobreviveria.
            $casamento = implode(' AND ', array_map(
                fn (string $c) => "t.{$c} IS NOT DISTINCT FROM d.{$c}",
                $colunas,
            ));

            $removidas = DB::affectingStatement(<<<SQL
                DELETE FROM public.{$tabela} t
                 USING (
                       SELECT min(id) AS manter, {$lista}
                         FROM public.{$tabela}
                        GROUP BY {$lista}
                       HAVING count(*) > 1
                 ) d
                 WHERE t.id <> d.manter
                   AND {$casamento}
            SQL);

            if ($removidas > 0) {
                $this->line("  fundidas em {$tabela}: {$removidas} linha(s) redundante(s)");
            }
        }
    }

    /**
     * Completa no sobrevivente os campos que só a cópia tinha preenchidos.
     *
     * "Não perca dado": se o sobrevivente tem e-mail nulo e uma cópia tem
     * e-mail, o e-mail vai para o sobrevivente antes de a cópia morrer.
     *
     * @param  array<int,int>  $para  copia_id => sobrevivente_id
     */
    private function completarCamposVazios(array $para): void
    {
        $campos = ['cpf', 'email', 'datanascimento', 'sexo'];
        $completados = 0;

        foreach (array_chunk($para, 2000, true) as $bloco) {
            $linhas = DB::table('clientes')
                ->whereIn('id', array_merge(array_keys($bloco), array_values($bloco)))
                ->get(array_merge(['id'], $campos))
                ->keyBy('id');

            $atualizacoes = [];

            foreach ($bloco as $copiaId => $sobreviventeId) {
                $copia = $linhas[$copiaId] ?? null;
                $base = $linhas[$sobreviventeId] ?? null;
                if ($copia === null || $base === null) {
                    continue;
                }

                foreach ($campos as $campo) {
                    if (($base->$campo === null || $base->$campo === '')
                        && $copia->$campo !== null && $copia->$campo !== '') {
                        $atualizacoes[$sobreviventeId][$campo] = $copia->$campo;
                        // Reflete em memória para a próxima cópia do mesmo grupo
                        // não sobrescrever o valor recém-adotado.
                        $base->$campo = $copia->$campo;
                    }
                }
            }

            foreach ($atualizacoes as $id => $valores) {
                DB::table('clientes')->where('id', $id)->update($valores);
                $completados++;
            }
        }

        if ($completados > 0) {
            $this->line("  campos completados a partir das cópias: {$completados} cliente(s)");
        }
    }

    /**
     * `UPDATE tabela SET coluna = sobrevivente FROM (VALUES ...) WHERE coluna = copia`.
     *
     * Um único statement por lote em vez de um UPDATE por grupo: com 33 mil
     * cópias e 24 tabelas, o laço ingênuo seria centenas de milhares de queries.
     *
     * @param  array<int,int>  $para  copia_id => sobrevivente_id
     */
    private function remapear(string $tabela, string $coluna, array $para): int
    {
        $total = 0;

        foreach (array_chunk($para, 5000, true) as $bloco) {
            $valores = [];
            $bind = [];
            foreach ($bloco as $de => $paraId) {
                $valores[] = '(?::bigint, ?::bigint)';
                $bind[] = $de;
                $bind[] = $paraId;
            }

            $sql = sprintf(
                'UPDATE public.%s AS t SET %s = m.novo FROM (VALUES %s) AS m(antigo, novo) WHERE t.%s = m.antigo',
                $tabela, $coluna, implode(',', $valores), $coluna,
            );

            $total += DB::affectingStatement($sql, $bind);
        }

        return $total;
    }
}
