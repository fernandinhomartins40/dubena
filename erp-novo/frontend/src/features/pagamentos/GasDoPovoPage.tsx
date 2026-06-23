import { useState } from 'react'
import { Plus, HandCoins, Wallet } from 'lucide-react'
import {
  Button, PageHeader, Input, Badge, DataTable, type Column, EmptyState, Field, AsyncSelect,
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogClose, toast,
} from '@/components/ui'
import { useBeneficios, useRegistrarBeneficio, useSacarBeneficio, type Beneficio } from './api'

const brl = (v: string | number) => Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })

export function GasDoPovoPage() {
  const { data, isLoading } = useBeneficios()
  const registrar = useRegistrarBeneficio()
  const sacar = useSacarBeneficio()

  const [open, setOpen] = useState(false)
  const [form, setForm] = useState<Record<string, any>>({ competencia: new Date().toISOString().slice(0, 7) })
  const [clienteLabel, setClienteLabel] = useState('')

  const [saqueOpen, setSaqueOpen] = useState(false)
  const [alvo, setAlvo] = useState<Beneficio | null>(null)
  const [pedidoId, setPedidoId] = useState<number | null>(null)
  const [contaId, setContaId] = useState<number | null>(null)
  const [contaLabel, setContaLabel] = useState('')

  function abrir() { setForm({ competencia: new Date().toISOString().slice(0, 7) }); setClienteLabel(''); setOpen(true) }
  function abrirSaque(b: Beneficio) { setAlvo(b); setPedidoId(null); setContaId(null); setContaLabel(''); setSaqueOpen(true) }

  async function onRegistrar() {
    if (!form.valor) { toast.error('Informe o valor.'); return }
    try { await registrar.mutateAsync(form); toast.success('Benefício registrado.'); setOpen(false) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao registrar.') }
  }
  async function onSacar() {
    if (!alvo || !pedidoId) { toast.error('Informe o pedido.'); return }
    try { await sacar.mutateAsync({ id: alvo.id, data: { pedido_id: pedidoId, conta_id: contaId } }); toast.success('Benefício sacado.'); setSaqueOpen(false) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao sacar.') }
  }

  const columns: Column<Beneficio>[] = [
    { key: 'competencia', header: 'Competência', cell: (v) => <span className="font-medium tabular-nums">{v.competencia}</span> },
    { key: 'nis', header: 'NIS', cell: (v) => v.nis || '—' },
    { key: 'valor', header: 'Valor', cell: (v) => <span className="tabular-nums">{brl(v.valor)}</span> },
    {
      key: 'sit', header: 'Situação', cell: (v) => v.situacao === 'utilizado'
        ? <Badge variant="secondary">Utilizado</Badge>
        : v.situacao === 'cancelado' ? <Badge variant="destructive">Cancelado</Badge> : <Badge variant="success">Disponível</Badge>,
    },
    {
      key: 'acoes', header: '', cell: (v) => v.situacao === 'disponivel'
        ? <div className="flex justify-end"><Button variant="secondary" size="sm" onClick={() => abrirSaque(v)}><Wallet size={15} /> Sacar</Button></div>
        : null,
    },
  ]

  return (
    <div>
      <PageHeader title="Gás do Povo" subtitle="Auxílio governamental — benefícios e saque"
        action={<Button onClick={abrir}><Plus size={16} /> Novo benefício</Button>} />
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(v) => v.id}
        empty={<EmptyState icon={<HandCoins />} title="Nenhum benefício" />} />

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent>
          <DialogHeader><DialogTitle>Novo benefício</DialogTitle></DialogHeader>
          <div className="space-y-4">
            <Field label="Cliente">
              <AsyncSelect endpoint="/lookups/clientes" value={form.cliente_id ?? null} valueLabel={clienteLabel}
                onChange={(id, opt) => { setForm((f) => ({ ...f, cliente_id: id })); setClienteLabel(opt?.label ?? '') }} />
            </Field>
            <div className="grid grid-cols-3 gap-3">
              <Field label="NIS"><Input value={form.nis ?? ''} onChange={(e) => setForm((f) => ({ ...f, nis: e.target.value }))} /></Field>
              <Field label="Competência" required><Input type="month" value={form.competencia ?? ''} onChange={(e) => setForm((f) => ({ ...f, competencia: e.target.value }))} /></Field>
              <Field label="Valor (R$)" required><Input type="number" step="0.01" min={0} value={form.valor ?? ''} onChange={(e) => setForm((f) => ({ ...f, valor: e.target.value }))} /></Field>
            </div>
          </div>
          <DialogFooter>
            <DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose>
            <Button loading={registrar.isPending} onClick={onRegistrar}>Registrar</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={saqueOpen} onOpenChange={setSaqueOpen}>
        <DialogContent>
          <DialogHeader><DialogTitle>Sacar benefício — {alvo && brl(alvo.valor)}</DialogTitle></DialogHeader>
          <div className="space-y-4">
            <Field label="Pedido (nº)" required>
              <Input type="number" min={1} value={pedidoId ?? ''} onChange={(e) => setPedidoId(e.target.value === '' ? null : Number(e.target.value))} placeholder="ID do pedido" />
            </Field>
            <Field label="Conta (crédito do auxílio)">
              <AsyncSelect endpoint="/lookups/contas" value={contaId} valueLabel={contaLabel}
                onChange={(id, opt) => { setContaId(id); setContaLabel(opt?.label ?? '') }} />
            </Field>
          </div>
          <DialogFooter>
            <DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose>
            <Button loading={sacar.isPending} onClick={onSacar}>Sacar</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
