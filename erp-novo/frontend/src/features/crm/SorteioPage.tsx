import { useState } from 'react'
import { Plus, Gift, Hash, Trophy } from 'lucide-react'
import {
  Button, Input, Badge, type Column, Field, AsyncSelect,
  ResourceList, FormDialog, toast,
} from '@/components/ui'
import { data as fmtData } from '@/lib/format'
import { useSorteios, useSalvarSorteio, useAddNumeroSorteio, useSortear, type Sorteio } from './api'

export function SorteioPage() {
  const { data, isLoading } = useSorteios()
  const salvar = useSalvarSorteio()
  const addNumero = useAddNumeroSorteio()
  const sortear = useSortear()

  const [open, setOpen] = useState(false)
  const [edit, setEdit] = useState<Sorteio | null>(null)
  const [form, setForm] = useState<Record<string, any>>({})

  const [numOpen, setNumOpen] = useState(false)
  const [alvo, setAlvo] = useState<Sorteio | null>(null)
  const [numero, setNumero] = useState('')
  const [clienteId, setClienteId] = useState<number | null>(null)
  const [clienteLabel, setClienteLabel] = useState('')

  function abrir(reg?: Sorteio) { setEdit(reg ?? null); setForm(reg ? { ...reg } : {}); setOpen(true) }
  function abrirNumeros(reg: Sorteio) { setAlvo(reg); setNumero(''); setClienteId(null); setClienteLabel(''); setNumOpen(true) }

  async function onSalvar() {
    try { await salvar.mutateAsync({ id: edit?.id ?? null, data: form }); toast.success('Sorteio salvo.'); setOpen(false) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao salvar.') }
  }
  async function onAddNumero() {
    if (!alvo || !numero) { toast.error('Informe o número.'); return }
    try { await addNumero.mutateAsync({ id: alvo.id, data: { numero, cliente_id: clienteId } }); toast.success('Número adicionado.'); setNumero('') }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro.') }
  }
  async function onSortear(reg: Sorteio) {
    if (!confirm(`Sortear "${reg.descricao}"? A ação é definitiva.`)) return
    try { const r = await sortear.mutateAsync(reg.id); toast.success(`Número sorteado: ${r.numero}`) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao sortear.') }
  }

  const columns: Column<Sorteio>[] = [
    { key: 'descricao', header: 'Descrição', cell: (v) => <span className="font-medium">{v.descricao}</span> },
    { key: 'data', header: 'Data', cell: (v) => fmtData(v.data_sorteio) },
    { key: 'numeros', header: 'Números', align: 'right', cell: (v) => <span className="tabular-nums">{v.numeros_count}</span> },
    {
      key: 'sit', header: 'Situação', cell: (v) => v.situacao === 'sorteado'
        ? <Badge variant="success">Sorteado: {v.numero_sorteado}</Badge>
        : <Badge variant="secondary">Aberto</Badge>,
    },
    {
      key: 'acoes', header: '', align: 'right', cell: (v) => (
        <div className="flex items-center justify-end gap-1">
          <Button variant="ghost" size="sm" onClick={() => abrirNumeros(v)}><Hash size={15} /> Números</Button>
          {v.situacao !== 'sorteado' && <Button variant="secondary" size="sm" loading={sortear.isPending} onClick={() => onSortear(v)}><Trophy size={15} /> Sortear</Button>}
        </div>
      ),
    },
  ]

  return (
    <>
      <ResourceList
        title="Sorteios"
        subtitle="Campanhas de sorteio e ganhadores"
        action={<Button onClick={() => abrir()}><Plus size={16} /> Novo sorteio</Button>}
        columns={columns} rows={data} loading={isLoading} rowKey={(v) => v.id}
        emptyIcon={<Gift />} emptyTitle="Nenhum sorteio"
      />

      <FormDialog
        open={open} onOpenChange={setOpen}
        title={edit ? 'Editar sorteio' : 'Novo sorteio'}
        loading={salvar.isPending} onConfirm={onSalvar}
      >
        <Field label="Descrição" required><Input value={form.descricao ?? ''} onChange={(e) => setForm((f) => ({ ...f, descricao: e.target.value }))} /></Field>
        <Field label="Data do sorteio"><Input type="date" value={form.data_sorteio ?? ''} onChange={(e) => setForm((f) => ({ ...f, data_sorteio: e.target.value }))} /></Field>
      </FormDialog>

      <FormDialog
        open={numOpen} onOpenChange={setNumOpen}
        title={`Números — ${alvo?.descricao ?? ''}`}
        confirmLabel="Adicionar número" loading={addNumero.isPending} onConfirm={onAddNumero}
      >
        <Field label="Número" required><Input value={numero} onChange={(e) => setNumero(e.target.value)} placeholder="ex: 0001" /></Field>
        <Field label="Cliente">
          <AsyncSelect endpoint="/lookups/clientes" value={clienteId} valueLabel={clienteLabel}
            onChange={(id, opt) => { setClienteId(id); setClienteLabel(opt?.label ?? '') }} />
        </Field>
      </FormDialog>
    </>
  )
}
