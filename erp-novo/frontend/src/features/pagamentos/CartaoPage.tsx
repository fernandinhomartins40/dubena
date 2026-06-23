import { useState } from 'react'
import { Plus, CreditCard } from 'lucide-react'
import {
  Button, PageHeader, Input, Badge, DataTable, type Column, EmptyState, Field, AsyncSelect,
  Select, SelectTrigger, SelectValue, SelectContent, SelectItem,
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogClose, toast,
} from '@/components/ui'
import { useCartoes, useRegistrarCartao, type CartaoTransacao } from './api'

const brl = (v: string | number) => Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })

export function CartaoPage() {
  const { data, isLoading } = useCartoes()
  const registrar = useRegistrarCartao()
  const [open, setOpen] = useState(false)
  const [form, setForm] = useState<Record<string, any>>({ tipo: 'credito', parcelas: 1, taxa_percentual: 0 })
  const [contaLabel, setContaLabel] = useState('')

  function abrir() { setForm({ tipo: 'credito', parcelas: 1, taxa_percentual: 0 }); setContaLabel(''); setOpen(true) }
  async function onSalvar() {
    if (!form.valor_bruto) { toast.error('Informe o valor bruto.'); return }
    try { await registrar.mutateAsync(form); toast.success('Transação registrada.'); setOpen(false) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao registrar.') }
  }

  const liquido = (Number(form.valor_bruto) || 0) * (1 - (Number(form.taxa_percentual) || 0) / 100)

  const columns: Column<CartaoTransacao>[] = [
    { key: 'bandeira', header: 'Bandeira', cell: (v) => v.bandeira || '—' },
    { key: 'tipo', header: 'Tipo', cell: (v) => <Badge variant="secondary">{v.tipo}</Badge> },
    { key: 'nsu', header: 'NSU', cell: (v) => <span className="tabular-nums">{v.nsu || '—'}</span> },
    { key: 'parcelas', header: 'Parcelas', cell: (v) => `${v.parcelas}x` },
    { key: 'bruto', header: 'Bruto', cell: (v) => <span className="tabular-nums">{brl(v.valor_bruto)}</span> },
    { key: 'taxa', header: 'Taxa', cell: (v) => `${v.taxa_percentual}%` },
    { key: 'liquido', header: 'Líquido', cell: (v) => <span className="tabular-nums font-medium">{brl(v.valor_liquido)}</span> },
  ]

  return (
    <div>
      <PageHeader title="Cartões" subtitle="Transações por cartão (NSU/bandeira) — líquido no caixa"
        action={<Button onClick={abrir}><Plus size={16} /> Nova transação</Button>} />
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(v) => v.id}
        empty={<EmptyState icon={<CreditCard />} title="Nenhuma transação" />} />

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent>
          <DialogHeader><DialogTitle>Nova transação de cartão</DialogTitle></DialogHeader>
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-3">
              <Field label="Bandeira"><Input value={form.bandeira ?? ''} onChange={(e) => setForm((f) => ({ ...f, bandeira: e.target.value }))} placeholder="visa, master…" /></Field>
              <Field label="Tipo">
                <Select value={form.tipo} onValueChange={(v) => setForm((f) => ({ ...f, tipo: v }))}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent><SelectItem value="credito">Crédito</SelectItem><SelectItem value="debito">Débito</SelectItem></SelectContent>
                </Select>
              </Field>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <Field label="NSU"><Input value={form.nsu ?? ''} onChange={(e) => setForm((f) => ({ ...f, nsu: e.target.value }))} /></Field>
              <Field label="Autorização"><Input value={form.autorizacao ?? ''} onChange={(e) => setForm((f) => ({ ...f, autorizacao: e.target.value }))} /></Field>
            </div>
            <div className="grid grid-cols-3 gap-3">
              <Field label="Valor bruto (R$)" required><Input type="number" step="0.01" min={0} value={form.valor_bruto ?? ''} onChange={(e) => setForm((f) => ({ ...f, valor_bruto: e.target.value }))} /></Field>
              <Field label="Taxa (%)"><Input type="number" step="0.01" min={0} max={100} value={form.taxa_percentual ?? ''} onChange={(e) => setForm((f) => ({ ...f, taxa_percentual: e.target.value }))} /></Field>
              <Field label="Parcelas"><Input type="number" min={1} max={24} value={form.parcelas ?? 1} onChange={(e) => setForm((f) => ({ ...f, parcelas: Number(e.target.value) }))} /></Field>
            </div>
            <Field label="Conta (crédito do líquido)">
              <AsyncSelect endpoint="/lookups/contas" value={form.conta_id ?? null} valueLabel={contaLabel}
                onChange={(id, opt) => { setForm((f) => ({ ...f, conta_id: id })); setContaLabel(opt?.label ?? '') }} />
            </Field>
            <p className="text-right text-sm">Líquido estimado: <span className="font-medium tabular-nums">{brl(liquido)}</span></p>
          </div>
          <DialogFooter>
            <DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose>
            <Button loading={registrar.isPending} onClick={onSalvar}>Registrar</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
