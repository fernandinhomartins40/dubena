# F4-02 — A projeção de saldo passa a ter prova

Data: 2026-08-31 (America/Sao_Paulo)

## O que existia

`estoquesaldos` é a projeção, mantida **na mesma transação do movimento**, com
lock pessimista do saldo. Isso está certo: é o que dá o saldo em O(1) na tela,
sem somar o ledger a cada consulta.

O que faltava é a outra metade da tarefa — *"materializada e **recalculável**;
divergência nunca é ajustada silenciosamente"*.

## Uma projeção sem conferência é uma afirmação sem prova

A projeção pode divergir do ledger por caminhos que não passam pelo serviço:

- um bug corrigido meses atrás, que deixou o rastro;
- um `UPDATE` manual em produção;
- uma migração de dados.

E **ninguém descobre**, porque a tela mostra a projeção e ninguém soma o ledger à
mão.

## O que o serviço faz — e o que deliberadamente não faz

`ConferenciaDeSaldo::divergencias()` compara, por `(setor, produto)`, o saldo
projetado com `SUM(quantidade)` do ledger.

**Não ajusta.** O gate da F4 é explícito, e a razão é que o ajuste automático
destrói a evidência:

> Se a projeção diz 10 e o ledger soma 8, há duas hipóteses — a projeção está
> errada (bug), ou **falta um movimento no ledger** (mercadoria que entrou sem
> registro). Sobrescrever a projeção com 8 resolve a tela e apaga a pergunta; e
> se a resposta era a segunda, a mercadoria some da contabilidade sem rastro.

Quem decide é gente, com o relatório na mão. Há teste provando que conferir não
altera nada.

## Três decisões

**A comparação percorre a UNIÃO das chaves**, não só as da projeção. Um par que
existe no ledger sem linha de saldo é o caso **mais grave** — mercadoria
movimentada que não aparece em lugar nenhum na tela — e olhar só a projeção o
deixaria invisível.

**Tolerância de 0,001.** A quantidade tem 3 casas no banco; comparar float por
igualdade exata produziria "divergência" de arredondamento, que não é divergência
de estoque. Um relatório com ruído deixa de ser lido.

**O comando sai com FAILURE** quando há divergência, para servir de portão em
script de deploy — e não só de relatório para leitura humana.

## Uso

```bash
php artisan estoque:conferir                    # todas as empresas
php artisan estoque:conferir --empresa=2
php artisan estoque:conferir --csv=/tmp/dif.csv # lista completa
```

Read-only por desenho.

## Verificação

| Portão | Resultado |
|---|---|
| Testes focais | 8 (`ConferenciaDeSaldoTest`) |
| Suíte integral | ver ESTADO_ATUAL |
| Pint | aprovado |

Os testes cobrem os dois lados da falha: projeção adulterada **e** movimento sem
projeção; que conferir não altera nada; que a conferência não atravessa empresa;
e que arredondamento não vira ruído.

## O que fica aberto

- **nenhum recálculo automático da projeção**, de propósito. Reconstruir
  `estoquesaldos` a partir do ledger é possível e seria uma linha — mas é
  exatamente o "ajuste silencioso" que o gate proíbe. Se vier a ser necessário
  (após uma migração, por exemplo), deve ser um comando próprio, explícito e com
  confirmação;
- este serviço confere `(setor, produto)`. A **custódia** (F4-04) tem o seu
  próprio ledger patrimonial, ainda não coberto.
