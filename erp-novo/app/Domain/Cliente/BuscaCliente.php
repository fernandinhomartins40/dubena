<?php

namespace App\Domain\Cliente;

use App\Domain\Identidade\NormalizadorTexto;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Busca única de cliente: nome ou fantasia SEM acento, telefone e documento.
 *
 * P2-D-05: o atendente digita rápido e sem acento — "joao", "acougue" — e a
 * busca comparava `LOWER(nome) LIKE`, que diferencia "joão" de "joao". A tela
 * respondia "Nenhum resultado." e o caminho natural era cadastrar o cliente DE
 * NOVO. A listagem de clientes tinha o mesmo defeito e, além disso, não
 * buscava por telefone, que é o primeiro dado de quem liga para um disk-gás.
 *
 * Como funciona:
 *  - O texto é comparado por `app_busca_cliente(nome, fantasia)`, que devolve
 *    "nome normalizado|fantasia normalizada" (minúsculas, sem acento, sem
 *    pontuação). O `|` separa os dois campos para um termo não casar
 *    atravessando o fim do nome e o começo da fantasia.
 *  - No Postgres ela é uma função SQL IMMUTABLE gerada a partir da MESMA
 *    tabela de acentos do `NormalizadorTexto`, com índice GIN trigram sobre a
 *    expressão — por isso a busca usa índice mesmo com `%termo%`, e a policy
 *    RLS (que custa por linha) só roda nas linhas candidatas.
 *  - No sqlite (suíte) a mesma função é registrada no PDO apontando para o
 *    PHP. O SQL é idêntico nos dois bancos; nada de `if driver` na consulta.
 *  - Coluna normalizada gravada pelo model foi descartada de propósito: o ETL
 *    grava clientes por `DB::table`, sem model, e toda linha migrada nasceria
 *    sem a chave — o mesmo erro do `tenant_account_id` nulo do pós-carga.
 */
final class BuscaCliente
{
    /** Separador entre nome e fantasia na chave; `basico()` nunca o produz. */
    public const SEPARADOR = '|';

    /**
     * Telefone sem máscara, em SQL portátil (`regexp_replace` não existe no
     * sqlite). O índice trigram de `clientetelefones` é sobre ESTA expressão,
     * textualmente: mudar aqui exige mudar a migration, ou o índice deixa de
     * ser usado sem erro nenhum.
     */
    public const TELEFONE_SO_DIGITOS = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefone, '(', ''), ')', ''), '-', ''), ' ', ''), '.', ''), '+', '')";

    /**
     * Abaixo de 4 dígitos o termo não é telefone nem documento plausível — e
     * "4" casaria com quase toda a base.
     */
    private const MINIMO_DIGITOS = 4;

    /** A chave que `app_busca_cliente` devolve, calculada em PHP. */
    public static function chave(?string $nome, ?string $fantasia): string
    {
        return NormalizadorTexto::basico($nome).self::SEPARADOR.NormalizadorTexto::basico($fantasia);
    }

    /**
     * Restringe a consulta de clientes ao que casa com o termo digitado.
     *
     * `id IN (… UNION …)` e não um `OR` entre as condições: com `OR` envolvendo
     * a subconsulta de telefones o Postgres desiste dos índices e varre a
     * tabela inteira rodando a RLS em cada linha. Cada ramo da união usa o
     * próprio índice.
     */
    public static function aplicar(Builder|EloquentBuilder $consulta, string $termo, string $tabela = 'clientes'): void
    {
        $texto = NormalizadorTexto::basico($termo);
        $digitos = NormalizadorTexto::digitos($termo);

        if ($texto === '' && strlen($digitos) < self::MINIMO_DIGITOS) {
            // Termo só de pontuação ("--", "()"): não há o que buscar, e
            // devolver a base inteira seria pior que devolver nada.
            $consulta->whereRaw('1 = 0');

            return;
        }

        $conexao = $consulta instanceof EloquentBuilder ? $consulta->getQuery()->getConnection() : $consulta->getConnection();

        // Postgres: os candidatos vêm da função indexada (ver a migration
        // 2026_10_31_1a0100 — sob RLS o LIKE não usa índice). A RLS continua
        // valendo na consulta de fora.
        if ($conexao->getDriverName() === 'pgsql') {
            $consulta->whereRaw("{$tabela}.id IN (SELECT public.app_clientes_candidatos(?, ?))", [$texto, $digitos]);

            return;
        }

        $ramos = [];

        if ($texto !== '') {
            self::registrarNoSqlite($conexao);
            $ramos[] = DB::table('clientes')->select('id')
                ->whereRaw('app_busca_cliente(nome, fantasia) LIKE ?', ['%'.$texto.'%']);
        }

        if (strlen($digitos) >= self::MINIMO_DIGITOS) {
            $ramos[] = DB::table('clientetelefones')->select('cliente_id')
                ->whereRaw(self::TELEFONE_SO_DIGITOS.' LIKE ?', ['%'.$digitos.'%']);
            // CPF/CNPJ: o ETL e o cadastro gravam só dígitos (PIE-35).
            $ramos[] = DB::table('clientes')->select('id')
                ->where(fn (Builder $w) => $w->where('cpf', 'like', '%'.$digitos.'%')
                    ->orWhere('cnpj', 'like', '%'.$digitos.'%'));
        }

        $uniao = array_shift($ramos);
        foreach ($ramos as $ramo) {
            $uniao->union($ramo);
        }

        $consulta->whereIn("{$tabela}.id", $uniao);
    }

    /**
     * No sqlite a função não existe: registra a versão PHP no PDO da conexão.
     * Idempotente — registrar de novo só substitui a mesma função.
     */
    private static function registrarNoSqlite(Connection $conexao): void
    {
        if ($conexao->getDriverName() !== 'sqlite') {
            return;
        }

        $conexao->getPdo()->sqliteCreateFunction(
            'app_busca_cliente',
            fn (?string $nome, ?string $fantasia): string => self::chave($nome, $fantasia),
            2,
        );
    }

    /**
     * Corpo da função SQL do Postgres, gerado da tabela de acentos do PHP.
     *
     * O banco pode ter `LC_CTYPE=C` (o de teste tem): aí `lower('Á')` devolve
     * 'Á', e só a tabela minúscula não bastaria. Por isso a tradução inclui as
     * maiúsculas acentuadas, mapeadas direto para a letra sem acento.
     *
     * @return array{de: string, para: string}
     */
    public static function traducaoPostgres(): array
    {
        $de = '';
        $para = '';
        foreach (NormalizadorTexto::tabelaAcentos() as $acentuada => $simples) {
            $de .= $acentuada;
            $para .= $simples;
            $maiuscula = mb_strtoupper($acentuada, 'UTF-8');
            if ($maiuscula !== $acentuada) {
                $de .= $maiuscula;
                $para .= $simples;
            }
        }

        return ['de' => $de, 'para' => $para];
    }
}
