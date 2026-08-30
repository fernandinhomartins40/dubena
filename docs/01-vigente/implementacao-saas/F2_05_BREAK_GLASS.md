# F2-05 — Break-glass substitui o bypass permanente de `support`

Data: 2026-08-29 (America/Sao_Paulo)

Primeira tarefa da F2. O gate da fase exige que "nenhum teste principal dependa
de `support`" e que "impersonação tenha trilha completa".

## O que `support` era

Bypass total de RBAC em **quatro camadas independentes**:

| Camada | Efeito |
|---|---|
| `Gate::before` | curto-circuita toda verificação antes da ability |
| `PolicyEvaluator::permite` | retorna `true` antes de RBAC/escopo/ABAC |
| `User::podeAcessarEmpresa` | acessa qualquer empresa |
| `User::empresasVisiveis` | enxerga a rede inteira |

Medido, não deduzido — a mesma rota, mesmo usuário:

```
SEM support = 403      COM support = 200
```

Na cópia real: **12 usuários ativos** com o flag, e `platform_audit_logs`
**zerado**. Ninguém sabe o que o suporte fez.

Numa revenda só isso é "o pessoal do suporte". Num SaaS com revendas
concorrentes é acesso permanente, irrestrito e invisível ao dado de todas elas.

## O que passou a ser

O acesso virou um **evento** com alvo, motivo, prazo e autor:

```
break_glass_grants: user_id, empresa_id, motivo, ticket_ref,
                    concedido_por, inicia_em, expira_em, revogado_em
```

`support` continua existindo — ele diz **quem pode pedir** acesso elevado. O que
deixou de ser é autorização por si.

O ponto único de decisão é `App\Domain\Acesso\BreakGlass`; as quatro camadas
passaram a chamar `User::acessoElevado()`. Concessão e revogação saem por
`saas:break-glass:conceder`, que exige motivo e recusa quem não tem o flag.

### Comportamento provado

| Situação | Resultado |
|---|---|
| `support`, sem concessão | **403** |
| Concessão vigente | **200** |
| Concessão expirada | **403** |
| Concessão revogada | **403** |
| Concessão para OUTRA empresa | **403** |
| Uso deixa trilha | 1 entrada `break_glass.usado` |
| Modo legado (enforcement off) | **200** — operação atual preservada |

### Um detalhe que mudou o desenho

A primeira versão memorizava a decisão por request. Isso fez o teste de
expiração falhar — e o defeito era real: **revogar não teria efeito imediato**,
o acesso sobreviveria até o fim da requisição. Metade do valor do break-glass é
poder cortar na hora.

Agora a consulta não é memorizada; o que se memoriza é apenas o **registro na
trilha**, para que as quatro camadas não gerem quatro linhas idênticas.

## Migração dos testes (F2-08 parcial)

96 usos de `'support' => true` espalhados por 87 arquivos — o flag era atalho
para "usuário que pode tudo", e isso mascarava justamente o RBAC que os testes
deveriam exercitar.

- os usos foram removidos e a `UserFactory` passou a conceder um **papel real**
  com todas as permissões (`FronteiraTenant::papelAdministrador`);
- o estado `semPapel()` atende os testes que provam o 403;
- dois testes que verificam o bypass em si (`FieldLevelTest`,
  `RbacContratoTest`) mantiveram o flag e fixaram o modo legado — é o
  comportamento que eles medem.

Progresso das falhas no modo enforcement: **290 → 69 → 36 → 0**.

## Evidência

- `BreakGlassTest`: 8 testes cobrindo os sete cenários da tabela.
- Suíte integral nos **dois modos**, verde.
- Pint aprovado.

## O que NÃO foi feito

O gate F2-05 pede também **2FA e aprovação quando crítica**. Não implementei:
exige decisão sobre quem aprova e qual ação é crítica, e o `platform_admins` já
tem `twofa_confirmado_em` que precisa ser considerado no desenho. Fica explícito
como pendência, não como esquecimento.

Restam da F2: F2-01 (manifesto), F2-02/02A (RBAC e `herda_filhos`), F2-03
(licença), F2-04 (Legacy Full), F2-06 (auditoria), F2-07 (segurança) e o restante
de F2-08.

`erp-novo/perda.sql` segue pré-existente e intocado.
