<?php

namespace App\Domain\Produto;

/**
 * F3-02 — o que um produto É, declarado no cadastro.
 *
 * A vigilância de comodato precisa distinguir três coisas que o catálogo não
 * separava: o RECIPIENTE que se empresta (o casco), o CONTEÚDO que se vende
 * dentro dele (o gás) e todo o resto (água, acessório, serviço).
 *
 * Sem essa distinção, `VinculoVasilhame` decidia lendo a descrição:
 *
 *     str_contains($texto, 'VASILHA') || 'CASCO' || 'BOTIJAO' || 'BOTIJÃO'
 *     str_contains($texto, 'GLP') || 'RECARGA'   — e 'GRANEL' excluía
 *
 * Isso acerta o cadastro de uma revenda: a que escreveu essas palavras. Uma
 * revenda que cadastre "Cilindro 13kg", "P13 cheio" ou opere em espanhol fica
 * **invisível para a vigilância inteira** — e o modo de falhar é o pior
 * possível, porque a tela não fica vazia: ela mostra menos linhas, e ninguém
 * sabe quantas faltam.
 *
 * A regex não foi jogada fora: ela continua valendo como SUGESTÃO na tela de
 * conferência, que é onde um palpite tem valor. O que muda é que ela deixa de
 * ser a resposta.
 */
enum TipoProduto: string
{
    /** Ainda não classificado — a vigilância pede conferência humana. */
    case INDEFINIDO = 'INDEFINIDO';

    /** O casco: emprestado ao cliente, volta vazio, é objeto de comodato. */
    case RECIPIENTE = 'RECIPIENTE';

    /** O que se vende para encher o casco (o gás). */
    case CONTEUDO = 'CONTEUDO';

    /** Mercadoria comum: água, acessório, serviço — fora do ciclo de custódia. */
    case MERCADORIA = 'MERCADORIA';

    public function rotulo(): string
    {
        return match ($this) {
            self::INDEFINIDO => 'Não classificado',
            self::RECIPIENTE => 'Recipiente (casco/vasilhame)',
            self::CONTEUDO => 'Conteúdo (gás)',
            self::MERCADORIA => 'Mercadoria comum',
        };
    }

    /** Participa do ciclo de custódia (comodato e vigilância de giro)? */
    public function temCustodia(): bool
    {
        return $this === self::RECIPIENTE;
    }
}
