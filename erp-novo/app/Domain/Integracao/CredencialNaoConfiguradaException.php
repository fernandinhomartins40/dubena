<?php

namespace App\Domain\Integracao;

/**
 * FASE 2 (PLANO_SEGURANCA_MULTITENANT_APPS) — dinheiro é FAIL-CLOSED em produção.
 *
 * Lançada quando uma operação financeira (cartão/PIX) precisa da credencial da
 * EMPRESA e ela não está configurada. Em produção NUNCA se herda o env da
 * plataforma para cobrar — seria faturar no credenciamento de outra entidade.
 *
 * O handler global (bootstrap/app.php) converte em 503 com mensagem neutra
 * (`mensagemUsuario`), sem vazar detalhe interno; o detalhe fica no log.
 */
class CredencialNaoConfiguradaException extends \RuntimeException
{
    private function __construct(
        string $mensagemInterna,
        public readonly string $servico,
        public readonly ?int $empresaId,
        public readonly string $mensagemUsuario,
    ) {
        parent::__construct($mensagemInterna);
    }

    public static function cartao(?int $empresaId): self
    {
        return new self(
            "Credencial de CARTÃO não configurada para a empresa {$empresaId} (fail-closed em produção).",
            'cartao',
            $empresaId,
            'Pagamento online indisponível nesta revenda.',
        );
    }

    public static function pix(?int $empresaId): self
    {
        return new self(
            "Credencial PIX não configurada para a empresa {$empresaId} (fail-closed em produção).",
            'pix',
            $empresaId,
            'PIX indisponível nesta revenda.',
        );
    }
}
