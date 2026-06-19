# IMPL_UI — Contrato de DESIGN SYSTEM (camada visual moderna) · vigente

> **Por que existe:** os IMPL_<modulo> definem PARIDADE (campos/regras) e REORGANIZAÇÃO
> (de-para, abas, visão nova) — mas NÃO definiam como a tela deve **parecer**. Resultado: as
> primeiras telas (Cliente/Produto) ficaram com paridade de dados, porém visual cru (Input/Card
> básicos), "a mesma coisa do AdminLTE antigo". Este documento é o **contrato visual**: todo
> módulo usa estes componentes e padrões. Sem ele, "moderno" virava adivinhação.
>
> **Regra:** nenhuma tela nova usa `<input>`/`<select>`/`<table>` cru. Tudo vem do design system
> em `frontend/src/components/ui/`. Telas existentes são migradas para o DS.

---

## STACK (libs modernas — já instaladas)
- **Tailwind 3** (utilitários) + **tokens CSS** (HSL via CSS vars, tema claro/escuro).
- **Radix UI** (primitivos acessíveis: dialog, select, dropdown, tabs, tooltip, popover,
  checkbox, switch, label) — comportamento/A11y corretos, estilo nosso.
- **class-variance-authority (CVA)** — variantes de componente tipadas.
- **tailwindcss-animate** — transições/entradas suaves.
- **sonner** — toasts (feedback de ação).
- **@tanstack/react-table** — DataTable rica (ordenação, etc.).
- **lucide-react** — ícones.
- Abordagem **shadcn/ui** (componentes copiados pro projeto, 100% sob nosso controle — não é
  template fechado).

---

## TOKENS (identidade Dubena → CSS vars)
Definidos em `index.css` como HSL, claro/escuro. Tailwind mapeia para classes semânticas.

| Token | Claro | Uso |
|---|---|---|
| `--background` / `--foreground` | branco / quase-preto | fundo e texto base |
| `--card` | branco | superfícies elevadas |
| `--primary` | **azul Dubena #2a54ad** | ações primárias, foco, links |
| `--primary-foreground` | branco | texto sobre primary |
| `--accent` | **roxo #672290** | destaques/seleção secundária |
| `--destaque` | **amarelo #e7eb13** | badge de marca/atenção |
| `--muted` / `--muted-foreground` | cinza claro / cinza médio | rótulos, textos secundários |
| `--destructive` | vermelho | excluir/erro |
| `--border` / `--input` / `--ring` | cinza | bordas, contorno de input, anel de foco |
| `--radius` | 0.625rem | raio padrão (cantos suaves, ar 2026) |

Tema escuro: mesma escala invertida. Densidade: inputs `h-9`/`h-10`, espaçamento generoso,
sombras sutis (`shadow-sm`), transições 150ms.

---

## COMPONENTES (em `frontend/src/components/ui/`)
Cada um é um arquivo; exportados via `components/ui/index.ts`.

| Componente | Base | Papel |
|---|---|---|
| `button.tsx` | CVA | variantes: default/secondary/outline/ghost/destructive/link; tamanhos sm/default/lg/icon; estado loading (spinner). |
| `input.tsx` | — | input com foco anel-primary, estados de erro. |
| `textarea.tsx` | — | idem multiline. |
| `label.tsx` | Radix Label | rótulo acessível. |
| `field.tsx` | — | wrapper Label+controle+mensagem de erro+hint (padrão de formulário). |
| `select.tsx` | Radix Select | select estilizado (não o nativo feio). |
| `checkbox.tsx` / `switch.tsx` | Radix | flags booleanas. |
| `card.tsx` | — | Card + CardHeader/Title/Description/Content/Footer. |
| `tabs.tsx` | Radix Tabs | abas acessíveis (substitui o Tabs manual). |
| `dialog.tsx` | Radix Dialog | modais (forms de config, confirmações). |
| `dropdown-menu.tsx` | Radix | menu de ações por linha (•••). |
| `badge.tsx` | CVA | status (ativo/inativo, sim/não NF-e) com cor semântica. |
| `tooltip.tsx` | Radix | dicas em ícones de ação. |
| `skeleton.tsx` | — | placeholders de carregamento (fim do "pisca"). |
| `empty-state.tsx` | — | estado vazio bonito (ícone + texto + ação). |
| `data-table.tsx` | react-table | tabela com header sticky, zebra, hover, ordenação, ações, paginação, loading skeleton, empty state. |
| `page-header.tsx` | — | título + subtítulo + breadcrumb + ações (à direita). |
| `toast` | sonner | `toast.success/error` para feedback de salvar/excluir. |
| `async-select.tsx` | Radix Popover + Command-like | seleção assíncrona (cidade→bairro) repaginada. |

---

## PADRÕES DE PÁGINA (todo módulo segue)
1. **Lista** = `PageHeader` (título + contagem + botão primário "Novo") → barra de busca em Card →
   `DataTable` (sticky header, hover, badge de status, **menu de ações por linha**, paginação,
   skeleton no load, empty-state quando vazio). NUNCA `<table>` cru.
2. **Ficha** = `PageHeader` (nome da entidade + ações Voltar/Salvar) → `Tabs` →
   cada aba em `Card` com `Field`s em grid responsivo (2 col desktop). Campos condicionais reativos.
3. **Configurações** (Classes/Unidades/etc.) = página com abas; cada aba uma `DataTable` + `Dialog`
   de criar/editar (não navega pra outra rota).
4. **Feedback** = toast em toda mutação (sucesso/erro); erro de regra de negócio em banner no topo;
   loading = skeleton, nunca tela branca; confirmação de exclusão em `Dialog` (não `confirm()`).
5. **Visão nova** (o que difere do legado): a ficha agrega dados de outros domínios (ex.: Produto
   mostra estoque por setor + curva de giro; Cliente mostra histórico/financeiro) — não é só CRUD.

---

## DoD (uma tela só é "moderna" se)
- [ ] Zero `<input>`/`<select>`/`<table>`/`confirm()` cru — tudo via `components/ui`.
- [ ] Lista com DataTable (sticky/hover/ordenação/ações/paginação/skeleton/empty-state).
- [ ] Ficha com Tabs + Field + grid responsivo; campos condicionais reativos.
- [ ] Toasts em salvar/excluir; banner para erro de regra; Dialog para confirmar/editar config.
- [ ] Tokens Dubena aplicados (primary azul, badges semânticos, raio/sombra/transição).
- [ ] Tema claro/escuro funcionando; A11y (foco visível, labels, Radix).
- [ ] "Visão nova" do IMPL do módulo presente (agrega dados, não só replica o form legado).
