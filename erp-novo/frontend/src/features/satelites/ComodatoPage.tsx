import { useState } from 'react'
import { Plus, PackageCheck, Undo2 } from 'lucide-react'
import {
  Button, PageHeader, Input, Badge, DataTable, type Column, EmptyState, Field, AsyncSelect,
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogClose, toast,
} from '@/components/ui'
import { useComodatos, useCriarComodato, useDevolverComodato, type Comodato } from './extraApi'

const fmtData = (s: string | null) => (s ? new Date(s).toLocaleDateString('pt-BR') : '—')

export function ComodatoPage() {
  const { data, isLoading } = useComodatos()
  const criar = useCriarComodato()
  const devolver = useDevolverComodato()
  const [open, setOpen] = useState(false)
  const [form, setForm] = useState<Record<string, any>>({ quantidade: 1 })
  const [clienteLabel, setClienteLabel] = useState('')
  const [prodLabel, setProdLabel] = useState('')

  function abrir() { setForm({ quantidade: 1 }); setClienteLabel(''); setProdLabel(''); setOpen(true) }
  async function onCriar() {
    if (!form.cliente_id || !form.produto_id) { toast.error('Cliente e produto são obrigatórios.'); return }
    try { await criar.mutateAsync(form); toast.success('Comodato registrado.'); setOpen(false) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao registrar.') }
  }
  async function onDevolver(c: Comodato) {
    const pend = Number(c.quantidade) - Number(c.quantidade_devolvida)
    const q = prompt(`Quantidade a devolver (pendente: ${pend})`, String(pend))
    if (q === null) return
    try { await devolver.mutateAsync({ id: c.id, quantidade: Number(q) }); toast.success('Devolução registrada.') }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro.') }
  }

  const columns: Column<Comodato>[] = [
    { key: 'cliente', header: 'Cliente', cell: (v) => v.cliente?.nome || '—' },
    { key: 'produto', header: 'Produto', cell: (v) => v.produto?.descricao || '—' },
    { key: 'qtd', header: 'Qtd', cell: (v) => <span className="tabular-nums">{v.quantidade}</span> },
    { key: 'dev', header: 'Devolvido', cell: (v) => <span className="tabular-nums">{v.quantidade_devolvida}</span> },
    { key: 'desde', header: 'Desde', cell: (v) => fmtData(v.data_emprestimo) },
    { key: 'sit', header: 'Situação', cell: (v) => v.situacao === 'DEVOLVIDO' ? <Badge variant="secondary">Devolvido</Badge> : <Badge variant="warning">Em aberto</Badge> },
    {
      key: 'acoes', header: '', cell: (v) => v.situacao !== 'DEVOLVIDO'
        ? <div className="flex justify-end"><Button variant="ghost" size="sm" onClick={() => onDevolver(v)}><Undo2 size={15} /> Devolver</Button></div>
        : null,
    },
  ]

  return (
    <div>
      <PageHeader title="Comodatos" subtitle="Vasilhames emprestados a clientes"
        action={<Button onClick={abrir}><Plus size={16} /> Novo comodato</Button>} />
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(v) => v.id}
        empty={<EmptyState icon={<PackageCheck />} title="Nenhum comodato" />} />

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent>
          <DialogHeader><DialogTitle>Novo comodato</DialogTitle></DialogHeader>
          <div className="space-y-4">
            <Field label="Cliente" required>
              <AsyncSelect endpoint="/lookups/clientes" value={form.cliente_id ?? null} valueLabel={clienteLabel}
                onChange={(id, opt) => { setForm((f) => ({ ...f, cliente_id: id })); setClienteLabel(opt?.label ?? '') }} />
            </Field>
            <Field label="Produto (vasilhame)" required>
              <AsyncSelect endpoint="/lookups/produtos-vasilhame" value={form.produto_id ?? null} valueLabel={prodLabel}
                onChange={(id, opt) => { setForm((f) => ({ ...f, produto_id: id })); setProdLabel(opt?.label ?? '') }} />
            </Field>
            <Field label="Quantidade" required><Input type="number" min={1} value={form.quantidade ?? 1} onChange={(e) => setForm((f) => ({ ...f, quantidade: Number(e.target.value) }))} /></Field>
          </div>
          <DialogFooter>
            <DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose>
            <Button loading={criar.isPending} onClick={onCriar}>Registrar</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
