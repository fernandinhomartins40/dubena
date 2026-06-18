# ÍNDICE — PRDs de MODERNIZAÇÃO (auditoria de código real · pós F0–F4A)

> Camada NOVA sobre os PRDs fiéis (`01..15_*.md`, que documentam o legado linha-a-linha).
> Estes `MODERN_*.md` foram escritos **lendo o código atual** (não a doc): comparam
> antes×agora (o que a F0–F4A já corrigiu vs. o que está aberto, com `arquivo:linha`),
> registram as REGRAS de negócio a preservar, e desenham o ALVO de UX/UI.
>
> **Norte (decisão do cliente):** sistema atual tem "cara de dinossauro" — menu no banco,
> telas fragmentadas, níveis de acesso complexos. ALVO = app moderno: **sidebar + header +
> conteúdo**, **página completa por entidade**, **navegação declarativa (sem menu-no-banco)**,
> **permissão por papel/recurso** (não menuusers).

## Guia mestre
- [MODERN_00_VISAO_UX.md](MODERN_00_VISAO_UX.md) — **paradigma-alvo** (ler primeiro): princípios
  de UX/UI, navegação declarativa, esboço do sidebar, modelo de permissões por papel.

## Por módulo (auditados)
| D | Módulo | Decisão herdada | Doc |
|---|--------|-----------------|-----|
| 01 | Vendas / Pedidos | REFATORAR Pedido/Caixa · REESCREVER resto | [MODERN_01](MODERN_01_vendas_pedidos.md) |
| 02 | NF-e / Fiscal | REFATORAR | [MODERN_02](MODERN_02_nfe_fiscal.md) |
| 03 | SPED | REFATORAR (motor) | [MODERN_03](MODERN_03_sped.md) |
| 04 | Financeiro / Tesouraria | REFATORAR núcleo · REESCREVER telas | [MODERN_04](MODERN_04_financeiro.md) |
| 05 | Clientes / CRM | REESCREVER (página completa) | [MODERN_05](MODERN_05_clientes.md) |
| 06 | Produtos / Estoque | REFATORAR motor · REESCREVER telas | [MODERN_06](MODERN_06_produtos_estoque.md) |
| 07 | Vale-Gás / Convênio | REFATORAR · REESCREVER | [MODERN_07](MODERN_07_valegas_convenio.md) |
| 08 | Colaboradores / RH | REESCREVER · REFATORAR comissões | [MODERN_08](MODERN_08_colaboradores.md) |
| 09 | Frota / Veículos | REESCREVER (ficha por veículo) | [MODERN_09](MODERN_09_frota.md) |
| 10 | Cadastros base / Geográfico | REESCREVER · REFATORAR Empresa | [MODERN_10](MODERN_10_cadastros_base.md) |
| 11 | Acesso / Permissões / Menu | REESCREVER (RBAC + nav declarativa) | [MODERN_11](MODERN_11_acesso_permissoes.md) |
| 12 | Relatórios / Dashboards | REESCREVER camada | [MODERN_12](MODERN_12_relatorios.md) |
| 13 | API Mobile | MANTER/REFATORAR (referência) | [MODERN_13](MODERN_13_api_mobile.md) |
| 14 | Monitoramento GPS | REESCREVER (UI) | [MODERN_14](MODERN_14_monitoramento.md) |
| 15 | Integrações / Misc | REESCREVER · DESCARTAR obsoleto | [MODERN_15](MODERN_15_integracoes_misc.md) |

## Conclusão transversal da auditoria (verificado no código)
- **A F0 fechou quase todos os bugs 🔴 que quebravam em produção** (Oracle→Postgres,
  typos fatais, debug exposto): Atualizarprecos UPDATE, Vendaativa/Veiculotrocaoleo/ReportCaixa
  rownum/CONNECT BY, Caixa `wwhere`/recibo, EstoqueProcessor empresa_id, gerarCodigo vale-gás,
  getEmpresas Monitora, dd/dump, Appnotification fcm, etc. — **confirmados corrigidos**.
- **Segurança:** S2/S7 (sha1 APP_KEY / HMAC 'secret') corrigidos (F0/F1); password em $hidden ✅;
  bypass AJAX agora atrás de kill-switch (F4A). **Resíduos abertos:** SQLi de filtro em
  `FinanceiroController:355-365`, whereRaw interpolado em alguns reports/Posvenda, `unique`-PG
  em ClienteRequest (fix em branch)/EmpresaBensRequest/NfRequest.
- **Dívida que sobra = ESTRUTURAL/UX** (o "ar de dinossauro"): God controllers, HTML/Form no
  backend, telas fragmentadas, menu-no-banco, permissões por menuusers. É exatamente o alvo
  da modernização Filament (página completa + nav declarativa + RBAC).
- **Já modernizado (F3/F4A):** painel Filament no ar, piloto Cidades/Bairros, UserResource
  (IDOR corrigido), gestão de permissões na UI. É a base para escalar o paradigma-alvo.
