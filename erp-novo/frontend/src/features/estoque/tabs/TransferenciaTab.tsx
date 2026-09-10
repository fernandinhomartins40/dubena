import { useState } from 'react'
import { ArrowRightLeft } from 'lucide-react'
import {
  Button, DataTable, type Column, EmptyState, Field, AsyncSelect, Input,
  FormDialog, toast,
} from '@/components/ui'
import { useTransferencias, useCriarTransferencia } from '../api'
import { dataHora as fmtData } from '@/lib/format'
import { ItensEditor } from './ItensEditor'

export function TransferenciaTab() {
  const { data, isLoading, error, refetch } = useTransferencias()
  const criar = useCriarTransferencia()
  const [open, setOpen] = useState(false)
  const [origem, setOrigem] = useState<number | null>(null); const [origemL, setOrigemL] = useState<string | null>(null)
  const [destino, setDestino] = useState<number | null>(null); const [destinoL, setDestinoL] = useState<string | null>(null)
  const [obs, setObs] = useState(''); const [itens, setItens] = useState<any[]>([])

  async function salvar() {
    if (!origem || !destino || itens.length === 0) { toast.error('Informe origem, destino e ao menos um item.'); return }
    try {
      await criar.mutateAsync({ origemsetor_id: origem, destinosetor_id: destino, observacoes: obs, itens: itens.map((i) => ({ produto_id: i.produto_id, quantidade: Number(i.quantidade) })) })
      toast.success('Transferência realizada.'); setOpen(false); setItens([]); setObs('')
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro na transferência.') }
  }

  const columns: Column<any>[] = [
    { key: 'id', header: 'Nº', cell: (r) => `#${r.id}` },
    { key: 'data', header: 'Data', cell: (r) => fmtData(r.datahora) },
    { key: 'obs', header: 'Observações', cell: (r) => <span className="text-muted-foreground">{r.observacoes || '—'}</span> },
  ]
  return (
    <>
      <div className="mb-3 flex justify-end">
        <Button onClick={() => { setOrigem(null); setOrigemL(null); setDestino(null); setDestinoL(null); setObs(''); setItens([]); setOpen(true) }}><ArrowRightLeft size={16} /> Nova transferência</Button>
        <FormDialog open={open} onOpenChange={(next) => { setOpen(next); if (!next) { setOrigem(null); setOrigemL(null); setDestino(null); setDestinoL(null); setObs(''); setItens([]) } }} title="Nova transferência entre setores"
          dirty={origem !== null || destino !== null || !!obs || itens.length > 0} widthClass="max-w-3xl" loading={criar.isPending} onConfirm={salvar} confirmLabel="Transferir">
            <div className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Field label="Setor de origem" required><AsyncSelect endpoint="/lookups/setores" value={origem} valueLabel={origemL} onChange={(id, o) => { setOrigem(id); setOrigemL(o?.label ?? null) }} /></Field>
                <Field label="Setor de destino" required><AsyncSelect endpoint="/lookups/setores" value={destino} valueLabel={destinoL} onChange={(id, o) => { setDestino(id); setDestinoL(o?.label ?? null) }} /></Field>
              </div>
              <Field label="Observações"><Input value={obs} onChange={(e) => setObs(e.target.value)} /></Field>
              <ItensEditor itens={itens} setItens={setItens} />
            </div>
        </FormDialog>
      </div>
      <DataTable columns={columns} rows={data} loading={isLoading} error={error} onRetry={() => { void refetch() }} rowKey={(r) => r.id} empty={<EmptyState icon={<ArrowRightLeft />} title="Nenhuma transferência" />} />
    </>
  )
}
