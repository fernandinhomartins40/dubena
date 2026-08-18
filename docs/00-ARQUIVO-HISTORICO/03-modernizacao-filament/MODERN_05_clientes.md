# MODERNIZAÇÃO (auditoria de código) — Clientes / CRM · D05

> Auditoria do CÓDIGO REAL (pós F0–F4A) vs. PRD fiel [`05_clientes.md`](05_clientes.md).
> Módulo das telas que o cliente está testando agora (cadastro de cliente).

---

## 1. ANTES × AGORA (verificado)

| Item (PRD fiel) | Original | HOJE (auditado) | Ref. |
|---|---|---|---|
| `DB::rdollback()` (Posvenda store) | 🔴 fatal | ✅ **corrigido** (`DB::rollback`) | `PosvendaController.php:108` |
| `ClienteRequest` unique → 500 no PG | (não no PRD; achado F4) | 🟡 **corrigido em branch** (id vazio→NULL) NÃO mesclada | `ClienteRequest.php` (`fase-4-fix-cliente-unique-pg`) |
| SQLi `whereRaw` interpolado | 🔴 | ❌ **ABERTO** (vários) | `ClienteController.php:1046`, `PosvendaController.php:139` |
| Sobreposição de período furada (AND/OR sem parênteses) | 🟠 | ❌ **ABERTO** | `PosvendaController.php:139` |
| `Clientecontato` scaffold vazio | 🟡 morto | ❌ **intacto** (7 métodos só com `//`) | `ClientecontatoController.php:17-83` |

---

## 2. DÍVIDA DE UX/UI E FLUXO

- **`ClienteController` (1318 linhas)** — God controller: CRUD + busca + convênio +
  endereço + telefones + produtos do convênio num só lugar, com HTML/Form no backend.
- **Cadastro de cliente = formulário gigante único** com muitos selects dependentes
  (estado→cidade→bairro→rua) carregados via AJAX; sem etapas, sem autosave, sem máscara
  consistente. Vários campos fiscais/convênio aparecem mesmo quando não se aplicam.
- **Lista de clientes só aparece após buscar** (`index` só lista se houver `name`/`cod`) —
  comportamento confuso para o usuário (tela "vazia" ao abrir). Verificado no controller.
- **Contatos do cliente (CRM)**: scaffold vazio — não há gestão real de interações/histórico.

---

## 3. REGRAS A PRESERVAR

- Escopo por empresa/grupo (multi-empresa) em toda busca/listagem.
- Cadeia geográfica estado→cidade→bairro→rua e vínculos de convênio (limite, fechamento).
- Unicidade de CPF/CNPJ/RG/IE **por empresa** (a regra existe; só estava quebrada no PG).

---

## 4. PÁGINA-ALVO: "tudo do cliente em um lugar" (ver MODERN_00 §1-2)

> Exemplo que o cliente citou: hoje é "menu Cadastro de Cliente → lista → botão Novo →
> outra tela; endereço/telefone/contato em telas separadas". O ALVO é **uma página de
> Cliente** onde se faz tudo.

- **ClienteResource** (item do grupo *Cadastros* no sidebar declarativo — sem menu-no-banco):
  - **Lista** paginada/filtrável que aparece ao abrir (não exige busca prévia); busca global.
  - **Ficha do cliente** = form em **abas/seções**: Dados · Endereço · Fiscal · Convênio.
    Campos fiscais/convênio só visíveis conforme tipo/flags (reactive). Selects dependentes
    nativos (cidade→bairro→rua) sem AJAX manual.
  - **Relacionados na mesma página** via RelationManagers (abas): **Telefones**, **Contatos/
    Interações (CRM)**, **Pedidos** do cliente, **Financeiro** (títulos/parcelas) do cliente.
    Acaba a navegação entre telas soltas.
- Domain: regras de convênio/limite em Service testável.
- Permissão por ação (Policy/role), não por linha de menu.

---

## 5. PENDÊNCIAS RESIDUAIS (arquivo:linha)

- `ClienteController.php:1046` — `whereRaw("empresa_id in (... user_id = " . Auth::id() ...)")`
  interpolado → parametrizar (não é SQLi de input externo, mas má prática).
- `PosvendaController.php:139` — `whereRaw("grupo_id = $this->grupo_id and ... or ... or ...")`:
  interpolação + **precedência AND/OR sem parênteses** (sobreposição de período furada).
- `PosvendaController.php:54` — `to_date(...)` interpolado em whereRaw (datas).
- `ClientecontatoController.php` — scaffold 100% vazio → reescrever como CRM ou remover.
- `ClienteRequest` fix do 500 (PG) está em branch **não mesclada** — mesclar para destravar
  cadastro de cliente.

> **Decisão herdada:** REESCREVER (faseado). Cliente é candidato ótimo a 2º recurso Filament
> (depois do piloto Cidades/Bairros da F3), por ser cadastro central e de alto uso.
