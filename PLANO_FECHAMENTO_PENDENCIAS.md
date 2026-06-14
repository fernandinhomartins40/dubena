# Plano de Fechamento de Pendências — pré-Multi-tenant

> **Documento de execução derivado da auditoria `STATUS_PLANO.md` (2026-06-14).**
> Objetivo: fechar, na ordem certa e com portão de saída objetivo, **tudo o que
> a auditoria marcou como 🟡/❌**, para só então liberar a Fase 6 (multi-tenant).
>
> Princípio mantido do plano original: **Segurança → Testes/baseline → Dados →
> (Framework) → Multi-tenant**. Nunca multi-tenant sobre base com buraco aberto.
>
> Estado-base: ctrl-web em produção (gasemcasa.com), 3 sistemas unificados num
> único Postgres (public=ERP, api=App\Api, monitora=App\Monitora), Laravel 5.8 /
> PHP 7.4. Deploy via GitHub Actions (self-hosted runner). Multi-tenant ADIADO.

---

## Visão geral das frentes

| Frente | Tema | Bloqueia MT? | Depende de prod? | Estimativa |
| --- | --- | --- | --- | --- |
| **A** | Segurança do app mobile (TLS + segredos) | ✅ Sim (S2 crítico) | Não | 2–4 dias |
| **B** | Rotação de credenciais expostas | ✅ Sim | Parcial | 2–3 dias |
| **C** | Auditoria SQLi/IDOR residual | ✅ Sim | Não | 3–5 dias |
| **D** | Baseline fiscal/financeiro (golden-master) | ✅ Sim | **Sim** (dados) | 1–3 semanas |
| **E** | Objetos ocultos do Oracle (triggers/proc/views) | ✅ Sim | **Sim** (Oracle prod) | 1–2 semanas |
| **F** | Migração de dados reais (ETL anonimizado) | ✅ Sim | **Sim** | 1–2 semanas |
| **G** | Unificação fina (*_importacao, contrato do app) | 🟡 Não | Parcial | 1 semana |
| **H** | Upgrade Laravel 6→8 / PHP 8 | 🟡 Não | Não | projeto à parte |
| **F6** | Multi-tenant (tenant_id + RLS) | — | Sim | só após A–F |

> Ordem recomendada: **A → C → B → D/E/F (exigem janela de prod) → G → F6**.
> A e C são autocontidas (sem prod) e fecham os riscos mais imediatos.

---

## FRENTE A — Segurança do App Mobile (autocontida, CRÍTICA)
**Por quê primeiro:** S2 do relatório (HTTP sem TLS com pagamento em jogo) é o
buraco crítico ainda 100% aberto, e NÃO depende de acesso a produção/Oracle.

### Ações (app-gas-em-casa)
- [ ] Remover `NSAllowsArbitraryLoads: true` (`app.json:28` + `ios/.../Info.plist`).
- [ ] Remover `allowHttp: true` (`app.json:52`) e `usesCleartextTraffic="true"`
      (`android/app/src/main/AndroidManifest.xml` e `.../debug/`).
- [ ] Garantir que TODAS as URLs de API são `https://` (varrer `http://`).
- [ ] Mover `app_key` / Google Maps key / FCM para **EAS Secrets** (tirar do
      código/`app.json` versionado).
- [ ] MMKV com `encryptionKey` (storage local criptografado).
- [ ] Remover `console.*` e `dd()`/debug de build de produção.
- [ ] Validar build EAS de staging apontando para a API por HTTPS.

### 🚪 Portão de Saída
- App não conecta em HTTP em nenhuma plataforma; segredos fora do código;
  build de staging sobe e fala com a API só por TLS.

---

## FRENTE B — Rotação de Credenciais Expostas
**Por quê:** o histórico do Git original guarda chaves reais; trocar o `.env`
não basta — as credenciais antigas continuam válidas até serem giradas na origem.

### Ações (checklist base em `SEGREDOS_LOCAIS.md`)
- [ ] Inventariar tudo que já vazou: bancos, `APP_KEY`, `APP_TOKEN_KEY`,
      Google Maps/Roads, FCM, Traccar, senha do certificado NF-e.
- [ ] Rotacionar **na origem** cada credencial (gerar nova, revogar antiga).
- [ ] Atualizar `/opt/dubena-env/*.env` na VPS + EAS Secrets do app.
- [ ] `APP_KEY` novo: reencriptar o que foi gravado com a chave antiga
      (certificado PFX, e-mail) — usar o fallback legado já existente em
      `customCrypt`/`customDecrypt` para migrar sem perder dados.
- [ ] Confirmar que nenhum segredo válido está no working tree (só fakes).

### 🚪 Portão de Saída
- Toda credencial que esteve no Git foi girada; produção/staging usando as novas;
  dados encriptados com chave antiga migrados para a nova.

---

## FRENTE C — Auditoria SQLi / IDOR residual (autocontida)
**Por quê:** a auditoria achou **105 `whereRaw` com interpolação em 47 arquivos**.
Maioria interpola ids de sessão (baixo risco), mas precisa triagem para separar
o que vem de **input do usuário** (alto risco).

### Ações
- [ ] Triar os 105 `whereRaw($...)`: classificar origem do valor
      (sessão/constante = ok · `$_GET`/`$request`/`Input` = parametrizar).
- [ ] Parametrizar (bindings) todo `whereRaw`/`DB::raw`/`DB::select` que receba
      input do usuário. Prioridade: SearchController (13), MobileRepository (8),
      FinanceiroController (7), App\Api\Repository\*.
- [ ] Revisar IDOR: `veiculos/dropdown` e telas que listam por id sem checar
      dono/tenant; confirmar filtro por empresa/grupo do usuário autenticado.
- [ ] Middleware `access` (App\Api): garantir que autoriza, não só loga.
- [ ] Teste de segurança: tentativa de injeção/escopo cruzado retorna negado.

### 🚪 Portão de Saída
- Nenhum `whereRaw` com input de usuário sem binding; IDOR fechado nas telas de
  listagem; teste de injeção/escopo cruzado passa.

---

## FRENTE D — Baseline Fiscal/Financeiro (golden-master)  ⚠️ exige dados
**Por quê:** é a maior lacuna real. A cobertura atual valida "tela abre" (200),
NÃO "o imposto/total é o mesmo de antes". Sem baseline, não há prova de que a
migração Oracle→Postgres + traduções de SQL não alteraram cálculos.

### Ações
- [ ] Definir os fluxos-âncora: pedido→estoque/financeiro, NF-e/NFC-e + impostos,
      SPED Fiscal/Contribuições, PIX, boleto, conciliação, fechamento DRE/Balanço.
- [ ] Capturar **golden-master**: dado de entrada fixo → saída esperada (valores
      numéricos), idealmente extraída da PRODUÇÃO Oracle atual (mesma entrada).
- [ ] Rodar os mesmos cenários no Postgres unificado e **comparar valor a valor**.
- [ ] Investigar e corrigir qualquer divergência (é aqui que CONNECT BY/relatórios
      fiscais traduzidos provam se estão corretos por VALOR, não só sintaxe).
- [ ] Integrar à suíte/CI como rede de regressão permanente.

### 🚪 Portão de Saída
- Fluxos fiscais/financeiros críticos com golden-master que casa Oracle×Postgres;
  baseline congelado no CI.

---

## FRENTE E — Objetos Ocultos do Oracle  ⚠️ exige acesso ao Oracle prod
**Por quê:** triggers/procedures/views/synonyms do Oracle nunca foram versionados.
Pode haver lógica fiscal/automação em trigger que não veio na migração de schema.

### Ações
- [ ] Extrair do Oracle de produção: `ALL_TRIGGERS`, `ALL_SOURCE` (procedures/
      functions/packages), `ALL_VIEWS`, `ALL_SYNONYMS` do schema do ERP.
- [ ] Catalogar o que é lógica de negócio (precisa reimplementar) vs. acessório.
- [ ] Reimplementar no Postgres (trigger/função PL/pgSQL) ou na aplicação, o que
      for regra ativa; versionar em migrations/DDL.
- [ ] Cruzar com a Frente D (golden-master detecta efeito de trigger ausente).

### 🚪 Portão de Saída
- Todo objeto de banco com lógica ativa está versionado e reproduzido no Postgres
  (ou conscientemente descartado), confirmado pelo baseline.

---

## FRENTE F — Migração de Dados Reais (ETL anonimizado)  ⚠️ exige janela
**Por quê:** schemas api/monitora nascem vazios (seeders só admin); relatórios
hierárquicos e o baseline precisam de massa de dados representativa.

### Ações
- [ ] ETL Oracle→Postgres dos dados do ERP (public), com **anonimização** de PII
      (CPF, telefone, e-mail, cartão) para staging — nunca dump cru (LGPD).
- [ ] Migrar dados do monitora (schema monitora) e da API (schema api).
- [ ] Validar integridade: contagens, FKs, somatórios financeiros batem.
- [ ] Rodar a suíte + baseline (Frente D) sobre os dados migrados.

### 🚪 Portão de Saída
- Staging com dados reais anonimizados; integridade validada; relatórios fiscais
  conferidos por valor.

---

## FRENTE G — Unificação Fina (não bloqueia, mas encerra a Fase 5)
- [ ] Substituir tabelas espelho `*_importacao` por leitura direta do ERP;
      reapontar os repositories da API.
- [ ] Apontar uma build de staging do app para a API unificada e validar os
      contratos publicados (v2/order/root, client/getById, getToken, etc.).

### 🚪 Portão de Saída
- Sem sincronização redundante; app de teste funcionando ponta-a-ponta na API
  unificada com contratos preservados.

---

## FRENTE H — Upgrade Laravel 6→8 / PHP 8 (não bloqueia; projeto à parte)
- [ ] Subir 5.8 → 6 LTS → 7 → 8, com PHP 8, validando a suíte a cada salto.
- [ ] Destravar deps acopladas (laravelcollective, maatwebsite/excel, sped).
- [ ] Substituir helpers globais removidos no 6.0 (array_get/str_random, etc.).

### 🚪 Portão de Saída
- Backend em Laravel 8 / PHP 8 com suíte verde.

---

## FASE 6 — Multi-tenant (só LIBERA após A–F)
Conforme o plano original (tenant_id + RLS no Postgres + middleware de tenant +
onboarding + testes de isolamento entre 2 tenants). **Gate de entrada:** Frentes
A, B, C, D, E, F com portão de saída fechado.

---

## Ordem operacional sugerida
```
A (app TLS/segredos)   ─┐ autocontidas, sem prod — começar JÁ
C (SQLi/IDOR)          ─┘
B (rotação credenciais) ── parcialmente autocontida
        │
   [janela de acesso à produção Oracle]
        │
E (objetos ocultos) ─┐
F (dados reais ETL) ─┤ interdependentes
D (baseline fiscal) ─┘ (D usa E e F)
        │
G (unificação fina) + H (Laravel 8) — em paralelo, quando der
        │
   FASE 6 — Multi-tenant
```

> **Regra de ouro mantida:** nenhuma frente que dependa de produção começa sem
> janela de acesso controlado; o multi-tenant só inicia com A–F verdes.
