# MODERNIZAÇÃO (auditoria de código) — Produtos / Estoque · D06

>
> **Paradigma-alvo:** ver [`MODERN_00_VISAO_UX.md`](MODERN_00_VISAO_UX.md) — página
> completa por entidade (abas/RelationManagers), navegação DECLARATIVA (sem menu-no-banco),
> permissão por recurso/ação (roles). O layout/fluxo legado é DESCARTADO; só as REGRAS de
> negócio são preservadas.
> Auditoria do CÓDIGO REAL (pós F0–F4A) vs. PRD fiel [`06_produtos_estoque.md`](06_produtos_estoque.md).

---

## 1. ANTES × AGORA (verificado)

| Item (PRD fiel) | Original | HOJE (auditado) | Ref. |
|---|---|---|---|
| `TesteestoqueController` debug exposto (corrompe estoque) | 🔴 | ✅ **DELETADO** (F0) | ausente de `app/Http/Controllers/` |
| `EstoqueProcessor::efetivarEstoquefisico` grava id no `empresa_id` | 🔴 | ✅ **corrigido** | `EstoqueProcessor.php:80` |
| `Inventario` usa `$e` em `catch($ex)` | 🔴 gravar/excluir quebra | ✅ **corrigido** (`catch (Exception $e)`) | `InventarioController.php:142,196` |
| Motor de estoque caracterizado (F1) | sem testes | ✅ **golden** (movimentar/fechar/efetivar) | `EstoqueProcessor.php` |

---

## 2. DÍVIDA DE UX/UI E FLUXO

- **Motor de estoque maduro** (EstoqueProcessor, caracterizado na F1) — preservar.
- **Telas** (requisição/transferência/acerto/inventário) são forms AdminLTE densos, sem
  visão de saldo em tempo real por setor/produto, sem confirmação visual do movimento.
- Produto: cadastro longo (fiscal + estoque + preço) num form único.

---

## 3. REGRAS A PRESERVAR (caracterizadas na F1)

- `movimentarEstoque` (ENTRADA/SAÍDA, saldo por setor+produto, não-negativar conforme
  `permiteestoquenegativo`), `fecharEstoque` (consolidação por setor+produto),
  `efetivarEstoquefisico` (ajuste sistema→físico). Custo médio ponderado (`updateCustoMedio`).

---

## 4. BLUEPRINT DE MODERNIZAÇÃO (Filament 3 + Service)

- Manter EstoqueProcessor como Domain Service; UI fina.
- **ProdutoResource** Filament: abas (Dados, Fiscal, Estoque, Preço); classes/unidades como
  Resources de apoio.
- **Telas de movimento** (requisição/transferência/acerto/inventário) com saldo em tempo
  real e preview do efeito; histórico por produto/setor.

---

## 5. PENDÊNCIAS RESIDUAIS (arquivo:linha)

- Telas de estoque com HTML no backend e sem preview (dívida de UX, não bug).
- Confirmar parametrização de eventuais whereRaw nos relatórios de estoque (D12).

> **Decisão herdada:** REFATORAR motor · REESCREVER telas. Os bugs 🔴 do PRD fiel já foram
> fechados pela F0; o motor está caracterizado. Pronto para UI nova com baixo risco.
