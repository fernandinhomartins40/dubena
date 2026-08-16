<?php

namespace App\Etl\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Leitura da origem que distingue "tabela não existe" de "a leitura falhou"
 * (T2.6 do PLANO_PRODUCAO).
 *
 * **O problema.** Os migrators tinham 72 blocos `catch (\Throwable)` que
 * devolviam vazio sem log e sem contabilizar erro. O padrão apaga a diferença
 * entre três situações muito distintas:
 *
 *  1. a tabela não está no espelho (esperado em dev/CI) → vazio é correto;
 *  2. a conexão caiu no meio da carga → vazio é MENTIRA;
 *  3. a query tem erro de sintaxe/coluna → vazio esconde um bug.
 *
 * Nos casos 2 e 3 o ETL segue adiante e reporta "sucesso, 0 linhas". O
 * `AppGasEmCasaMigrator` mostrou a forma mais destrutiva disso: um catch em
 * `montarCorrelacoes()` zerava o mapa de correlação, e o migrator passava a
 * tratar TODOS os clientes do app como novos, recriando a base inteira.
 *
 * **O que este helper faz.** Só engole a exceção quando ela é comprovadamente
 * "relação não existe"; qualquer outra falha é registrada em log E empilhada
 * nos avisos do migrator, para aparecer no relatório do `etl:run`.
 *
 * O contraponto legítimo são as funções-sonda (`tabelaExiste()`,
 * `legadoDisponivel()`) — ali a pergunta É "existe?" e engolir a exceção é a
 * própria semântica. Essas não usam este helper.
 */
trait RegistraFalhaDeLeitura
{
    /** @var list<string> */
    private array $avisosDeLeitura = [];

    /**
     * Executa uma leitura da origem, devolvendo `$vazio` quando a tabela não
     * existe e registrando aviso quando a falha é de outra natureza.
     *
     * @template T
     *
     * @param  callable():T  $leitura
     * @param  T  $vazio  valor devolvido quando não há o que ler
     * @return T
     */
    protected function lerOuAvisar(string $descricao, callable $leitura, mixed $vazio = []): mixed
    {
        try {
            return $leitura();
        } catch (\Throwable $e) {
            if ($this->ehTabelaAusente($e) || $this->ehConexaoIndisponivel($e)) {
                // Caso 1: esperado fora do ambiente com dump. As invariantes
                // (CountInvariant::hasTable) é que decidem se isso é falha.
                //
                // Conexão ausente entra aqui pelo mesmo motivo: em dev/CI o
                // legado simplesmente não existe, e toda a arquitetura já trata
                // isso como "não se aplica" (ver `legadoDisponivel()` nos
                // migrators e o skip da CountInvariant). Tratar como erro faria
                // o `etl:run` falhar em qualquer ambiente sem dump.
                return $vazio;
            }

            $mensagem = sprintf('%s: leitura falhou — %s', $descricao, $e->getMessage());

            $this->avisosDeLeitura[] = $mensagem;

            Log::warning('[etl] '.$mensagem, [
                'migrator' => method_exists($this, 'nome') ? $this->nome() : static::class,
                'excecao' => $e::class,
            ]);

            return $vazio;
        }
    }

    /**
     * Avisos acumulados nesta execução, para compor o `MigrationResult`.
     *
     * @return list<string>
     */
    protected function avisosDeLeitura(): array
    {
        return $this->avisosDeLeitura;
    }

    protected function limparAvisosDeLeitura(): void
    {
        $this->avisosDeLeitura = [];
    }

    /**
     * A exceção é "não consegui nem conectar no legado"?
     *
     * Distinto de "a conexão caiu no meio da carga": aqui o banco de origem não
     * está configurado/acessível desde o início, que é a situação normal em
     * dev/CI. O `phpunit.xml` inclusive aponta a conexão `legado` para um destino
     * inexistente de propósito, para exercitar o caminho "sem dump".
     */
    private function ehConexaoIndisponivel(\Throwable $e): bool
    {
        $msg = mb_strtolower($e->getMessage());

        foreach ([
            'could not find driver',
            'connection refused',
            'no such host',
            'could not translate host name',
            'unable to connect',
            'database file does not exist',
            'connection to server',
            'name or service not known',
            'access denied for user',
            'unsupported driver',
        ] as $marca) {
            if (str_contains($msg, $marca)) {
                return true;
            }
        }

        // Driver não configurado no config/database.php.
        return $e instanceof \InvalidArgumentException
            && str_contains($msg, 'not configured');
    }

    /**
     * A exceção é "a relação não existe" (e não um erro real de query)?
     *
     * Cobre as grafias de Postgres, MySQL e sqlite, já que a conexão de origem
     * pode ser qualquer um dos três neste pipeline.
     */
    private function ehTabelaAusente(\Throwable $e): bool
    {
        if (! $e instanceof QueryException) {
            return false;
        }

        $msg = mb_strtolower($e->getMessage());

        // Casa só o que é inequivocamente TABELA ausente. Um "does not exist"
        // solto pegaria também `column "x" does not exist` — que é bug de query
        // e precisa aparecer, não ser engolido como ambiente sem dump.
        foreach ([
            '/relation ".+" does not exist/',   // Postgres
            '/undefined table/',                // Postgres (SQLSTATE 42P01)
            '/no such table/',                  // sqlite
            "/table '.+' doesn't exist/",       // MySQL
            '/invalid object name/',            // SQL Server
        ] as $padrao) {
            if (preg_match($padrao, $msg) === 1) {
                return true;
            }
        }

        return false;
    }
}
