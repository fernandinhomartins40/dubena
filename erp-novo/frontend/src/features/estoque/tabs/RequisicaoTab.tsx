import { useState } from 'react'
import { ClipboardList } from 'lucide-react'
import {
  Button, DataTable, type Column, EmptyState, Badge, Field, Input,
  FormDialog, toast,
} from '@/components/ui'
import { useRequisicoes, useCriarRequisicao } from '../api'
import { dataHora as fmtData } from '@/lib/format'
import { ItensEditor } from './ItensEditor'

export function RequisicaoTab() {
  const { data, isLoading, error, refetch } = useRequisicoes()
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
        <Button onClick={() => { setObs(''); setItens([]); setOpen(true) }}><ClipboardList size={16} /> Nova requisição</Button>
        <FormDialog open={open} onOpenChange={(next) => { setOpen(next); if (!next) { setObs(''); setItens([]) } }} title="Nova requisição de estoque"
          dirty={!!obs || itens.length > 0} widthClass="max-w-3xl" loading={criar.isPending} onConfirm={salvar} confirmLabel="Registrar">
            <div className="space-y-4">
              <Field label="Observações"><Input value={obs} onChange={(e) => setObs(e.target.value)} /></Field>
              <ItensEditor itens={itens} setItens={setItens} comSetor />
            </div>
        </FormDialog>
      </div>
      <DataTable columns={columns} rows={data} loading={isLoading} error={error} onRetry={() => { void refetch() }} rowKey={(r) => r.id} empty={<EmptyState icon={<ClipboardList />} title="Nenhuma requisição" />} />
    </>
  )
}
