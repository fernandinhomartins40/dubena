# F1-12 — Jobs, eventos e WebSockets sem envelope (item 3 do gate)

Data: 2026-08-28 (America/Sao_Paulo)

## Inventário

Seis jobs reais, mais dois traits. Quatro já haviam sido convertidos no microlote
anterior (`GeocodificarClienteJob`, `ImportarLogradourosJob`, `AtribuirPedidoJob`,
`EnviarPushJob`). Restavam dois:

| Job | Estado | Ação |
|---|---|---|
| `NotificarEstoqueBaixoJob` | sem envelope nenhum | convertido |
| `ExecutarMigracaoJob` | `platformJob = true` + valida admin ativo | correto, mantido |

Seis eventos, todos com `broadcastOn()` já namespaced por tenant
(`empresa.{id}.*`, `pedido.{id}.*`). Nenhum listener. A exposição do tempo real
não estava nos eventos — estava na autorização dos canais.

## O contraste que explica o item 3

`TenantEnvelopeJob` **lança** quando o envelope falta. O `TenantAwareJob` legado
faz o oposto, e diz isso no próprio docblock:

> Sem tenant ativo no dispatch (ex.: cron global/seed), capturarTenant() guarda
> null e aplicarTenant() é NO-OP

É o mesmo fail-open que a auditoria encontrou cinco vezes: ausência de contexto
lida como "não filtrar" em vez de "recusar".

## `NotificarEstoqueBaixoJob`

Recebia um `empresaId` solto e lia dados de negócio (`estoqueBaixo`,
`Empresa::find`, `User::where`) sem fronteira alguma. Convertido para o padrão
já estabelecido: captura no construtor quando o enforcement está ligado, reaplica
no `handle()`.

Com uma diferença que os outros jobs não têm: **ele carrega um `empresaId`
próprio**. Quem enfileira escolhe esse número, então ele não é credencial — o job
confere contra o grant antes de executar:

```php
TenantEnvelope::fromPayload($this->tenantEnvelopePayload)
    ->requireOperation($this->empresaId);
```

O push aninhado (`PushService` → `EnviarPushJob`) herda o envelope corretamente:
dentro de `withinTenantEnvelope`, o `TenantEnvelopeDispatch::capture()` devolve o
envelope corrente do runtime em vez de exigir `Auth::user()` — que não existe
dentro de um worker.

## O buraco do tempo real

`routes/channels.php` autorizava exclusivamente pelo modelo **legado**:

```php
return method_exists($user, 'podeAcessarEmpresa') && $user->podeAcessarEmpresa($empresaId);
```

`podeAcessarEmpresa()` aceita um vínculo em `empresa_user` e, antes disso,
devolve `true` incondicionalmente para quem tem a flag `support`. O arquivo não
tinha uma única referência ao envelope de tenant.

Consequência: com o enforcement ligado, um usuário sem grant aprovado nenhum
continuaria entrando no canal ao vivo de outro tenant. **O dado não vazaria pela
RLS — vazaria pelo WebSocket.**

Os canais de pedido tinham um segundo problema, independente do SaaS: filtravam
por `where('empresa_id', $user->empresa_id)`, ou seja, apenas a empresa *padrão*
do usuário, ignorando o próprio multi-empresa que o canal de empresa suporta.

### Correção

Uma função de fronteira compartilhada pelos quatro canais. Com enforcement
ligado, resolve pelo mesmo `TenantEnvelopeResolver` do HTTP e dos jobs e fecha em
`TenantAccessDeniedException`; desligado, preserva o comportamento legado para
não derrubar a operação atual antes da conversão. Nos canais de pedido a empresa
passou a vir **do pedido**, validada contra o grant.

## Evidência

- `JobsTratamentoFalhaTest`: **9 testes / 48 assertions**. O teste novo varre os
  jobs em vez de citar um por nome — job novo sem envelope, e sem se declarar de
  plataforma, reprova.
- `TempoRealTest`: **6 testes / 19 assertions**, incluindo o caso novo: usuário
  com vínculo legado perfeito **e flag `support`** é recusado nos dois canais de
  empresa quando o enforcement está ligado.
- Suíte integral: **1.331 passes, 4.196 assertions, 8 skips, zero falhas**
  (antes: 1.328 / 4.176).
- Pint aprovado nos arquivos do microlote.

## O que isto NÃO conclui

Continuam abertos no gate F1: os demais grafos pai-filho fora dos já protegidos
(item 4), o registro de rollback/snapshot de grants (item 5) e a execução em
homologação com a role de runtime (item 1). `erp-novo/perda.sql` segue
pré-existente e intocado.
