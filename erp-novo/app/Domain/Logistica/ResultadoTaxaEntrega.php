<?php

namespace App\Domain\Logistica;

use App\Models\Logistica\TaxaEntrega;

/**
 * O que a calculadora decidiu: quanto cobrar, quanto custa e POR QUÊ.
 *
 * A regra que decidiu viaja junto porque o pedido precisa gravá-la: mudar a
 * tabela de preços depois não pode reescrever o que foi cobrado no passado.
 */
final class ResultadoTaxaEntrega
{
    private function __construct(
        public readonly float $valor,
        public readonly ?float $custo,
        public readonly ?int $regraId,
        public readonly ?string $descricao,
        public readonly bool $isenta,
    ) {}

    public static function de(TaxaEntrega $regra): self
    {
        return new self(
            // Isenção zera a cobrança mesmo com `valor` preenchido: a regra
            // pode existir com valor de referência e ainda assim ser grátis.
            valor: $regra->isenta ? 0.0 : (float) $regra->valor,
            custo: $regra->custo_estimado !== null ? (float) $regra->custo_estimado : null,
            regraId: $regra->id,
            descricao: $regra->descricao,
            isenta: (bool) $regra->isenta,
        );
    }

    /**
     * Sem regra configurada = entrega gratuita.
     *
     * Fail-safe para o CLIENTE FINAL: silêncio na configuração não pode virar
     * cobrança surpresa. É o oposto do fail-closed de credencial — aqui o dano
     * de cobrar indevidamente é maior que o de não cobrar.
     */
    public static function semRegra(): self
    {
        return new self(0.0, null, null, null, false);
    }

    /** Margem da entrega, quando o custo está configurado. */
    public function margem(): ?float
    {
        return $this->custo !== null ? $this->valor - $this->custo : null;
    }

    /** @return array<string, mixed> */
    public function paraArray(): array
    {
        return [
            'valor' => round($this->valor, 2),
            'custo' => $this->custo !== null ? round($this->custo, 2) : null,
            'margem' => $this->margem() !== null ? round($this->margem(), 2) : null,
            'isenta' => $this->isenta,
            'regra_id' => $this->regraId,
            'regra' => $this->descricao,
        ];
    }
}
