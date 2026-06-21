<?php

namespace App\Etl\Support;

/**
 * Resultado de uma invariante: passou/falhou + esperado/obtido + mensagem.
 */
final class InvariantResult
{
    public function __construct(
        public readonly string $invariante,
        public readonly bool $ok,
        public readonly string $mensagem = '',
        public readonly mixed $esperado = null,
        public readonly mixed $obtido = null,
    ) {
    }

    public static function ok(string $nome, string $msg = ''): self
    {
        return new self($nome, true, $msg);
    }

    public static function falha(string $nome, string $msg, mixed $esperado = null, mixed $obtido = null): self
    {
        return new self($nome, false, $msg, $esperado, $obtido);
    }

    public function resumo(): string
    {
        $status = $this->ok ? 'OK ' : 'FALHA';
        $detalhe = $this->ok ? $this->mensagem
            : sprintf('%s (esperado=%s, obtido=%s)', $this->mensagem, var_export($this->esperado, true), var_export($this->obtido, true));

        return trim(sprintf('[%s] %s — %s', $status, $this->invariante, $detalhe));
    }
}
