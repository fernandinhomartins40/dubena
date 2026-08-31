import { useState } from 'react'
import { Plus, Package } from 'lucide-react'
import {
  Button, Input, Textarea, Badge, type Column, Field, CheckboxField,
  ResourceList, FormDialog, RowActions, toast,
} from '@/components/ui'
import { brl } from '@/lib/format'
import { useSaPlanos, useSaSalvarPlano, type SaPlano } from './api'

/** Planos (catálogo global) + seus recursos (feature-flags) — P2/P4. */
export function SaPlanosPage() {
  const { data, isLoading } = useSaPlanos()
  const salvar = useSaSalvarPlano()
  const [open, setOpen] = useState(false)
  const [edit, setEdit] = useState<SaPlano | null>(null)
  const [form, setForm] = useState<Record<string, any>>({})
  const [recursos, setRecursos] = useState<Set<string>>(new Set())
  // chave => string do input. Vazio = ILIMITADO (o backend recebe null).
  const [limites, setLimites] = useState<Record<string, string>>({})

  const catalogo = data?.catalogo ?? []
  const catalogoLimites = data?.catalogoLimites ?? []

  function abrir(reg?: SaPlano) {
    setEdit(reg ?? null)
    setForm(reg ? { ...reg } : { ativo: true })
    setRecursos(new Set(reg?.recursos ?? []))
    setLimites(
      Object.fromEntries(
        Object.entries(reg?.limites ?? {}).map(([k, v]) => [k, v === null || v === undefined ? '' : String(v)]),
      ),
    )
    setOpen(true)
  }

  function toggle(chave: string, on: boolean) {
    setRecursos((prev) => {
      const next = new Set(prev)
      if (on) next.add(chave); else next.delete(chave)
      return next
    })
  }

  async function onSalvar() {
    try {
      await salvar.mutateAsync({
        id: edit?.id ?? null,
        data: {
          slug: form.slug,
          nome: form.nome,
          descricao: form.descricao || null,
          preco_mensal: Number(form.preco_mensal ?? 0),
          ativo: !!form.ativo,
          recursos: Array.from(recursos),
          // Campo vazio vira `null` = ilimitado, e nao 0 (que barraria tudo).
          limites: Object.fromEntries(
            catalogoLimites.map((l) => [l.chave, limites[l.chave]?.trim() ? Number(limites[l.chave]) : null]),
          ),
        },
      })
      toast.success('Plano salvo.'); setOpen(false)
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao salvar.') }
  }

  const columns: Column<SaPlano>[] = [
    { key: 'nome', header: 'Plano', cell: (v) => <div><div className="font-medium">{v.nome}</div><div className="text-xs text-muted-foreground">{v.slug}</div></div> },
    { key: 'preco', header: 'Preço/mês', cell: (v) => brl(Number(v.preco_mensal ?? 0)) },
    { key: 'recursos', header: 'Recursos', cell: (v) => <span className="text-sm text-muted-foreground">{(v.recursos ?? []).length} recurso(s)</span> },
    {
      key: 'limites', header: 'Limites',
      cell: (v) => {
        const declarados = Object.entries(v.limites ?? {}).filter(([, teto]) => teto !== null && teto !== undefined)
        return <span className="text-sm text-muted-foreground">{declarados.length === 0 ? 'Ilimitado' : `${declarados.length} teto(s)`}</span>
      },
    },
    { key: 'ativo', header: 'Status', cell: (v) => v.ativo ? <Badge variant="success">Ativo</Badge> : <Badge variant="secondary">Inativo</Badge> },
    { key: 'acoes', header: '', align: 'right', cell: (v) => <RowActions onEdit={() => abrir(v)} /> },
  ]

  return (
    <>
      <ResourceList
        title="Planos"
        subtitle="Catálogo de planos: preço, recursos habilitados e limites de uso"
        action={<Button onClick={() => abrir()}><Plus size={16} /> Novo plano</Button>}
        columns={columns}
        rows={data?.planos}
        loading={isLoading}
        rowKey={(v) => v.id}
        emptyIcon={<Package />}
        emptyTitle="Nenhum plano"
        emptyDescription="Crie planos e marque os recursos que cada um libera."
      />

      <FormDialog
        open={open} onOpenChange={setOpen}
        title={edit ? 'Editar plano' : 'Novo plano'}
        loading={salvar.isPending} onConfirm={onSalvar}
        widthClass="max-w-2xl"
      >
        <div className="grid grid-cols-2 gap-3">
          <Field label="Nome" required><Input value={form.nome ?? ''} onChange={(e) => setForm((f) => ({ ...f, nome: e.target.value }))} /></Field>
          <Field label="Slug" required><Input value={form.slug ?? ''} onChange={(e) => setForm((f) => ({ ...f, slug: e.target.value }))} placeholder="basico / pro / enterprise" /></Field>
        </div>
        <Field label="Descrição"><Textarea value={form.descricao ?? ''} onChange={(e) => setForm((f) => ({ ...f, descricao: e.target.value }))} /></Field>
        <Field label="Preço mensal (R$)" required><Input type="number" min={0} step="0.01" value={form.preco_mensal ?? ''} onChange={(e) => setForm((f) => ({ ...f, preco_mensal: e.target.value }))} /></Field>

        <div>
          <p className="mb-2 text-sm font-medium">Recursos do plano</p>
          <div className="grid grid-cols-2 gap-2 rounded-lg border border-border p-3">
            {catalogo.length === 0 && <p className="text-sm text-muted-foreground">Catálogo de recursos indisponível.</p>}
            {catalogo.map((r) => (
              <CheckboxField key={r.chave} label={r.descricao} checked={recursos.has(r.chave)} onChange={(c) => toggle(r.chave, c)} />
            ))}
          </div>
        </div>

        <div>
          <p className="mb-1 text-sm font-medium">Limites do plano</p>
          <p className="mb-2 text-xs text-muted-foreground">
            Deixe em branco para ilimitado. Zero bloqueia o recurso por completo.
          </p>
          <div className="grid grid-cols-2 gap-3 rounded-lg border border-border p-3">
            {catalogoLimites.length === 0 && <p className="text-sm text-muted-foreground">Catálogo de limites indisponível.</p>}
            {catalogoLimites.map((l) => (
              <Field key={l.chave} label={l.descricao}>
                <Input
                  type="number" min={0} placeholder="Ilimitado"
                  value={limites[l.chave] ?? ''}
                  onChange={(e) => setLimites((p) => ({ ...p, [l.chave]: e.target.value }))}
                />
              </Field>
            ))}
          </div>
        </div>

        <CheckboxField label="Plano ativo" checked={!!form.ativo} onChange={(c) => setForm((f) => ({ ...f, ativo: c }))} />
      </FormDialog>
    </>
  )
}
