# F2-01 — Toda rota admin declara sua permissão

Data: 2026-08-30 (America/Sao_Paulo)

O gate F2 exige que "rota não catalogada falhe no CI". O manifesto já existia e
já pegava rota **removida ou nova** — mas só sabia *quais rotas existem*, não *o
que cada uma exige*. Essa diferença tinha custo real.

## O furo

`LookupController` era o único controller do Admin sem `autorizar()` em método
nenhum. Ele serve 33 lookups para os selects da SPA — entre eles `clientes`,
`produtos`, `contas`, `colaboradores` e `usuarios`.

Medido, com o mesmo usuário sem papel:

```
GET /api/admin/clientes         -> 403
GET /api/admin/lookups/clientes -> 200, com os 3 clientes
```

A mesma informação, pela porta de trás. Valia igual para conta bancária, plano de
contas e folha de colaboradores.

### Correção

Cada lookup declara a permissão de **leitura do módulo dono do dado** — não uma
permissão genérica: quem enxerga cliente não passa a enxergar conta bancária. A
autorização roda **depois** de normalizar o alias e **antes** de qualquer leitura.

Slug **conhecido** sem permissão declarada é recusado (default-deny): lookup novo
não nasce aberto por esquecimento.

Slug **inexistente** segue devolvendo lista vazia com 200. Minha primeira versão
trocou isso por 404 e derrubou um teste que documentava o contrato — um
`AsyncSelect` da SPA tolera lista vazia, não um 404. Mudar o contrato não era
necessário para fechar o furo, então foi revertido.

## O guardião

`ApiManifestGerar::rotasSemPermissaoDeclarada()` varre as rotas `auth:sanctum` e
lê o código-fonte do método do controller. `ApiContratoDriftTest` reprova
qualquer rota `api/admin/*` que não declare permissão nem esteja na lista de
exceções justificadas.

### Um falso positivo que precisou ser corrigido

A primeira versão olhava só o corpo do método e acusou o `GeoController` — que
**autoriza corretamente**, dentro do helper `cfg()`, recebendo a permissão por
parâmetro. Acusar quem está certo faz o detector ser ignorado, então ele passou a
seguir helpers privados da própria classe. Isso levou o recorte admin de 17 para
**11 rotas**, todas legítimas.

### As 11 exceções, e por que cada uma vale

| Rota | Motivo |
|---|---|
| 2FA (setup/confirmar/desabilitar/status) | o usuário administra a **própria** credencial; exigir permissão de módulo impediria alguém sem papel de proteger a conta |
| Sessões (listar/revogar/revogar-outras) | idem — sessão própria |
| `GET assinatura` | o admin vê o que a **própria** empresa contratou |
| `GET dashboard/resumo` | home de qualquer logado; o serviço já escopa pela empresa ativa |
| `GET vale-gas/situacoes` | devolve valores de um enum, sem dado de empresa |
| `POST empresas/{id}/ativar` | a fronteira é `podeAcessarEmpresa`, não RBAC |

`api/app/v1/*` fica fora do recorte: a fronteira ali é o papel do token
(middleware `approle`), não o RBAC. As pontes legadas têm a sua.

## Prova de que o guardião guarda

Removi temporariamente a autorização do `LookupController` e o teste **reprovou**,
apontando a rota exata:

```
- GET api/admin/lookups/{tipo} (LookupController@index)
```

Um guardião que nunca falha não guarda nada; este foi verificado nos dois
sentidos.

## Evidência

- `LookupAutorizacaoTest`: 4 testes — lookup nega o que a listagem nega, libera
  para quem tem a permissão, exige a permissão do módulo **dono**, e recusa slug
  sem permissão declarada.
- `ApiContratoDriftTest`: 2 testes (drift + permissão declarada).
- Suíte integral verde nos dois modos.
- Pint aprovado.

## O que este microlote NÃO fez

A tarefa F2-01 completa pede também **schema de request/response por rota** e
**catálogo consumido pelo frontend**. Ficou fora: exige contrato de tipos que a
SPA leia, e o valor imediato estava na permissão — que é o que F2-02 consome e o
que fechava um vazamento real.

Restam da F2: o restante de F2-01, F2-02/02A (RBAC e `herda_filhos`), F2-03
(licença), F2-04 (Legacy Full), F2-06 (auditoria), F2-07 e F2-08.

`erp-novo/perda.sql` segue pré-existente e intocado.
