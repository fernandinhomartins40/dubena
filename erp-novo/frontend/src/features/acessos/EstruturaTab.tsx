import { useState } from 'react'
import { Plus, Building, Network, Users2 } from 'lucide-react'
import {
  Button, Input, Badge, Field, Select, SelectTrigger, SelectValue, SelectContent, SelectItem,
  type Column, ResourceList, FormDialog, RowActions, CheckboxField, toast,
} from '@/components/ui'
import { useAuth } from '@/lib/auth'
import {
  unidades as unidadesApi, departamentos as deptosApi, setoresOrg as setoresApi,
  type Unidade, type Departamento, type SetorOrg,
} from './api'

/**
 * Estrutura organizacional (A3) — árvore Unidade → Departamento → Setor em
 * master-detail: escolha uma unidade para ver/gerir seus departamentos, e um
 * departamento para ver/gerir seus setores. Cada nível respeita sua permissão.
 */
export function EstruturaTab() {
  const { can } = useAuth()
  const [unidadeSel, setUnidadeSel] = useState<number | null>(null)
  const [deptoSel, setDeptoSel] = useState<number | null>(null)

  const { data: unids, isLoading: loadUnids } = unidadesApi.useList()
  const { data: deps, isLoading: loadDeps } = deptosApi.useList(unidadeSel ? { unidade_id: unidadeSel } : undefined)
  const { data: sets, isLoading: loadSets } = setoresApi.useList(deptoSel ? { departamento_id: deptoSel } : undefined)

  return (
    <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <UnidadesPanel
        rows={unids} loading={loadUnids} selecionada={unidadeSel}
        onSelecionar={(id) => { setUnidadeSel(id); setDeptoSel(null) }}
        can={can}
      />
      <DepartamentosPanel
        unidadeId={unidadeSel} rows={deps} loading={loadDeps} selecionado={deptoSel}
        onSelecionar={setDeptoSel} can={can}
      />
      <SetoresPanel departamentoId={deptoSel} rows={sets} loading={loadSets} can={can} />
    </div>
  )
}

// ─────────────── Unidades ───────────────
function UnidadesPanel({ rows, loading, selecionada, onSelecionar, can }: {
  rows?: Unidade[]; loading: boolean; selecionada: number | null
  onSelecionar: (id: number) => void; can: (p: string) => boolean
}) {
  const salvar = unidadesApi.useSalvar()
  const excluir = unidadesApi.useExcluir()
  const [open, setOpen] = useState(false)
  const [edit, setEdit] = useState<Unidade | null>(null)
  const [form, setForm] = useState<Record<string, any>>({})

  function abrir(reg?: Unidade) {
    setEdit(reg ?? null)
    setForm(reg ? { ...reg } : { tipo: 'filial', ativo: true })
    setOpen(true)
  }
  async function onSalvar() {
    try {
      await salvar.mutateAsync({ id: edit?.id ?? null, data: {
        nome: form.nome, tipo: form.tipo, cnpj: form.cnpj || null,
        parent_id: form.parent_id || null, ativo: form.ativo,
      } })
      toast.success('Unidade salva.'); setOpen(false)
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao salvar.') }
  }

  const columns: Column<Unidade>[] = [
    { key: 'nome', header: 'Unidade', cell: (v) => (
      <button className="text-left font-medium hover:underline" onClick={() => onSelecionar(v.id)}>
        {v.nome} {v.tipo === 'matriz' && <Badge variant="secondary">Matriz</Badge>}
      </button>
    ) },
    { key: 'deps', header: 'Deptos', align: 'right', cell: (v) => <Badge variant="secondary">{v.departamentos_count ?? 0}</Badge> },
    { key: 'acoes', header: '', align: 'right', cell: (v) => (
      <RowActions
        onEdit={can('unidade.edit') ? () => abrir(v) : undefined}
        onDelete={can('unidade.delete') ? () => excluir.mutate(v.id) : undefined}
        confirmMsg="Excluir esta unidade?"
      />
    ) },
  ]

  return (
    <div className={selecionada ? 'lg:ring-2 lg:ring-primary/20 rounded-lg' : ''}>
      <ResourceList
        title="Unidades" subtitle="Filiais da empresa"
        action={can('unidade.create') ? <Button size="sm" onClick={() => abrir()}><Plus size={15} /> Nova</Button> : undefined}
        columns={columns} rows={rows} loading={loading} rowKey={(v) => v.id}
        emptyIcon={<Building />} emptyTitle="Nenhuma unidade" emptyDescription="Crie a matriz e filiais."
      />
      <FormDialog open={open} onOpenChange={setOpen} title={edit ? 'Editar unidade' : 'Nova unidade'} loading={salvar.isPending} onConfirm={onSalvar}>
        <Field label="Nome" required><Input value={form.nome ?? ''} onChange={(e) => setForm((f) => ({ ...f, nome: e.target.value }))} /></Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Tipo">
            <Select value={form.tipo ?? 'filial'} onValueChange={(v) => setForm((f) => ({ ...f, tipo: v }))}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="matriz">Matriz</SelectItem>
                <SelectItem value="filial">Filial</SelectItem>
              </SelectContent>
            </Select>
          </Field>
          <Field label="CNPJ"><Input value={form.cnpj ?? ''} onChange={(e) => setForm((f) => ({ ...f, cnpj: e.target.value }))} /></Field>
        </div>
        <Field label="Unidade-pai (opcional)">
          <Select value={form.parent_id ? String(form.parent_id) : 'none'} onValueChange={(v) => setForm((f) => ({ ...f, parent_id: v === 'none' ? null : Number(v) }))}>
            <SelectTrigger><SelectValue placeholder="Nenhuma (raiz)" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="none">Nenhuma (raiz)</SelectItem>
              {(rows ?? []).filter((u) => u.id !== edit?.id).map((u) => <SelectItem key={u.id} value={String(u.id)}>{u.nome}</SelectItem>)}
            </SelectContent>
          </Select>
        </Field>
        <CheckboxField label="Ativa" checked={!!form.ativo} onChange={(c) => setForm((f) => ({ ...f, ativo: c }))} />
      </FormDialog>
    </div>
  )
}

// ─────────────── Departamentos ───────────────
function DepartamentosPanel({ unidadeId, rows, loading, selecionado, onSelecionar, can }: {
  unidadeId: number | null; rows?: Departamento[]; loading: boolean; selecionado: number | null
  onSelecionar: (id: number) => void; can: (p: string) => boolean
}) {
  const salvar = deptosApi.useSalvar()
  const excluir = deptosApi.useExcluir()
  const [open, setOpen] = useState(false)
  const [edit, setEdit] = useState<Departamento | null>(null)
  const [nome, setNome] = useState('')

  function abrir(reg?: Departamento) { setEdit(reg ?? null); setNome(reg?.nome ?? ''); setOpen(true) }
  async function onSalvar() {
    try {
      await salvar.mutateAsync({ id: edit?.id ?? null, data: { unidade_id: unidadeId, nome } })
      toast.success('Departamento salvo.'); setOpen(false)
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao salvar.') }
  }

  if (!unidadeId) {
    return <div className="rounded-lg border border-dashed border-border p-6 text-sm text-muted-foreground grid place-items-center">Selecione uma unidade</div>
  }

  const columns: Column<Departamento>[] = [
    { key: 'nome', header: 'Departamento', cell: (v) => (
      <button className="text-left font-medium hover:underline" onClick={() => onSelecionar(v.id)}>{v.nome}</button>
    ) },
    { key: 'sets', header: 'Setores', align: 'right', cell: (v) => <Badge variant="secondary">{v.setores_count ?? 0}</Badge> },
    { key: 'acoes', header: '', align: 'right', cell: (v) => (
      <RowActions
        onEdit={can('departamento.edit') ? () => abrir(v) : undefined}
        onDelete={can('departamento.delete') ? () => excluir.mutate(v.id) : undefined}
        confirmMsg="Excluir este departamento?"
      />
    ) },
  ]

  return (
    <div className={selecionado ? 'lg:ring-2 lg:ring-primary/20 rounded-lg' : ''}>
      <ResourceList
        title="Departamentos" subtitle="Da unidade selecionada"
        action={can('departamento.create') ? <Button size="sm" onClick={() => abrir()}><Plus size={15} /> Novo</Button> : undefined}
        columns={columns} rows={rows} loading={loading} rowKey={(v) => v.id}
        emptyIcon={<Network />} emptyTitle="Nenhum departamento" emptyDescription="Crie departamentos nesta unidade."
      />
      <FormDialog open={open} onOpenChange={setOpen} title={edit ? 'Editar departamento' : 'Novo departamento'} loading={salvar.isPending} onConfirm={onSalvar}>
        <Field label="Nome" required><Input value={nome} onChange={(e) => setNome(e.target.value)} /></Field>
      </FormDialog>
    </div>
  )
}

// ─────────────── Setores ───────────────
function SetoresPanel({ departamentoId, rows, loading, can }: {
  departamentoId: number | null; rows?: SetorOrg[]; loading: boolean; can: (p: string) => boolean
}) {
  const salvar = setoresApi.useSalvar()
  const excluir = setoresApi.useExcluir()
  const [open, setOpen] = useState(false)
  const [edit, setEdit] = useState<SetorOrg | null>(null)
  const [nome, setNome] = useState('')

  function abrir(reg?: SetorOrg) { setEdit(reg ?? null); setNome(reg?.nome ?? ''); setOpen(true) }
  async function onSalvar() {
    try {
      await salvar.mutateAsync({ id: edit?.id ?? null, data: { departamento_id: departamentoId, nome } })
      toast.success('Setor salvo.'); setOpen(false)
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao salvar.') }
  }

  if (!departamentoId) {
    return <div className="rounded-lg border border-dashed border-border p-6 text-sm text-muted-foreground grid place-items-center">Selecione um departamento</div>
  }

  const columns: Column<SetorOrg>[] = [
    { key: 'nome', header: 'Setor / Equipe', cell: (v) => <span className="font-medium">{v.nome}</span> },
    { key: 'acoes', header: '', align: 'right', cell: (v) => (
      <RowActions
        onEdit={can('setor.edit') ? () => abrir(v) : undefined}
        onDelete={can('setor.delete') ? () => excluir.mutate(v.id) : undefined}
        confirmMsg="Excluir este setor?"
      />
    ) },
  ]

  return (
    <div>
      <ResourceList
        title="Setores" subtitle="Do departamento selecionado"
        action={can('setor.create') ? <Button size="sm" onClick={() => abrir()}><Plus size={15} /> Novo</Button> : undefined}
        columns={columns} rows={rows} loading={loading} rowKey={(v) => v.id}
        emptyIcon={<Users2 />} emptyTitle="Nenhum setor" emptyDescription="Crie setores/equipes neste departamento."
      />
      <FormDialog open={open} onOpenChange={setOpen} title={edit ? 'Editar setor' : 'Novo setor'} loading={salvar.isPending} onConfirm={onSalvar}>
        <Field label="Nome" required><Input value={nome} onChange={(e) => setNome(e.target.value)} /></Field>
      </FormDialog>
    </div>
  )
}
