import { useState } from 'react'
import { Plus, Trash2, PackageCheck } from 'lucide-react'
import {
  Button, DataTable, type Column, EmptyState, Badge, Field, AsyncSelect, Input,
  FormDialog, ConfirmDialog, toast,
} from '@/components/ui'
import { useFisicos, useCriarFisico, useEfetivarFisico, type InventarioRow } from '../api'
import { data as fmtData, qtd } from '@/lib/format'

interface ItemContado { produto_id: number | null; produtoLabel: string | null; quantidade: string }

const hoje = () => new Date().toLocaleDateString('en-CA') // AAAA-MM-DD no fuso local

/**
 * Estoque físico (inventário): conta-se um setor e, ao efetivar, o saldo é
 * ajustado para o contado.
 *
 * A tela NÃO pede a "quantidade do sistema": o servidor a lê do ledger no
 * instante da efetivação. Digitada à mão ela já estaria velha quando alguém
 * aprovasse — e o ajuste sairia calculado contra um saldo que não existe mais.
 */
export function FisicoTab() {
  const { data, isLoading, error, refetch } = useFisicos()
  const criar = useCriarFisico()
  const efetivar = useEfetivarFisico()
  const [confirmando, setConfirmando] = useState<number | null>(null)
  const [open, setOpen] = useState(false)
  const [setor, setSetor] = useState<number | null>(null); const [setorL, setSetorL] = useState<string | null>(null)
  const [dataCont, setDataCont] = useState(hoje); const [itens, setItens] = useState<ItemContado[]>([])

  function limpar() { setSetor(null); setSetorL(null); setDataCont(hoje()); setItens([]) }

  async function salvar() {
    if (!setor || !dataCont || itens.length === 0) { toast.error('Informe o setor, a data e os itens.'); return }
    if (itens.some((i) => !i.produto_id || i.quantidade === '' || Number(i.quantidade) < 0)) { toast.error('Cada item precisa de produto e quantidade contada.'); return }
    try {
      await criar.mutateAsync({ setor_id: setor, data: dataCont, itens: itens.map((i) => ({ produto_id: i.produto_id as number, quantidade_contada: Number(i.quantidade) })) })
      toast.success('Contagem registrada.'); setOpen(false); limpar()
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro no estoque físico.') }
  }

  async function onEfetivar(id: number) {
    try { await efetivar.mutateAsync(id); toast.success('Estoque físico efetivado.'); setConfirmando(null) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao efetivar.') }
  }

  const columns: Column<InventarioRow>[] = [
    { key: 'id', header: 'Nº', cell: (r) => `#${r.id}` },
    { key: 'data', header: 'Data', cell: (r) => fmtData(r.data.slice(0, 10)) },
    { key: 'setor', header: 'Setor', cell: (r) => r.setor?.descricao ?? `#${r.setor_id}` },
    {
      key: 'itens', header: 'Contagem',
      cell: (r) => (
        <ul className="space-y-0.5">
          {r.itens.map((i) => (
            <li key={i.id} className="text-sm">
              {i.produto?.descricao ?? `#${i.produto_id}`}: <span className="tabular-nums font-medium">{qtd(Number(i.quantidade_contada))}</span>
              {i.quantidade_sistema !== null && <span className="text-muted-foreground"> (sistema tinha {qtd(Number(i.quantidade_sistema))})</span>}
            </li>
          ))}
        </ul>
      ),
    },
    { key: 'situacao', header: 'Situação', cell: (r) => r.situacao === 'efetivado' ? <Badge variant="success">Efetivado</Badge> : <Badge variant="warning">Pendente</Badge> },
    { key: 'acoes', header: '', align: 'right', cell: (r) => r.situacao !== 'efetivado' && <Button variant="outline" size="sm" loading={efetivar.isPending} onClick={() => setConfirmando(r.id)}><PackageCheck size={14} /> Efetivar</Button> },
  ]
  return (
    <>
      <div className="mb-3 flex justify-end">
        <Button onClick={() => { limpar(); setOpen(true) }}><PackageCheck size={16} /> Novo estoque físico</Button>
        <FormDialog open={open} onOpenChange={(next) => { setOpen(next); if (!next) limpar() }} title="Registrar estoque físico"
          dirty={setor !== null || itens.length > 0} widthClass="max-w-3xl" loading={criar.isPending} onConfirm={salvar} confirmLabel="Registrar">
            <div className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <Field label="Setor contado" required><AsyncSelect endpoint="/lookups/setores" value={setor} valueLabel={setorL} onChange={(id, o) => { setSetor(id); setSetorL(o?.label ?? null) }} /></Field>
                <Field label="Data da contagem" required><Input type="date" value={dataCont} onChange={(e) => setDataCont(e.target.value)} /></Field>
              </div>
              <p className="text-xs text-muted-foreground">Informe só o que foi contado. Ao efetivar, o sistema compara com o saldo daquele momento e lança a diferença.</p>
              <FisicoItens itens={itens} setItens={setItens} />
            </div>
        </FormDialog>
      </div>
      <ConfirmDialog open={confirmando !== null} onOpenChange={(next) => { if (!next) setConfirmando(null) }} title="Efetivar estoque físico"
        description={<>Efetivar o registro <strong>#{confirmando}</strong>? O saldo de cada produto contado passa a ser a quantidade informada.</>}
        confirmLabel="Efetivar" variant="default" loading={efetivar.isPending} onConfirm={() => { if (confirmando !== null) void onEfetivar(confirmando) }} />
      <DataTable columns={columns} rows={data} loading={isLoading} error={error} onRetry={() => { void refetch() }} rowKey={(r) => r.id} empty={<EmptyState icon={<PackageCheck />} title="Nenhum estoque físico" />} />
    </>
  )
}

function FisicoItens({ itens, setItens }: { itens: ItemContado[]; setItens: (i: ItemContado[]) => void }) {
  const add = () => setItens([...itens, { produto_id: null, produtoLabel: null, quantidade: '' }])
  const set = (i: number, patch: Partial<ItemContado>) => setItens(itens.map((it, idx) => idx === i ? { ...it, ...patch } : it))
  const rm = (i: number) => setItens(itens.filter((_, idx) => idx !== i))
  return (
    <div className="space-y-3">
      <div className="flex justify-between items-center"><p className="text-sm font-medium">Itens</p><Button variant="outline" size="sm" onClick={add}><Plus size={16} /> Adicionar</Button></div>
      {itens.map((it, i) => (
        <div key={i} className="grid grid-cols-1 md:grid-cols-12 gap-3 items-end rounded-lg border border-border p-3">
          <div className="md:col-span-7"><Field label="Produto"><AsyncSelect endpoint="/lookups/produtos" value={it.produto_id} valueLabel={it.produtoLabel} onChange={(id, o) => set(i, { produto_id: id, produtoLabel: o?.label ?? null })} /></Field></div>
          <div className="md:col-span-4"><Field label="Quantidade contada"><Input type="number" min="0" step="0.001" value={it.quantidade} onChange={(e) => set(i, { quantidade: e.target.value })} /></Field></div>
          <div className="md:col-span-1 flex justify-end"><Button variant="ghost" size="icon" aria-label={`Remover item ${i + 1}`} onClick={() => rm(i)}><Trash2 size={16} /></Button></div>
        </div>
      ))}
    </div>
  )
}
