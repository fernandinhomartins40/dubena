import { useState } from 'react'
import { ClipboardList } from 'lucide-react'
import {
  Button, DataTable, type Column, EmptyState, Badge, Field, Input,
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogFooter, DialogClose, toast,
} from '@/components/ui'
import { useRequisicoes, useCriarRequisicao } from '../api'
import { dataHora as fmtData } from '@/lib/format'
import { ItensEditor } from './ItensEditor'

export function RequisicaoTab() {
  const { data, isLoading } = useRequisicoes()
  const criar = useCriarRequisicao()
  const [open, setOpen] = useState(false)
  const [obs, setObs] = useState(''); const [itens, setItens] = useState<any[]>([])

  async function salvar() {
    if (itens.length === 0) { toast.error('Adicione ao menos um item.'); return }
    try {
      await criar.mutateAsync({ observacoes: obs, itens: itens.map((i) => ({ produto_id: i.produto_id, setor_id: i.setor_id, quantidade: Number(i.quantidade), entradasaida: i.entradasaida })) })
      toast.success('Requisição registrada.'); setOpen(false); setItens([]); setObs('')
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro na requisição.') }
  }

  const columns: Column<any>[] = [
    { key: 'id', header: 'Nº', cell: (r) => `#${r.id}` },
    { key: 'data', header: 'Data', cell: (r) => fmtData(r.datahora) },
    { key: 'cancelado', header: 'Status', cell: (r) => Number(r.cancelado) ? <Badge variant="destructive">Cancelada</Badge> : <Badge variant="success">Ativa</Badge> },
  ]
  return (
    <>
      <div className="mb-3 flex justify-end">
        <Dialog open={open} onOpenChange={setOpen}>
          <DialogTrigger asChild><Button><ClipboardList size={16} /> Nova requisição</Button></DialogTrigger>
          <DialogContent className="max-w-3xl">
            <DialogHeader><DialogTitle>Nova requisição de estoque</DialogTitle></DialogHeader>
            <div className="space-y-4">
              <Field label="Observações"><Input value={obs} onChange={(e) => setObs(e.target.value)} /></Field>
              <ItensEditor itens={itens} setItens={setItens} comSetor />
            </div>
            <DialogFooter><DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose><Button loading={criar.isPending} onClick={salvar}>Registrar</Button></DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(r) => r.id} empty={<EmptyState icon={<ClipboardList />} title="Nenhuma requisição" />} />
    </>
  )
}
