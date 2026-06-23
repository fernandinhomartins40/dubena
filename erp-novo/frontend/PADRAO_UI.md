# Padrão de UI — erp-novo (SPA)

Guia para criar páginas novas **no padrão**, de forma consistente e com cor
centralizada. Toda página nova deve seguir isto.

## 1. Cor é centralizada — nunca use hex/cor crua

A paleta vive **só** em `src/index.css` (tokens HSL em `:root` e `.dark`) e é
exposta como classes semânticas pelo `tailwind.config.js`. Para mudar a marca,
edita-se **apenas** o `index.css`.

**Paleta (linguagem industrial):**
- Laranja `#FF6200` → `--primary` — **ações** (botões, item de menu ativo, links, foco)
- Lime `#DBFB3B` → `--destaque` / `lime` — **destaque pontual** (badges, KPIs, realces)
- Grafite `#1F1F1F` → sidebar e texto
- Greige/branco → fundo neutro que respira

**Regra de ouro:** neutro domina (~90%), cor é pontual (~10%). Laranja e lime
**nunca** preenchem grandes áreas lado a lado — sempre mediados por neutro.
Tema claro = base off-white com toques de cor; tema escuro = base grafite, cores
mais vivas.

### Use SEMPRE classes semânticas (não cores Tailwind cruas)
| Em vez de | Use |
|---|---|
| `bg-blue-600`, `bg-marca-800`, `bg-info` | `bg-primary` |
| `text-slate-500` | `text-muted-foreground` |
| `bg-white dark:bg-slate-900` | `bg-card` |
| `border-slate-200` | `border-border` |
| `text-yellow-400` (destaque) | `text-destaque` / `bg-lime` |
| `#16a34a` (ok) | `text-success` |
| `text-red-600` (erro) | `text-destructive` |

Proibido: hex literal, `slate-*`, `bg-info`, `bg-marca-*` em telas novas.

## 2. Formatação — use `lib/format.ts`

```ts
import { brl, num, pct, data, dataHora } from '@/lib/format'
brl('1234.5')      // R$ 1.234,50
data(reg.criado)   // 23/06/2026  (ou — se vazio)
```
Não reescreva `toLocaleString`/`new Date` nas páginas.

## 3. Componentes de composição (`@/components/ui`)

### Lista (CRUD) → `ResourceList`
```tsx
<ResourceList
  title="Promoções" subtitle="…"
  action={<Button onClick={() => abrir()}><Plus size={16}/> Nova</Button>}
  columns={columns} rows={data} loading={isLoading} rowKey={(v) => v.id}
  emptyIcon={<Tag/>} emptyTitle="Nenhuma promoção"
/>
```

### Ações de linha → `RowActions` (na coluna `acoes`, `align: 'right'`)
```tsx
{ key: 'acoes', header: '', align: 'right',
  cell: (v) => <RowActions onEdit={() => abrir(v)} onDelete={() => excluir.mutate(v.id)} /> }
```

### Form de criar/editar → `FormDialog`
```tsx
<FormDialog open={open} onOpenChange={setOpen}
  title={edit ? 'Editar' : 'Novo'} loading={salvar.isPending} onConfirm={onSalvar}>
  <Field label="Descrição" required><Input …/></Field>
</FormDialog>
```

### KPIs/resumo → `StatCard`
```tsx
<StatCard titulo="Clientes" valor={n} icon={Users} accent="primary" />
// accent: 'primary' | 'lime' | 'neutral' | 'success' | 'destructive'
```

## 4. Página de referência

`src/features/crm/PromocaoPage.tsx` é o **modelo canônico** de CRUD. Para uma
página nova, copie a estrutura dela: `api.ts` (hooks react-query) +
`ResourceList` + `FormDialog` + `RowActions` + helpers de `format`.

## 5. Checklist ao criar página nova
- [ ] Nenhuma cor hardcoded — só classes semânticas
- [ ] Datas/valores via `lib/format`
- [ ] Lista usa `ResourceList`; ações de linha usam `RowActions`
- [ ] Form usa `FormDialog`
- [ ] Estado vazio com ícone + título (e descrição quando ajudar)
- [ ] `npx tsc --noEmit` limpo
