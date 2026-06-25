import { useState } from 'react'
import { PackageCheck } from 'lucide-react'
import {
  Button, DataTable, type Column, EmptyState, Field, Input,
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogFooter, DialogClose, toast,
} from '@/components/ui'
import { useInventarios, useCriarInventario } from '../api'
import { brl } from '@/lib/format'
import { ItensEditor } from './ItensEditor'

export function InventarioTab() {
  const { data, isLoading } = useInventarios()
  const criar = useCriarInventario()
  const [open, setOpen] = useState(false)
  const [dataInv, setDataInv] = useState(''); const [mes, setMes] = useState(''); const [itens, setItens] = useState<any[]>([])

  async function salvar() {
    if (!dataInv || !mes || itens.length === 0) { toast.error('Informe data, mês e itens.'); return }
    try {
      await criar.mutateAsync({ datainventario: dataInv, mesentrega: mes + '-01', itens: itens.map((i) => ({ produto_id: i.produto_id, quantidade: Number(i.quantidade), valorunitario: Number(i.valorunitario) })) })
      toast.success('Inventário gravado.'); setOpen(false); setItens([])
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro no inventário.') }
  }

  const columns: Column<any>[] = [
    { key: 'id', header: 'Nº', cell: (r) => `#${r.id}` },
    { key: 'data', header: 'Data', cell: (r) => r.datainventario },
    { key: 'valor', header: 'Valor', align: 'right', cell: (r) => <span className="tabular-nums">{brl(r.valorinventario)}</span> },
  ]
  return (
    <>
      <div className="mb-3 flex justify-end">
        <Dialog open={open} onOpenChange={setOpen}>
          <DialogTrigger asChild><Button><PackageCheck size={16} /> Novo inventário</Button></DialogTrigger>
          <DialogContent className="max-w-3xl">
            <DialogHeader><DialogTitle>Novo inventário (valoração)</DialogTitle></DialogHeader>
            <div className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <Field label="Data do inventário" required><Input type="date" value={dataInv} onChange={(e) => setDataInv(e.target.value)} /></Field>
                <Field label="Mês de entrega" required><Input type="month" value={mes} onChange={(e) => setMes(e.target.value)} /></Field>
              </div>
              <ItensEditor itens={itens} setItens={setItens} comValor />
            </div>
            <DialogFooter><DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose><Button loading={criar.isPending} onClick={salvar}>Gravar</Button></DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(r) => r.id} empty={<EmptyState icon={<PackageCheck />} title="Nenhum inventário" />} />
    </>
  )
}
