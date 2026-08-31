# F3-11 — O guardião que impede o padrão de voltar

Data: 2026-08-31 (America/Sao_Paulo)

## Por que um teste, e não só disciplina

A F3 gastou trabalho tirando decisões de negócio de dentro de palavras em
português: a situação "saiu para entrega" (F3-04A), o que um produto é e qual a
capacidade dele (F3-02).

**Nada disso se sustenta sozinho.** A próxima pessoa que precisar distinguir dois
conceitos que o modelo não separa vai fazer exatamente o que foi feito antes:

```php
str_contains($p->descricao, 'ALGUMA_COISA')
```

E vai funcionar. Passa no teste, resolve o chamado, e o custo só aparece na
segunda revenda, meses depois, como **uma tela com menos linhas do que devia** —
que é o modo mais difícil de perceber que existe.

Uma regra que depende de alguém lembrar não é uma regra. Por isso a proteção é
executável.

## O que ele faz

Varre `app/Domain`, `app/Http/Controllers` e `app/Jobs` procurando termos de
domínio em português — `VASILHA`, `CASCO`, `BOTIJAO`, `GRANEL`, `RECARGA`,
`SAIU`, `ROTA DE`, `CAMINHO` — e falha quando um deles aparece **governando uma
decisão**.

Três cuidados para não virar ruído:

- **presença não basta.** A palavra tem de estar na mesma linha que
  `str_contains`, `stripos`, `preg_match`, `LIKE` ou `ilike`. Comparar strings
  não é o problema; usar o resultado como condição é. Sem isso, todo rótulo de
  tela e mensagem de erro acusaria;
- **comentário não conta.** Documentação que cita o padrão antigo é
  documentação — inclusive a que explica por que ele foi removido. Contá-la
  faria este teste proibir a própria explicação;
- **a licença é nominal.** `VinculoVasilhame.php` é permitido, com o motivo
  escrito ao lado: a regex vive lá como sugestão para conferência humana, com a
  evidência junto, que é o lugar certo de um palpite.

E há um segundo teste que impede a lista de envelhecer: arquivo permitido que
não existe mais quebra a suíte, para a licença não continuar valendo depois de o
arquivo ser renomeado.

## A mensagem de falha aponta a saída

Um guardião que só diz "não" empurra o próximo para contornar a lista. Este diz
o que fazer:

```
A saída não é contornar esta lista: é declarar o conceito no cadastro,
como `PedidoSituacao::papel` (F3-04A) e `Produto::tipo` (F3-02).
Se for mesmo sugestão para conferência humana, o lugar é um método
`sugerir*` que devolva a evidência junto.
```

## Verificado que detecta

Um guardião que nunca acusa é decorativo. Inseri de propósito um
`str_contains(..., 'BOTIJAO')` no `EntregaService` e ele apontou arquivo e
linha:

```
app/Domain/Mobile/EntregaService.php:124 decide por "BOTIJAO" no texto
```

Removida a regressão, volta a passar.

## Escopo, e o que ele não cobre

A lista de termos é **do domínio de GLP**, não exaustiva do português. Um
conceito novo com vocabulário novo não é pego por ela.

Isso é uma limitação real e assumida: o valor deste teste está em travar o
caminho já trilhado, que é por onde a regressão volta. Ampliar a lista é barato
quando um conceito novo for declarado — e é o momento certo de fazê-lo, porque
aí se sabe qual palavra proibir.

## Verificação

| Portão | Resultado |
|---|---|
| Testes | 2 (`EscritaCanonicaTest`) |
| Detecção verificada com regressão proposital | apontou arquivo e linha |
| Suíte integral | ver ESTADO_ATUAL |
| Pint | aprovado |
