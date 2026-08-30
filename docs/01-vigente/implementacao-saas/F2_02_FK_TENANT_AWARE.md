# F2-02 — Validação de FK respeita a fronteira do tenant

Data: 2026-08-30 (America/Sao_Paulo)

A tarefa pede três coisas: autorização comportamental em cada porta, **validações
de FK tenant-aware** e **negar condição ABAC desconhecida**. Medi as três antes de
mexer em qualquer uma.

## O que já estava certo

**Condição ABAC desconhecida já é negada.** `PolicyEvaluator::avaliarCondicao`
termina em `default => false` — tipo de condição que o código não conhece reprova,
não passa. Nada a fazer.

**Transferência entre contas já estava contida.** `CaixaController::transferir`
recebe ids no body com `exists:contas,id` global, mas o `CaixaService` revalida
antes do primeiro movimento, com comentário nomeando o achado (`F0-04/A-12.2`).
Dinheiro estava protegido; não mexi.

## O que estava aberto

`exists:clientes,id` valida contra a tabela **inteira**. Medido:

```
POST /api/admin/pedidos  (empresa A, cliente_id da empresa B)  -> 201
```

E, pior, **continuava 201 entre tenants distintos com o enforcement ligado**. A
RLS protege a leitura; a validação corria por fora, e a FK nascia cruzada — um
pedido de uma revenda referenciando o cliente de outra.

São 135 ocorrências de `exists:` em `app/Http/`.

## A correção

`App\Rules\ExisteNoTenant` valida através do **model**, não da tabela. Assim o
escopo vem de quem já o declara — `BelongsToTenant` filtra por empresa,
`BelongsToGrupo` por grupo, e model sem escopo continua valendo na tabela toda,
que é o correto para catálogo de plataforma.

Isso evita reescrever 135 regras à mão com o filtro certo em cada uma: os 126
models que declaram escopo passam a ser a fonte.

A mensagem de erro é a mesma do `exists` nativo, de propósito: pelo texto, quem
está de fora não aprende se o registro existe em outro tenant.

### Onde foi aplicada

| Request | Campos |
|---|---|
| `PedidoRequest` | `cliente_id`, `pedidosituacao_id`, `pedidooperacao_id`, `setor_id`, `itens.*.produto_id` |
| `ClienteRequest` | `tipopessoa_id`, `segmento_id`, `cidade_id`, `bairro_id`, `rua_id`, `convenio_id`, `telefones.*.telefonetipo_id` |

`convenio_id` merece nota: aponta para **outro cliente**, então sem escopo uma
empresa amarrava seu cliente ao convênio de um cliente alheio.

`atendente_user_id` e `entregador_user_id` seguem com o `exists` nativo: `User`
não tem escopo de tenant, e a validação correta ali é outra (pertencer à empresa
via `empresa_user`), que não é o que esta regra resolve.

## Evidência

- `FkTenantAwareTest`: 5 testes — recusa cliente de outra empresa, cliente de
  outro **tenant**, produto de outra empresa, convênio de outra empresa; e
  confirma que o caminho legítimo continua criando com 201 (a regra não pode
  virar bloqueio).
- Suíte integral verde nos dois modos.
- Pint aprovado.

## O que este microlote NÃO fez

Restam as demais ocorrências de `exists:` — `ColaboradorController`,
`ComodatoController`, `VeiculoController`, `AlcadaDescontoController`,
`LogradouroOficialController`, `PagamentoController` e os controllers do app
mobile. A ferramenta está pronta e provada; aplicá-la é trabalho mecânico, mas
cada arquivo precisa de leitura própria para não trocar por escopo errado —
`exists:users,id` foi justamente o caso onde a troca automática estaria errada.

A tarefa F2-02 também pede "autorização comportamental em cada porta de mutação e
leitura sensível". A varredura de F2-01 já mostrou que todas as rotas admin
autenticadas declaram permissão, com 11 exceções justificadas; o que falta é a
matriz por papel real, que é F2-08.

`erp-novo/perda.sql` segue pré-existente e intocado.
