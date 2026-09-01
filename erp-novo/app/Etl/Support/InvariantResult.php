<?php

namespace App\Etl\Support;

/**
 * Resultado de uma invariante: passou/falhou/inconclusivo + esperado/obtido.
 *
 * ## O terceiro estado (F7-10)
 *
 * Havia dois: `ok` e `falha`. E a fonte ausente caía no primeiro —
 * `BalanceInvariant` devolvia **`ok`** com a mensagem "sem movimentos no
 * recorte".
 *
 * O raciocínio era defensável: não gritar num banco recém-criado. Mas a mesma
 * resposta serve para dois fatos opostos:
 *
 *  - **antes da carga**, "sem movimentos" é o esperado;
 *  - **depois da carga**, "sem movimentos" significa que a carga **não trouxe
 *    nada** — e o portão do cutover aprovou assim mesmo.
 *
 * É a mesma família do registry vazio que imprimia "ETL concluído", e do
 * guardião que varria zero arquivos: **ausência de dado tratada como
 * aprovação**. O verde que não prova nada é pior que o vermelho, porque ninguém
 * investiga.
 *
 * `INCONCLUSIVO` não bloqueia sozinho — quem decide é o portão que consome. O
 * que ele garante é que a diferença **chega** a quem decide, em vez de ser
 * apagada aqui.
 */
final class InvariantResult
{
    public const APROVADA = 'ok';

    public const REPROVADA = 'falha';

    /** Não deu para verificar: fonte ausente, recorte vazio, check indisponível. */
    public const INCONCLUSIVA = 'inconclusiva';

    public function __construct(
        public readonly string $invariante,
        public readonly bool $ok,
        public readonly string $mensagem = '',
        public readonly mixed $esperado = null,
        public readonly mixed $obtido = null,
        /**
         * `ok` continua respondendo "posso seguir?" — e uma inconclusiva não
         * pode ser lida como aprovação. Por isso ela nasce com `ok = false`, e
         * quem quiser tratá-la de forma diferente da reprovação usa `situacao`.
         */
        public readonly string $situacao = self::APROVADA,
    ) {}

    public static function ok(string $nome, string $msg = ''): self
    {
        return new self($nome, true, $msg, situacao: self::APROVADA);
    }

    public static function falha(string $nome, string $msg, mixed $esperado = null, mixed $obtido = null): self
    {
        return new self($nome, false, $msg, $esperado, $obtido, self::REPROVADA);
    }

    /**
     * A invariante não pôde ser verificada.
     *
     * Use quando a FONTE está ausente — recorte vazio, tabela inexistente,
     * check indisponível. Não use para "verifiquei e está zerado": isso é uma
     * aprovação legítima.
     */
    public static function inconclusiva(string $nome, string $msg): self
    {
        return new self($nome, false, $msg, situacao: self::INCONCLUSIVA);
    }

    public function naoVerificada(): bool
    {
        return $this->situacao === self::INCONCLUSIVA;
    }

    public function resumo(): string
    {
        $status = match ($this->situacao) {
            self::APROVADA => 'OK ',
            self::INCONCLUSIVA => 'INCONCLUSIVA',
            default => 'FALHA',
        };

        $detalhe = $this->ok || $this->naoVerificada()
            ? $this->mensagem
            : sprintf('%s (esperado=%s, obtido=%s)', $this->mensagem, var_export($this->esperado, true), var_export($this->obtido, true));

        return trim(sprintf('[%s] %s — %s', $status, $this->invariante, $detalhe));
    }
}
