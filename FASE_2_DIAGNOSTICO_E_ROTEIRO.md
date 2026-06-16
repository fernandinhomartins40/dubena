# FASE 2 — Diagnóstico da stack + Roteiro de upgrade (5.8 → 12 · PHP 8.3)

> Reconhecimento sem alteração de código. Base: `composer.json`/`composer.lock`
> reais e varredura de uso no `ctrl-web/`. A rede de testes da Fase 1
> (`tests/Caracterizacao/`) + a suíte de Fluxo são o portão de cada salto.

---

## 1. Estado atual (medido, não suposto)

| Item | Valor real |
|---|---|
| PHP (constraint) | `>=7.4` · runtime container **7.4.33** |
| Laravel framework | **5.8.38** (`5.8.*`) |
| PHPUnit | 7.5.20 (`^7.5`) — **não roda em PHP 8.x** (erro `$GLOBALS`) |
| doctrine/dbal | `^2.13` (ok p/ PG) |
| Passport | `^3.0` (instalado 3.x) |

**Correções de compatibilidade PHP 8 já no código (medido):**
- `$var{}` (offset por chaves) — **0 restantes** (corrigido na F0: Sped/Util.php).
- `create_function` — **0**. `each()` função removida — **0** (os 2 achados são
  `Collection::each()`, método do framework, seguros).
- Lint `php -l` (PHP 8.2) do `app/` inteiro — **limpo**.
→ O código de aplicação está, em sintaxe, perto de PHP 8. O risco maior são as **libs**.

## 2. Libs que travam o salto de framework (o trabalho real)

| Lib | Constraint | Situação | Ação F2 |
|---|---|---|---|
| **laravelcollective/html** | `^5.8` | EOL; `Form::`/`Html::` em **327 views** | **MAIOR ITEM.** Manter shim (fork `spatie/laravel-html` ou pacote da comunidade que segue versões novas) OU migrar gradualmente para Blade components. 327 arquivos = trabalho dominante. |
| **maatwebsite/excel** | `~2.1.0` | EOL (2.1) | Subir p/ **3.x** (laravel-excel atual). API muda. Só **6** usos de `Excel::` → contido. |
| phpoffice/phpexcel | (1.8.2) | EOL/abandonado | **6** usos diretos `PHPExcel` → migrar p/ **PhpSpreadsheet** (já presente: `phpoffice/phpspreadsheet ^1.11`). |
| **venturecraft/revisionable** | `1.*` | chama `Event::fire()` (removido) — já quebra ao salvar entidade auditada | Subir versão compatível OU trocar por `spatie/laravel-activitylog`. (Achado da F1.) |
| **laravel/passport** | `^3.0` | acompanha o framework | Subir junto a cada salto; **não quebrar tokens** dos apps (testar getToken/pedido). |
| nfephp-org/sped-nfe | `5.2.1` (pin) | fiscal | Subir p/ versão compatível PHP 8; **validar emissão em homologação SEFAZ** (é o oráculo da tributação que a F1 não cobriu). |
| jenssegers/date | `^3.2` | EOL (virou jenssegers/date→Carbon) | Avaliar remoção (Carbon nativo) no salto. |
| eduardokum/laravel-boleto | `^0.9.4` | acompanhar | Bump no caminho. |
| zendframework/* | 3.x | Zend→Laminas | Renomear p/ laminas se algum salto exigir. |

## 3. Roteiro salto-a-salto (incremental, suíte verde a cada salto)

> Princípio: **um salto por vez**, deploy validado, sem mexer em regra de
> negócio (só compatibilizar). A cada salto: F1 + Fluxo verdes no container.

### Decisão (user): adiar Excel, fazer o salto de framework primeiro
> **Reconhecimento aprofundado mudou o plano:**
> 1. **Excel é maior que o estimado.** Não são 12 usos triviais —
>    `FechamentomensalgestaoController` tem dezenas de chamadas
>    `\PHPExcel_Style_*`/`Drawing`/`Border` gerando DRE/Balanço; vários `Excel::create`
>    estão comentados, mas Fechamentomensal/Reportclientes/ReportController estão
>    ATIVOS. Migrar é reescrita real de exports → **ADIADO** para quando o framework exigir.
> 2. **laravelcollective/html NÃO trava o salto.** Tem `v6.4.1` (Laravel 6) — as 327
>    views seguem com `Form::`/`Html::` só bumpando para `^6.0`. (Confirmado via
>    `composer show --all`.) maatwebsite/excel tem 3.x disponível.
> 3. PHPUnit em PHP 8 só a partir do Salto 2 (ver acima).
> → **Salto 0 vira: o próprio 5.8→6** (libs já têm versões compatíveis). Excel fica
>   para o trecho 6→8 ou quando travar.

### (adiado) Salto Excel — Libs isoláveis ainda em Laravel 5.8 / PHP 7.4
> **Correção de premissa (reconhecimento aprofundado):** "rodar a suíte em PHP 8"
> NÃO é antecipável isoladamente. Laravel 5.8 exige `php ^7.1.3` e aceita PHPUnit
> `^7.5|^8.0`; PHPUnit 8 também não roda em PHP 8.0+. PHPUnit 9+ (que roda em PHP 8)
> exige Laravel ≥ 8. Logo, **a suíte só roda em PHP 8 a partir do Salto 2** (quando
> o framework chega ao 8). O portão dos Saltos 0–1 continua sendo PHPUnit 7.5 no
> container PHP 7.4 (que já temos verde).
- [ ] Trocar **maatwebsite/excel 2.1 → 3.x** (6 usos) e **PHPExcel → PhpSpreadsheet**
      (6 usos) — isolado, ainda em Laravel 5.8, com a suíte 7.5 como portão. ← Salto 0.
- [ ] Decidir o destino do **laravelcollective/html** (shim vs. migração) — POC em
      1 view antes de comprometer as 327.
- [ ] (Opcional) subir PHPUnit 7.5 → 8.0 ainda em PHP 7.4 (Laravel 5.8 aceita) para
      reduzir o degrau no Salto 2 — mas NÃO habilita PHP 8.

### Salto 1 — Laravel 5.8 → 6 (LTS) + migração Excel (acoplados)
> **`composer why-not laravel/framework 6.0` revelou os bloqueios reais:**
> - `laravel/passport v3.0.2` → illuminate ~5.4 → **subir p/ Passport 7.x** (linha L6).
>   ⚠️ não quebrar tokens dos apps publicados (D13) — testar getToken/pedido.
> - `laravelcollective/html v5.8.1` → **^6.0** (v6.4.1 existe; 327 views seguem).
> - `maatwebsite/excel 2.1.30` → illuminate ^5.0 → **TRAVA o L6**. Tem de ir p/ 3.x.
>   Por isso a migração de Excel entra NESTE salto (decisão do user).
> - `milon/barcode 5.3.6` → illuminate 5.* → **subir p/ v6+**.
> - `nesbot/carbon` → L6 quer **^2.0** (tenho 1.39). Carbon 2 quebra `jenssegers/date 3.x`
>   (depende de Carbon 1) → **jenssegers/date 4.x ou remover** (usar Carbon nativo).
>
> **Ordem de execução (cada passo validado no container, suíte como portão):**
> 1. **laravel/helpers** `^1.4` (ponte p/ ~62 usos de array_*/str_* removidos no L6 —
>    evita reescrever 62 chamadas agora; refino p/ Str::/Arr:: fica p/ F5).
> 2. **Migrar exports** (ainda em 5.8, 3.x suporta L5.5+ — desacopla do salto):
>    - 5 `Excel::create` (Maatwebsite 2.1→3.x): Fechamentomensalgestao (DRE+Balanço),
>      Reportclientes, Reportclientesaniversariantes, ReportController.
>    - 6 controllers com `\PHPExcel_*`/Drawing → **PhpSpreadsheet** (já no composer).
> 3. **Bump composer.json**: framework `^6.0`, laravelcollective `^6.0`, passport `^7.0`,
>    milon/barcode `^6.0`, maatwebsite/excel `^3.1`, carbon `^2`, jenssegers/date `^4`
>    (ou remover). `composer update` no container; tratar conflitos residuais.
> 4. Ajustes L6: `Str`/`Arr` onde laravel/helpers não cobrir; config; TrustProxies.
> 5. **Portão:** suíte F1+Fluxo verde no container; exports gerando arquivo OK; deploy.

### Salto 2 — 6 → 7 → 8 + PHP 7.4 → 8.1
- [ ] Compatibilizar libs EOL definitivamente (Excel/Form/NFePHP/Passport).
- [ ] PHP 7.4 → 8.0 → 8.1 no Dockerfile (dev+prod). `each()`/`$var{}` já limpos.
- [ ] **Validar emissão fiscal em homologação SEFAZ** (cobre a tributação não
      caracterizada na F1).
- [ ] Portão: suíte + emissão homologada + apps publicados autenticando.

### Salto 3 — 8 → 9 → 10 → 11 → 12 + PHP 8.3
- [ ] Ajustes finais (casts, middleware, rotas, `Str`/`Arr`).
- [ ] Atualizar docker-compose (dev+prod) + workflows.
- [ ] Portão F2: Laravel 12 / PHP 8.3 em prod; tudo verde.

## 4. Riscos & ordem recomendada
1. **laravelcollective/html (327 views)** é o maior risco/esforço — decidir a
   estratégia ANTES de iniciar os saltos (Salto 0).
2. **PHPUnit em PHP 8** é pré-condição do portão — sem ele, perdemos a rede nos saltos.
3. **Fiscal (NFePHP/SEFAZ)** é validado por emissão em homologação (oráculo real).
4. **Apps publicados** (Passport/contratos D13) — testar a cada salto.

## 5. Próxima decisão do user
Aprovar o roteiro e escolher por onde abrir o Salto 0:
(a) subir PHPUnit p/ rodar em PHP 8, (b) trocar Excel/PHPExcel (contido, 12 usos),
ou (c) POC da estratégia do laravelcollective/html (maior item).
