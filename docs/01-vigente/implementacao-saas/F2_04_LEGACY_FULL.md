# F2-04 — `Legacy Full`: conservar acesso sem transformar ausência em regra

Data: 2026-08-31 (America/Sao_Paulo)

## O problema, em uma linha

`LicencaService` é fail-closed. No instante em que `SAAS_ENFORCE_LICENCA` fosse
ligado, **toda empresa sem assinatura perderia todos os módulos de uma vez**.

Medido antes de mexer: 103 testes falhavam com o enforcement ligado, todos com
402. Não era bug do enforcement — era o produto dizendo corretamente que ninguém
tinha contrato.

## A ordem é a coisa toda

Primeiro assinar quem já opera, depois ligar o enforcement. Invertido, a
transição derruba a operação que ela deveria proteger.

## As duas faces do risco

O risco óbvio é o acima. O risco silencioso é o oposto: **um plano de R$ 0,00 com
o catálogo inteiro é o melhor negócio da grade.** Sem barreiras, "transição" vira
"plano gratuito com tudo incluso", ninguém migra, e o fail-open que se queria
remover volta com outro nome.

Por isso `Legacy Full` tem quatro travas, e cada uma fecha uma porta diferente:

| Trava | Porta que fecha |
|---|---|
| `planos.transitorio` + escopo `vendaveis()` | aparecer na grade comercial |
| `definirAssinatura` recusa plano transitório | ser atribuído por um clique no painel |
| slug do plano transitório é imutável | renomear e deixar comando/relatório cegos |
| `saas:licenca:status` conta quem ainda está nele | virar permanente por esquecimento |

A terceira trava não é hipotética: o slug é como `saas:legacy-full` e o relatório
acham o plano. Renomeá-lo pelo painel não daria erro nenhum — as empresas
continuariam assinantes de um plano que o sistema deixou de reconhecer como
transitório.

`ativo` não servia para separar oferta de transição: o plano transitório
**precisa** estar ativo, senão as assinaturas que apontam para ele deixam de
valer. Daí a coluna nova.

## Por que comando e não seeder

Seeder roda a cada deploy e não pergunta nada. Assinar empresa é ato comercial:
precisa de `--dry-run`, de conferência do alvo e de trilha de quem mandou fazer.

O alvo é deliberadamente estreito — só empresa com vínculo de tenant **aprovado**
e **sem** assinatura vigente:

- fora da fronteira, o resolver nega de qualquer forma: assinar criaria licença
  para algo inalcançável;
- já assinante não pode ser rebaixada para um plano de transição por um comando
  de manutenção.

A assinatura nasce **sem `fim`**. Um prazo aqui desligaria a operação de quem já
roda numa data futura que ninguém lembraria de renovar. A pressão para migrar é
o relatório, não um corte automático.

## O que estava faltando no F2-06 e apareceu aqui

`AuditoriaPlataforma::registrar()` não aceitava `motivo` — eu havia criado a
coluna no F2-06 sem expor o parâmetro. Ficaria sempre nula, justamente na trilha
onde o porquê mais importa. Corrigido.

## As fixtures descreviam um mundo que F2-04 declarou inválido

Depois do comando pronto, o enforcement ainda acusava 104 falhas. A causa não era
o `Legacy Full`: os testes criam empresas por factory, sem passar por comando
nenhum.

Já existia `FronteiraTenant::paraEmpresa()`, criado quando o envelope teve o
mesmo problema — fixtures legadas (empresa + grupo e mais nada) contra um
resolver que exige vínculo aprovado. A licença precisava do mesmo tratamento, e
pela mesma razão: **empresa dentro da fronteira tem assinatura**. Isso não é
conveniência de teste, é o estado que F2-04 estabelece no mundo real.

A fixture assina no plano de **transição**, de propósito. Assinar `essencial`
faria a suíte exercitar uma grade comercial que o dono ainda vai desenhar — e no
dia em que ele a mudasse, a suíte quebraria sem nada ter quebrado.

Para o oposto existe `FronteiraTenant::semLicenca()`, usado pelos testes que
exercitam a negação. Ele **cancela** em vez de apagar, que é o que acontece de
fato quando uma assinatura termina.

## Resultado

| | antes | depois |
|---|---|---|
| Suíte com `SAAS_ENFORCE_LICENCA=true` | 103 falhas | **0** |
| Suíte no modo padrão | 1393 | **1405** |

Ambos os modos: 1405 passes / 4430 assertions / zero falhas.
`RlsCoberturaTest` 6/6 em PostgreSQL real; migration up → down → up validada;
`tsc --noEmit` limpo; Vitest 39; Pint aprovado.

**O portão da F2-04 está satisfeito: ligar `SAAS_ENFORCE_LICENCA` não tira módulo
de ninguém.** O que falta é operacional — rodar `saas:legacy-full --dry-run`
contra o banco real, conferir a lista, executar, e só então virar a variável.

## Sequência para produção

```bash
php artisan db:seed --class=PlanosSeeder   # cria o plano de transição
php artisan saas:licenca:status            # quem está descoberto (falha se houver)
php artisan saas:legacy-full --dry-run     # confere o alvo
php artisan saas:legacy-full               # pede confirmação
php artisan saas:licenca:status            # deve passar
# só então: SAAS_ENFORCE_LICENCA=true
```

## O que fica em aberto

Nenhuma empresa deve permanecer em `Legacy Full` indefinidamente — é o que
`saas:licenca:status` cobra a cada execução. A migração de cada revenda para um
plano vendável é decisão comercial do dono, não do código.
