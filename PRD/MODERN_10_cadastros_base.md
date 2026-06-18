# MODERNIZAÇÃO (auditoria de código) — Cadastros base / Geográfico · D10

>
> **Paradigma-alvo:** ver [`MODERN_00_VISAO_UX.md`](MODERN_00_VISAO_UX.md) — página
> completa por entidade (abas/RelationManagers), navegação DECLARATIVA (sem menu-no-banco),
> permissão por recurso/ação (roles). O layout/fluxo legado é DESCARTADO; só as REGRAS de
> negócio são preservadas.
> Auditoria do CÓDIGO REAL (pós F0–F4A) vs. PRD fiel [`10_cadastros_base.md`](10_cadastros_base.md).
> Inclui Empresa/EmpresasGrupo/Empresaconfig, geográfico (Cidade/Bairro/Rua), Banco, Região.

---

## 1. ANTES × AGORA (verificado)

| Item (PRD fiel) | Original | HOJE (auditado) | Ref. |
|---|---|---|---|
| `updateLob()` (driver Oracle) p/ logo | 🔴 quebrado (8 lugares) | ✅ **corrigido** (helper BlobWriter/bytea na F0) | ausente em `Empresa.php`/`EmpresasGrupo.php` |
| `ORA-02292` em delete geográfico | 🔴 | ✅ **corrigido** → SQLSTATE 23503 | ausente |
| Empresaconfig condição invertida | 🟠 | ✅ **corrigido** (F0) | `EmpresaconfigController.php:467` (normalizado) |
| Cidade/Bairro piloto Filament | (não existia) | ✅ **novo** (F3: `/admin/cidades`,`/admin/bairros`) | `app/Filament/Resources/Cidade|BairroResource` |
| `EmpresaBensRequest` unique PG | (achado F4) | ❌ **ABERTO** (mesmo bug `id` vazio→`<> ''`) | `EmpresaBensRequest.php` |
| Array com chave `ativo` duplicada | (achado agora) | 🟡 menor (`['ativo'=>1,...,'ativo'=>1]`) | `EmpresaconfigController.php:238` |

---

## 2. DÍVIDA DE UX/UI E FLUXO

- Cadastros geográficos (Cidade/Bairro/Rua) são CRUDs AdminLTE clássicos; o **piloto F3**
  já mostrou o padrão novo para Cidade/Bairro (Filament) — falta Rua e os demais.
- **Empresa/Empresaconfig**: form muito longo com dezenas de flags de configuração
  (fiscal, estoque, boleto, NFC-e) sem agrupamento claro — difícil achar a config certa.
- HTML/Form no backend e `destroy` retornando `<br/>` (padrão do projeto).

---

## 3. REGRAS A PRESERVAR

- Logo da empresa em `bytea` (BlobWriter) — não regredir.
- Integridade referencial geográfica (cidade↔bairro↔rua↔setor) com erro amigável (23503).
- `empresaconfig` é lida em Session no login e por muitos módulos — contrato sensível.

---

## 4. BLUEPRINT DE MODERNIZAÇÃO (Filament 3)

- **Geográfico**: completar os Resources (Cidade ✅, Bairro ✅, Rua, Região) com selects
  dependentes nativos; é a "vitrine" do padrão novo (baixo risco) — Bloco B do plano.
- **EmpresaResource**: form em abas (Dados, Fiscal, Estoque, Boleto, NFC-e) com as flags
  agrupadas; upload de logo nativo (bytea). Empresaconfig como aba/recurso relacionado.
- **Banco/Layoutbanco**: CRUDs simples Filament.

---

## 5. PENDÊNCIAS RESIDUAIS (arquivo:linha)

- `EmpresaBensRequest.php` — mesmo bug `unique` com id vazio → `"id" <> ''` (500 no PG ao
  salvar bem da empresa). **Aplicar o mesmo fix do ClienteRequest.**
- `EmpresaconfigController.php:238` — array com chave `'ativo'` duplicada (cosmético).
- Geográfico/Empresa — `destroy` retornando HTML; cadastros menores sem `DB::transaction`.

> **Decisão herdada:** REESCREVER cadastros · REFATORAR Empresa(config). O geográfico é o
> melhor ponto de partida do Bloco B (já iniciado na F3 com Cidade/Bairro).
