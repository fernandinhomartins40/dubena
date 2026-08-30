# F2-02A — `herda_filhos`: a semântica existe e passou a ser testada

Data: 2026-08-30 (America/Sao_Paulo)

A tarefa exige: *"decidir e testar a semântica de `herda_filhos`; implementá-la
integralmente ou migrar/remover o campo. Persistir uma promessa sem enforcement
fica proibido."*

## A previsão da auditoria não se confirmou

A expectativa era encontrar um campo gravado e nunca lido — promessa sem
enforcement. **Não é o caso.** `PolicyEvaluator::escopoCobre()` implementa a
semântica de verdade:

| Escopo do papel | Com `herda_filhos` | Sem |
|---|---|---|
| Unidade | alcança departamentos e setores dela | só o que casa a unidade |
| Departamento | alcança os setores dele | só o que casa o departamento |
| Setor | — | — |

As três consultas de descida (`setorPertenceAoDepartamento`,
`departamentoPertenceAUnidade`, `setorPertenceAUnidade`) filtram por
`empresa_id`, então a herança não atravessa empresa.

Decisão: **implementar integralmente** — o campo fica, porque já faz o que
promete.

### Setor ignora `herda_filhos`, e isso é correto

Setor é a folha da hierarquia: não há nível abaixo para herdar. A assimetria com
os outros dois níveis é intencional, e agora está fixada em teste para não
parecer esquecimento a quem ler depois.

## A lacuna real: o teste, não o código

O que faltava era cobertura. O teste de unidade existente comparava apenas
`unidade_id` direto — **o ramo que desce dois níveis nunca era exercitado**, nem
o `herda_filhos = false` desse nível. Uma regressão ali passaria calada.

Dois testes novos:

- **unidade cobre departamento e setor somente quando herda** — verifica a descida
  nos dois níveis, que ela não vaza para outra unidade, e que `false` fecha tudo
  menos o casamento direto;
- **escopo de setor é folha e ignora `herda_filhos`** — registra a assimetria como
  decisão.

## A outra metade da F2-02

*"Autorização comportamental em cada porta de mutação e leitura sensível."*

Medido com o detector de F2-01: das rotas de **mutação** autenticadas sem
permissão declarada, as 5 em `api/admin` são as exceções já justificadas (2FA e
sessões do próprio usuário, trocar de empresa). As demais são `api/app/v1`, onde
a fronteira é o papel do token.

Verifiquei que essa fronteira existe de fato: **8 rotas** `app/v1` não têm
`approle`, e todas são legítimas — login, cadastro, logout, refresh, registro de
device e marketplace público. Nenhuma delas pode exigir um papel de token que
ainda não foi emitido.

## Evidência

- `AbacPolicyEvaluatorTest`: **12 testes / 30 assertions** (eram 10).
- Suíte integral verde nos dois modos.
- Pint aprovado.

## Estado da F2

| Tarefa | Estado |
|---|---|
| F2-01 — manifesto/permissão por rota | fechada (o schema de request/response ficou de fora, registrado) |
| F2-02 — RBAC, FK tenant-aware, ABAC | **fechada** |
| F2-02A — `herda_filhos` | **fechada** |
| F2-03 — licença | aberta |
| F2-04 — Legacy Full | aberta |
| F2-05 — break-glass | fechada |
| F2-06 — auditoria | aberta |
| F2-07 — segurança | parcial (anti-replay feito em F2-05) |
| F2-08 — matriz de testes por papel | parcial (o `support` saiu dos testes) |

`erp-novo/perda.sql` segue pré-existente e intocado.
