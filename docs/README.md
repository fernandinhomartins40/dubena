# Documentação — Dubena / ERP-NOVO

Organizada por finalidade. A raiz desta pasta contém **apenas este índice** —
todo documento vive numa subpasta classificada.

---

## 🎯 Comece aqui

| Sua pergunta | Documento |
|---|---|
| **O que falta para virar o sistema?** | [gauntlet/GUIA_DO_DONO.md](gauntlet/GUIA_DO_DONO.md) — em linguagem comum, com as tarefas em ordem |
| O que já foi entregue? | [gauntlet/STATUS_FINAL.md](gauntlet/STATUS_FINAL.md) |
| Como fazer a virada, passo a passo | [../deploy/CUTOVER_RUNBOOK.md](../deploy/CUTOVER_RUNBOOK.md) |

---

## As pastas

| Pasta | O que é | Usar? |
|---|---|---|
| [gauntlet/](gauntlet/) | **Auditoria e plano de produção VIGENTES.** A verdade de hoje sobre o que falta, o que foi decidido e por quê. | ✅ **SIM — fonte de verdade** |
| [01-vigente/](01-vigente/) | **Contratos de implementação por módulo** (`IMPL_*.md`) e specs ativas (controle de acesso, integrações multi-tenant, design system). | ✅ **SIM — contrato** |
| [06-runbooks/](06-runbooks/) | Procedimentos operacionais de homologação e go-live. | ✅ SIM (operação) |
| [02-auditoria-legado/](02-auditoria-legado/) | PRDs fiéis (linha a linha) do sistema legado — referência do comportamento original. Vale enquanto o legado existir. | 📖 referência |
| [00-ARQUIVO-HISTORICO/](00-ARQUIVO-HISTORICO/) | **Fases concluídas ou abandonadas.** Registram *por que* as decisões foram tomadas, não o que vale agora. | 🗄️ **NÃO é fonte de verdade** |

> ⚠️ **Para auditorias:** a fonte da verdade é **o código**, não estes documentos.
> `00-ARQUIVO-HISTORICO/` guarda registros do passado que divergem do estado
> atual **de propósito** — não os trate como especificação vigente. Cada pasta
> lá dentro está explicada em
> [00-ARQUIVO-HISTORICO/LEIA-ME.md](00-ARQUIVO-HISTORICO/LEIA-ME.md).

---

## Por onde começar, conforme o que você vai fazer

### Executar a virada para produção
1. [gauntlet/GUIA_DO_DONO.md](gauntlet/GUIA_DO_DONO.md) — as tarefas pendentes,
   em ordem, com o comando de cada uma
2. [../deploy/CUTOVER_RUNBOOK.md](../deploy/CUTOVER_RUNBOOK.md) — o roteiro da
   noite da virada: 15 passos, 3 portões, 3 níveis de rollback
3. [gauntlet/PENDENCIAS_OPERACIONAIS_F3.md](gauntlet/PENDENCIAS_OPERACIONAIS_F3.md)
   — detalhe técnico das chaves externas (Firebase, certificado A1, PIX)

### Implementar ou alterar um módulo
1. [01-vigente/IMPL_00_INDICE.md](01-vigente/IMPL_00_INDICE.md) — os PRDs por
   módulo. Cada um é o **contrato** (paridade + definição de pronto)
2. [01-vigente/PLANO_CONTROLE_ACESSO_HIERARQUIA.md](01-vigente/PLANO_CONTROLE_ACESSO_HIERARQUIA.md)
   — RBAC/ABAC, hierarquia, 2FA, auditoria (fases A0–A7 concluídas)
3. [01-vigente/INTEGRACOES_MULTITENANT.md](01-vigente/INTEGRACOES_MULTITENANT.md)
   — como segredos são resolvidos: empresa → grupo → plataforma, *fail-closed*

### Entender por que algo é como é
1. [gauntlet/AUDITORIA.md](gauntlet/AUDITORIA.md) — a auditoria que originou o
   plano de produção
2. [gauntlet/T5.1_ACHADOS.md](gauntlet/T5.1_ACHADOS.md) — reauditoria do débito
   técnico; contém a divergência de saldo herdada do legado (§4)
3. [gauntlet/TRIAGEM_LACUNAS_F4.md](gauntlet/TRIAGEM_LACUNAS_F4.md) — cada lacuna
   do legado com veredito: implementar, adiar ou aposentar
4. [00-ARQUIVO-HISTORICO/](00-ARQUIVO-HISTORICO/) — quando a pergunta for sobre
   uma decisão antiga (por que Filament foi descartado, por que RLS)

### Conhecer o comportamento do sistema legado
[02-auditoria-legado/00_INDICE.md](02-auditoria-legado/00_INDICE.md) — 15
domínios documentados linha a linha.

---

> **Convenção:** a raiz do repositório mantém só `README.md` e
> `SEGREDOS_LOCAIS.md`; a raiz de `docs/` mantém só este `README.md`. Todo novo
> documento entra numa subpasta classificada — nada solto.
>
> Quando um documento deixar de valer, **mova para `00-ARQUIVO-HISTORICO/`** e
> registre o motivo no `LEIA-ME.md` de lá. Apagar perde a justificativa; deixar
> junto do vigente cria confusão.
