<?php

namespace App\Domain\Identidade;

/**
 * Peso de cada traço de identidade e os limiares de decisão.
 *
 * Ponto único de calibragem do sistema: mexer aqui muda o comportamento de
 * TODAS as portas de cadastro de uma vez.
 */
final class PesoTraco
{
    // ── Pesos por traço ──────────────────────────────────────────────────────
    //
    // Calibrados sobre o que a base real mostra, não sobre intuição.

    /** Documento é determinístico: se o CPF bate, é a mesma pessoa. */
    public const CPF = 100;

    public const CNPJ = 100;

    /**
     * Telefone é forte, mas NÃO é identidade: casa e família compartilham.
     * Medido na base: "JEANN RICARDO DE GOES-" e "Karem Francieli Calixto" no
     * mesmo número — duas pessoas reais. Por isso 60, abaixo do limiar de
     * consolidação automática: telefone sozinho nunca funde dois cadastros.
     */
    public const TELEFONE = 60;

    /** Telefone confirmado por SMS (Firebase) vale mais que digitado à mão. */
    public const TELEFONE_VERIFICADO = 75;

    /** E-mail é pessoal com mais frequência que telefone, mas é raro na base. */
    public const EMAIL = 45;

    /**
     * Nome fonético MUITO parecido (≥0.8). Sozinho não decide — homônimo
     * existe — mas somado a telefone ou endereço fecha o caso.
     */
    public const NOME_FORTE = 40;

    /**
     * Nome escrito EXATAMENTE igual (após normalizar caixa e acento).
     *
     * Vale mais que "muito parecido": a fonética casa "Juraci" com "Juracy",
     * o que é ótimo para achar o candidato mas admite homônimos distintos.
     * Nome idêntico letra a letra é evidência mais dura.
     *
     * Medido na base: 1.667 pares têm nome idêntico E mesmo endereço, sem
     * telefone em comum — duplicatas evidentes que, com NOME_FORTE (40+25=65),
     * ficariam represadas na fila de revisão para sempre. Com 75, o par
     * nome-exato + endereço chega a 100 e se resolve sozinho.
     */
    public const NOME_EXATO = 75;

    /** Nome parecido (0.6–0.8): evidência fraca, só compõe. */
    public const NOME_FRACO = 15;

    /**
     * Endereço sozinho vale pouco: prédio, vila e condomínio têm dezenas de
     * clientes no mesmo logradouro e número. Era exatamente o que a trava
     * antiga do ClienteService bloqueava — e por isso ela travava venda
     * legítima.
     */
    public const ENDERECO = 25;

    // ── Limiares de decisão ──────────────────────────────────────────────────

    /**
     * A partir daqui é a mesma pessoa: consolida sem perguntar.
     *
     * 100 exige DOCUMENTO, ou uma combinação de dois traços independentes
     * (telefone 60 + nome forte 40). Um traço sozinho nunca alcança.
     */
    public const LIMIAR_AUTOMATICO = 100;

    /**
     * Faixa da dúvida: cria o cadastro, conclui a venda e enfileira o par para
     * revisão humana.
     *
     * A decisão de projeto que sustenta tudo: na dúvida a venda ACONTECE. A
     * trava antiga ("Telefone informado já existe em outro cliente") abortava
     * a venda em campo, e o entregador contornava inventando outro telefone —
     * o que sujava a base justamente quando ela tentava se proteger.
     */
    public const LIMIAR_REVISAO = 50;

    /**
     * Nome parecido a partir daqui conta como NOME_FORTE.
     *
     * 0.8 e não 1.0 porque a base tem o mesmo cliente com nome parcial
     * ("Gabriel Niczay" × "GABRIEL NICZAI DE ARAUJO") e truncado
     * ("Miguel MARCELIt" × "MIGUEL MARCELITO").
     */
    public const SIMILARIDADE_FORTE = 0.8;

    /** Abaixo disto o nome não conta nada. */
    public const SIMILARIDADE_MINIMA = 0.6;
}
