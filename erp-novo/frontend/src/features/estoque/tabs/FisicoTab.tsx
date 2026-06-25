import { useState } from 'react'
import { Plus, Trash2, PackageCheck } from 'lucide-react'
import {
  Button, DataTable, type Column, EmptyState, Badge, Field, AsyncSelect, Input,
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogFooter, DialogClose, toast,
} from '@/components/ui'
import { useFisicos, useCriarFisico, useEfetivarFisico } from '../api'
import { dataHora as fmtData } from '@/lib/format'

export function FisicoTab() {
  const { data, isLoading } = useFisicos()
  const criar = useCriarFisico()
  const efetivar = useEfetivarFisico()
  const [open, setOpen] = useState(false)
  const [dataComp, setDataComp] = useState(''); const [itens, setItens] = useState<any[]>([])

  async function salvar() {
    if (!dataComp || itens.length === 0) { toast.error('Informe a data e os itens.'); return }
    try {
      await criar.mutateAsync({ datacompetencia: dataComp, itens: itens.map((i) => ({ setor_id: i.setor_id, produto_id: i.produto_id, quantidadesistema: Number(i.quantidadesistema || 0), quantidadefisica: Number(i.quantidade || 0) })) })
      toast.success('Estoque físico registrado.'); setOpen(false); setItens([])
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro no estoque físico.') }
  }

  async function onEfetivar(id: number) {
    try { await efetivar.mutateAsync(id); toast.success('Estoque físico efetivado.') }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao efetivar.') }
  }

  const columns: Column<any>[] = [
    { key: 'id', header: 'Nº', cell: (r) => `#${r.id}` },
    { key: 'data', header: 'Competência', cell: (r) => fmtData(r.datacompetencia) },
    { key: 'efetivado', header: 'Status', cell: (r) => Number(r.efetivado) ? <Badge variant="success">Efetivado</Badge> : <Badge variant="warning">Pendente</Badge> },
    { key: 'acoes', header: '', align: 'right', cell: (r) => !Number(r.efetivado) && <Button variant="outline" size="sm" loading={efetivar.isPending} onClick={() => onEfetivar(r.id)}><PackageCheck size={14} /> Efetivar</Button> },
  ]
  return (
    <>
      <div className="mb-3 flex justify-end">
        <Dialog open={open} onOpenChange={setOpen}>
          <DialogTrigger asChild><Button><PackageCheck size={16} /> Novo estoque físico</Button></DialogTrigger>
          <DialogContent className="max-w-3xl">
            <DialogHeader><DialogTitle>Registrar estoque físico</DialogTitle></DialogHeader>
            <div className="space-y-4">
              <Field label="Data de competência" required><Input type="datetime-local" value={dataComp} onChange={(e) => setDataComp(e.target.value)} /></Field>
              <p className="text-xs text-muted-foreground">Informe a quantidade do sistema e a contada fisicamente; ao efetivar, a diferença ajusta o saldo.</p>
              <FisicoItens itens={itens} setItens={setItens} />
            </div>
            <DialogFooter><DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose><Button loading={criar.isPending} onClick={salvar}>Registrar</Button></DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(r) => r.id} empty={<EmptyState icon={<PackageCheck />} title="Nenhum estoque físico" />} />
    </>
  )
}

function FisicoItens({ itens, setItens }: { itens: any[]; setItens: (i: any[]) => void }) {
  const add = () => setItens([...itens, { produto_id: null, produtoLabel: null, setor_id: null, setorLabel: null, quantidadesistema: '', quantidade: '' }])
  const set = (i: number, patch: any) => setItens(itens.map((it, idx) => idx === i ? { ...it, ...patch } : it))
  const rm = (i: number) => setItens(itens.filter((_, idx) => idx !== i))
  return (
    <div className="space-y-3">
      <div className="flex justify-between items-center"><p className="text-sm font-medium">Itens</p><Button variant="outline" size="sm" onClick={add}><Plus size={16} /> Adicionar</Button></div>
      {itens.map((it, i) => (
        <div key={i} className="grid grid-cols-1 md:grid-cols-12 gap-3 items-end rounded-lg border border-border p-3">
          <div className="md:col-span-4"><Field label="Produto"><AsyncSelect endpoint="/lookups/produtos" value={it.produto_id} valueLabel={it.produtoLabel} onChange={(id, o) => set(i, { produto_id: id, produtoLabel: o?.label ?? null })} /></Field></div>
          <div className="md:col-span-3"><Field label="Setor"><AsyncSelect endpoint="/lookups/setores" value={it.setor_id} valueLabel={it.setorLabel} onChange={(id, o) => set(i, { setor_id: id, setorLabel: o?.label ?? null })} /></Field></div>
          <div className="md:col-span-2"><Field label="Qtd sistema"><Input type="number" step="0.0001" value={it.quantidadesistema} onChange={(e) => set(i, { quantidadesistema: e.target.value })} /></Field></div>
          <div className="md:col-span-2"><Field label="Qtd física"><Input type="number" step="0.0001" value={it.quantidade} onChange={(e) => set(i, { quantidade: e.target.value })} /></Field></div>
          <div className="md:col-span-1 flex justify-end"><Button variant="ghost" size="icon" onClick={() => rm(i)}><Trash2 size={16} /></Button></div>
        </div>
      ))}
    </div>
  )
}
