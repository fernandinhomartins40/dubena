import { useState } from 'react'
import { Search, Flame, CheckCircle2 } from 'lucide-react'
import {
  Button, Card, PageHeader, Input, Badge, DataTable, type Column, EmptyState, Field,
  Select, SelectTrigger, SelectValue, SelectContent, SelectItem,
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogFooter, DialogClose, toast,
} from '@/components/ui'
import { useValeGas, useValeGasSituacoes, useBaixarValeGas } from './api'

const fmtData = (s: string | null) => (s ? new Date(s).toLocaleDateString('pt-BR') : '—')

export function ValeGasPage() {
  const [busca, setBusca] = useState(''); const [q, setQ] = useState('')
  const { data, isLoading } = useValeGas(q)
  const { data: situacoes } = useValeGasSituacoes()
  const baixar = useBaixarValeGas()
  const [open, setOpen] = useState(false); const [codigo, setCodigo] = useState(''); const [sitId, setSitId] = useState('')

  async function onBaixar() {
    if (!codigo || !sitId) { toast.error('Informe o código e a situação.'); return }
    try { await baixar.mutateAsync({ codigo, situacao_id: Number(sitId) }); toast.success('Vale-gás baixado.'); setOpen(false); setCodigo(''); setSitId('') }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao baixar.') }
  }

  const columns: Column<any>[] = [
    { key: 'codigo', header: 'Código', cell: (v) => <span className="font-medium tabular-nums">{v.codigo}</span> },
    { key: 'cliente', header: 'Cliente', cell: (v) => v.cliente || '—' },
    { key: 'produto', header: 'Produto', cell: (v) => v.produto || '—' },
    { key: 'ger', header: 'Geração', cell: (v) => fmtData(v.datageracao) },
    { key: 'sit', header: 'Situação', cell: (v) => v.databaixa ? <Badge variant="secondary">{v.situacao || 'Baixado'}</Badge> : <Badge variant="success">{v.situacao || 'Ativo'}</Badge> },
  ]
  return (
    <div>
      <PageHeader title="Vale-Gás" subtitle="Gás de Bolso — consulta e baixa"
        action={
          <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild><Button><CheckCircle2 size={16} /> Baixar vale</Button></DialogTrigger>
            <DialogContent>
              <DialogHeader><DialogTitle>Baixar vale-gás</DialogTitle></DialogHeader>
              <div className="space-y-4">
                <Field label="Código" required><Input value={codigo} onChange={(e) => setCodigo(e.target.value)} /></Field>
                <Field label="Situação" required>
                  <Select value={sitId} onValueChange={setSitId}>
                    <SelectTrigger><SelectValue placeholder="Selecione" /></SelectTrigger>
                    <SelectContent>{(situacoes ?? []).map((s) => <SelectItem key={s.id} value={String(s.id)}>{s.descricao}</SelectItem>)}</SelectContent>
                  </Select>
                </Field>
              </div>
              <DialogFooter><DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose><Button loading={baixar.isPending} onClick={onBaixar}>Baixar</Button></DialogFooter>
            </DialogContent>
          </Dialog>
        } />
      <Card className="mb-4 p-3"><form onSubmit={(e) => { e.preventDefault(); setQ(busca) }} className="flex gap-2">
        <div className="relative flex-1"><Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" /><Input value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Buscar por código…" className="pl-9" /></div>
        <Button type="submit" variant="secondary">Buscar</Button>
      </form></Card>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(v) => v.id} empty={<EmptyState icon={<Flame />} title="Nenhum vale-gás" />} />
    </div>
  )
}
