import { useState } from 'react'
import { Lock } from 'lucide-react'
import {
  Button, Card, CardContent, DataTable, type Column, EmptyState, Badge, Field, Input,
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogFooter, DialogClose, toast,
} from '@/components/ui'
import { useFechamentos, useFechar, useAbrirFechamento } from '../api'
import { dataHora as fmtData } from '@/lib/format'

export function FechamentoTab() {
  const { data, isLoading } = useFechamentos()
  const fechar = useFechar()
  const abrir = useAbrirFechamento()
  const [dataF, setDataF] = useState('')
  const [abrirData, setAbrirData] = useState(''); const [motivo, setMotivo] = useState(''); const [openAbrir, setOpenAbrir] = useState(false)

  async function onFechar() {
    if (!dataF) { toast.error('Informe a data/hora de fechamento.'); return }
    try { await fechar.mutateAsync({ datahorafechamento: dataF.replace('T', ' ') + ':00' }); toast.success('Estoque fechado.'); setDataF('') }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao fechar.') }
  }
  async function onAbrir() {
    if (!abrirData || !motivo) { toast.error('Informe data e motivo.'); return }
    try { await abrir.mutateAsync({ datahorafechamento: abrirData.replace('T', ' ') + ':00', motivo }); toast.success('Estoque reaberto.'); setOpenAbrir(false); setMotivo('') }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao reabrir.') }
  }

  const columns: Column<any>[] = [
    { key: 'id', header: 'Nº', cell: (r) => `#${r.id}` },
    { key: 'data', header: 'Fechamento', cell: (r) => fmtData(r.datahorafechamento) },
    { key: 'reaberto', header: 'Status', cell: (r) => Number(r.reaberto) ? <Badge variant="warning">Reaberto</Badge> : <Badge variant="success">Fechado</Badge> },
  ]
  return (
    <>
      <Card className="mb-4"><CardContent className="pt-6 flex flex-wrap items-end gap-3">
        <Field label="Fechar estoque até"><Input type="datetime-local" value={dataF} onChange={(e) => setDataF(e.target.value)} /></Field>
        <Button loading={fechar.isPending} onClick={onFechar}><Lock size={16} /> Fechar estoque</Button>
        <Dialog open={openAbrir} onOpenChange={setOpenAbrir}>
          <DialogTrigger asChild><Button variant="outline">Reabrir período</Button></DialogTrigger>
          <DialogContent>
            <DialogHeader><DialogTitle>Reabrir estoque</DialogTitle></DialogHeader>
            <div className="space-y-4">
              <Field label="Reabrir a partir de" required><Input type="datetime-local" value={abrirData} onChange={(e) => setAbrirData(e.target.value)} /></Field>
              <Field label="Motivo" required><Input value={motivo} onChange={(e) => setMotivo(e.target.value)} /></Field>
            </div>
            <DialogFooter><DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose><Button loading={abrir.isPending} onClick={onAbrir}>Reabrir</Button></DialogFooter>
          </DialogContent>
        </Dialog>
      </CardContent></Card>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(r) => r.id} empty={<EmptyState icon={<Lock />} title="Nenhum fechamento" />} />
    </>
  )
}
