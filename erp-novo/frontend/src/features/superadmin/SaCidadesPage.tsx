import { useState } from 'react'
import { Plus, MapPin } from 'lucide-react'
import {
  Button, Input, Badge, type Column, Field, CheckboxField,
  ResourceList, FormDialog, RowActions, toast,
} from '@/components/ui'
import { useSaCidades, useSaCidadeAcoes, type SaCidade } from './api'

/** Cidades da plataforma (catálogo global) — P3/P4. */
export function SaCidadesPage() {
  const { data, isLoading } = useSaCidades()
  const { salvar, excluir } = useSaCidadeAcoes()
  const [open, setOpen] = useState(false)
  const [edit, setEdit] = useState<SaCidade | null>(null)
  const [form, setForm] = useState<Record<string, any>>({})

  function abrir(reg?: SaCidade) {
    setEdit(reg ?? null)
    setForm(reg ? { ...reg } : { ativo: true, uf: '' })
    setOpen(true)
  }

  async function onSalvar() {
    try {
      await salvar.mutateAsync({
        id: edit?.id ?? null,
        data: {
          nome: form.nome,
          uf: String(form.uf ?? '').toUpperCase(),
          cod_ibge: form.cod_ibge ? Number(form.cod_ibge) : null,
          centro_lat: form.centro_lat === '' || form.centro_lat == null ? null : Number(form.centro_lat),
          centro_lng: form.centro_lng === '' || form.centro_lng == null ? null : Number(form.centro_lng),
          ativo: !!form.ativo,
        },
      })
      toast.success('Cidade salva.'); setOpen(false)
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao salvar.') }
  }

  const columns: Column<SaCidade>[] = [
    { key: 'nome', header: 'Cidade', cell: (v) => <span className="font-medium">{v.nome}</span> },
    { key: 'uf', header: 'UF', cell: (v) => v.uf },
    { key: 'centro', header: 'Centro (lat,lng)', cell: (v) => (v.centro_lat != null && v.centro_lng != null) ? `${v.centro_lat}, ${v.centro_lng}` : '—' },
    { key: 'ativo', header: 'Status', cell: (v) => v.ativo ? <Badge variant="success">Ativa</Badge> : <Badge variant="secondary">Inativa</Badge> },
    {
      key: 'acoes', header: '', align: 'right',
      cell: (v) => <RowActions onEdit={() => abrir(v)} onDelete={() => excluir.mutate(v.id)} confirmMsg={`Excluir ${v.nome}?`} />,
    },
  ]

  return (
    <>
      <ResourceList
        title="Cidades da plataforma"
        subtitle="Catálogo global de cidades atendidas (descoberta multi-cidade)"
        action={<Button onClick={() => abrir()}><Plus size={16} /> Nova cidade</Button>}
        columns={columns}
        rows={data}
        loading={isLoading}
        rowKey={(v) => v.id}
        emptyIcon={<MapPin />}
        emptyTitle="Nenhuma cidade"
        emptyDescription="Cadastre as cidades em que a plataforma opera."
      />

      <FormDialog
        open={open} onOpenChange={setOpen}
        title={edit ? 'Editar cidade' : 'Nova cidade'}
        loading={salvar.isPending} onConfirm={onSalvar}
      >
        <div className="grid grid-cols-[1fr_90px] gap-3">
          <Field label="Nome" required><Input value={form.nome ?? ''} onChange={(e) => setForm((f) => ({ ...f, nome: e.target.value }))} /></Field>
          <Field label="UF" required><Input maxLength={2} value={form.uf ?? ''} onChange={(e) => setForm((f) => ({ ...f, uf: e.target.value.toUpperCase() }))} /></Field>
        </div>
        <Field label="Código IBGE"><Input type="number" value={form.cod_ibge ?? ''} onChange={(e) => setForm((f) => ({ ...f, cod_ibge: e.target.value }))} /></Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Centro — latitude"><Input type="number" step="0.0000001" value={form.centro_lat ?? ''} onChange={(e) => setForm((f) => ({ ...f, centro_lat: e.target.value }))} /></Field>
          <Field label="Centro — longitude"><Input type="number" step="0.0000001" value={form.centro_lng ?? ''} onChange={(e) => setForm((f) => ({ ...f, centro_lng: e.target.value }))} /></Field>
        </div>
        <CheckboxField label="Cidade ativa" checked={!!form.ativo} onChange={(c) => setForm((f) => ({ ...f, ativo: c }))} />
      </FormDialog>
    </>
  )
}
