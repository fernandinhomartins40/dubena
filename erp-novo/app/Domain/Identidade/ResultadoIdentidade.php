<?php

namespace App\Domain\Identidade;

use App\Models\Cliente\Cliente;

/**
 * Um candidato a "mesma pessoa", com o escore e o PORQUÊ.
 *
 * Os motivos não são decoração: quem revisa a fila precisa ver o que casou
 * ("Telefone idêntico", "Nome muito parecido (92%)") para decidir. Um escore
 * nu, sem justificativa, obrigaria a pessoa a conferir campo a campo — que é
 * o trabalho que este sistema existe para evitar.
 */
final class ResultadoIdentidade
{
    /** @param list<string> $motivos */
    public function __construct(
        public readonly Cliente $cliente,
        public readonly int $escore,
        public readonly array $motivos,
        public readonly float $similaridadeNome = 0.0,
    ) {}

    /** Confiança alta o bastante para consolidar sem perguntar a ninguém. */
    public function consolidaAutomaticamente(): bool
    {
        return $this->escore >= PesoTraco::LIMIAR_AUTOMATICO;
    }

    /** Faixa da dúvida: cadastra, vende, e manda o par para revisão humana. */
    public function mereceRevisao(): bool
    {
        return $this->escore >= PesoTraco::LIMIAR_REVISAO
            && $this->escore < PesoTraco::LIMIAR_AUTOMATICO;
    }

    /** Rótulo da confiança para exibir na tela. */
    public function confianca(): string
    {
        return match (true) {
            $this->escore >= PesoTraco::LIMIAR_AUTOMATICO => 'alta',
            $this->escore >= PesoTraco::LIMIAR_REVISAO => 'media',
            default => 'baixa',
        };
    }

    /** @return array<string, mixed> */
    public function paraArray(): array
    {
        return [
            'cliente_id' => $this->cliente->id,
            'nome' => $this->cliente->nome,
            'documento' => $this->cliente->cpf ?: $this->cliente->cnpj,
            'ativo' => (bool) $this->cliente->ativo,
            'escore' => $this->escore,
            'confianca' => $this->confianca(),
            'motivos' => $this->motivos,
        ];
    }
}
