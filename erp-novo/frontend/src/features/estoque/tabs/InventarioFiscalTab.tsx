import { useState } from 'react'
import { FileSpreadsheet, Plus, Trash2, Wand2 } from 'lucide-react'
import {
  Button, DataTable, type Column, EmptyState, Field, AsyncSelect, Input,
  FormDialog, toast,
} from '@/components/ui'
import {
  useInventariosFiscais, useCriarInventarioFiscal, useExcluirInventarioFiscal,
  buscarSugestaoInventarioFiscal, type InventarioFiscalRow,
} from '../api'
import { brl, data as fmtData, qtd } from '@/lib/format'

interface ItemDeclarado { produto_id: number | null; produtoLabel: string | null; quantidade: string; valor_unitario: string }

const mesAno = (iso: string) => `${iso.slice(5, 7)}/${iso.slice(0, 4)}`
// Mesma conta do servidor: arredonda item a item e soma os arredondados, para
// o total da tela ser o que vai no SPED.
const totalDe = (itens: ItemDeclarado[]) =>
  itens.reduce((s, i) => s + Math.round(Number(i.quantidade || 0) * Number(i.valor_unitario || 0) * 100) / 100, 0)

/**
 * Inventário fiscal — o estoque declarado ao fisco (SPED Fiscal, Bloco H).
 *
 * Não é a contagem física (aba "Inventário físico"): aqui não há setor, há
 * valor, e gravar NÃO altera saldo. O SPED do mês de entrega passa a levar
 * exatamente o que foi declarado aqui.
 *
 * Não há edição: um inventário pode já ter sido entregue, e corrigir é excluir
 * (com motivo) e gravar de novo.
 */
export function InventarioFiscalTab() {
  const { data, isLoading, error, refetch } = useInventariosFiscais()
  const criar = useCriarInventarioFiscal()
  const excluir = useExcluirInventarioFiscal()
  const [open, setOpen] = useState(false)
  const [mes, setMes] = useState(''); const [dataInv, setDataInv] = useState(''); const [itens, setItens] = useState<ItemDeclarado[]>([])
  const [sugerindo, setSugerindo] = useState(false)
  const [excluindo, setExcluindo] = useState<InventarioFiscalRow | null>(null); const [motivo, setMotivo] = useState('')

  function limpar() { setMes(''); setDataInv(''); setItens([]) }

  async function preencher() {
    setSugerindo(true)
    try {
      const sugestao = await buscarSugestaoInventarioFiscal()
      if (sugestao.length === 0) { toast.error('Nenhum produto com saldo em estoque.'); return }
      setItens(sugestao.map((s) => ({ produto_id: s.produto_id, produtoLabel: s.descricao, quantidade: String(s.quantidade), valor_unitario: s.valor_unitario === null ? '' : String(s.valor_unitario) })))
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Não foi possível buscar o estoque atual.') } finally { setSugerindo(false) }
  }

  async function salvar() {
    if (!mes || !dataInv || itens.length === 0) { toast.error('Informe o mês de entrega, a data do inventário e os itens.'); return }
    if (itens.some((i) => !i.produto_id || i.quantidade === '' || i.valor_unitario === '' || Number(i.quantidade) < 0 || Number(i.valor_unitario) < 0)) { toast.error('Cada item precisa de produto, quantidade e valor unitário.'); return }
    try {
      await criar.mutateAsync({ mes_entrega: `${mes}-01`, data_inventario: dataInv, itens: itens.map((i) => ({ produto_id: i.produto_id as number, quantidade: Number(i.quantidade), valor_unitario: Number(i.valor_unitario) })) })
      toast.success('Inventário fiscal gravado.'); setOpen(false); limpar()
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao gravar o inventário.') }
  }

  async function onExcluir() {
    if (!excluindo) return
    if (motivo.trim().length < 3) { toast.error('Informe o motivo da exclusão.'); return }
    try {
      await excluir.mutateAsync({ id: excluindo.id, motivo: motivo.trim() })
      toast.success('Inventário excluído.'); setExcluindo(null); setMotivo('')
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao excluir.') }
  }

  const columns: Column<InventarioFiscalRow>[] = [
    { key: 'id', header: 'Nº', cell: (r) => `#${r.id}` },
    { key: 'entrega', header: 'Entrega (SPED)', cell: (r) => <span className="font-medium">{mesAno(r.mes_entrega)}</span> },
    { key: 'data', header: 'Data do inventário', cell: (r) => fmtData(r.data_inventario.slice(0, 10)) },
    {
      key: 'itens', header: 'Itens',
      cell: (r) => (
        <ul className="space-y-0.5">
          {r.itens.map((i) => (
            <li key={i.id} className="text-sm">
              {i.descricao_snapshot ?? `#${i.produto_id}`}: <span className="tabular-nums">{qtd(Number(i.quantidade))}</span>
              <span className="text-muted-foreground"> × {brl(Number(i.valor_unitario))}</span>
            </li>
          ))}
        </ul>
      ),
    },
    { key: 'valor', header: 'Valor declarado', align: 'right', cell: (r) => <span className="tabular-nums font-medium">{brl(Number(r.valor_total))}</span> },
    { key: 'acoes', header: '', align: 'right', cell: (r) => <Button variant="ghost" size="icon" aria-label={`Excluir inventário ${r.id}`} onClick={() => { setMotivo(''); setExcluindo(r) }}><Trash2 size={16} /></Button> },
  ]
  return (
    <>
      <div className="mb-3 flex justify-end">
        <Button onClick={() => { limpar(); setOpen(true) }}><FileSpreadsheet size={16} /> Novo inventário fiscal</Button>
        <FormDialog open={open} onOpenChange={(next) => { setOpen(next); if (!next) limpar() }} title="Novo inventário fiscal (SPED — Bloco H)"
          dirty={!!mes || !!dataInv || itens.length > 0} widthClass="max-w-3xl" loading={criar.isPending} onConfirm={salvar} confirmLabel="Gravar">
            <div className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <Field label="Data do inventário" required><Input type="date" value={dataInv} onChange={(e) => setDataInv(e.target.value)} /></Field>
                <Field label="Mês de entrega (SPED)" required><Input type="month" value={mes} onChange={(e) => setMes(e.target.value)} /></Field>
              </div>
              <p className="text-xs text-muted-foreground">O mês de entrega é o da escrituração que leva este inventário — o de 31/12 costuma ir na de fevereiro. Gravar não altera o saldo do estoque.</p>
              <div className="flex justify-between items-center">
                <p className="text-sm font-medium">Itens</p>
                <div className="flex gap-2">
                  <Button variant="outline" size="sm" loading={sugerindo} onClick={() => { void preencher() }}><Wand2 size={16} /> Preencher com o estoque atual</Button>
                  <Button variant="outline" size="sm" onClick={() => setItens([...itens, { produto_id: null, produtoLabel: null, quantidade: '', valor_unitario: '' }])}><Plus size={16} /> Adicionar item</Button>
                </div>
              </div>
              <p className="text-xs text-muted-foreground">"Estoque atual" é o saldo e o custo médio de agora, não os da data do inventário. Confira e ajuste antes de gravar.</p>
              {itens.length === 0 && <p className="text-sm text-muted-foreground">Nenhum item adicionado.</p>}
              {itens.map((it, i) => (
                <div key={i} className="grid grid-cols-1 md:grid-cols-12 gap-3 items-end rounded-lg border border-border p-3">
                  <div className="md:col-span-5"><Field label="Produto"><AsyncSelect endpoint="/lookups/produtos" value={it.produto_id} valueLabel={it.produtoLabel} onChange={(id, o) => setItens(itens.map((x, idx) => idx === i ? { ...x, produto_id: id, produtoLabel: o?.label ?? null } : x))} /></Field></div>
                  <div className="md:col-span-3"><Field label="Quantidade"><Input type="number" min="0" step="0.001" value={it.quantidade} onChange={(e) => setItens(itens.map((x, idx) => idx === i ? { ...x, quantidade: e.target.value } : x))} /></Field></div>
                  <div className="md:col-span-3"><Field label="Valor unitário"><Input type="number" min="0" step="0.0001" value={it.valor_unitario} onChange={(e) => setItens(itens.map((x, idx) => idx === i ? { ...x, valor_unitario: e.target.value } : x))} /></Field></div>
                  <div className="md:col-span-1 flex justify-end"><Button variant="ghost" size="icon" aria-label={`Remover item ${i + 1}`} onClick={() => setItens(itens.filter((_, idx) => idx !== i))}><Trash2 size={16} /></Button></div>
                </div>
              ))}
              {itens.length > 0 && <p className="text-right text-sm">Valor total declarado: <strong className="tabular-nums">{brl(totalDe(itens))}</strong></p>}
            </div>
        </FormDialog>
      </div>
      <FormDialog open={excluindo !== null} onOpenChange={(next) => { if (!next) { setExcluindo(null); setMotivo('') } }} title={`Excluir inventário fiscal #${excluindo?.id ?? ''}`}
        dirty={!!motivo} loading={excluir.isPending} onConfirm={onExcluir} confirmLabel="Excluir">
          <div className="space-y-3">
            <p className="text-sm text-muted-foreground">Se este inventário já foi entregue, o próximo SPED gerado para {excluindo ? mesAno(excluindo.mes_entrega) : ''} deixará de levá-lo.</p>
            <Field label="Motivo" required><Input maxLength={255} value={motivo} onChange={(e) => setMotivo(e.target.value)} /></Field>
          </div>
      </FormDialog>
      <DataTable columns={columns} rows={data} loading={isLoading} error={error} onRetry={() => { void refetch() }} rowKey={(r) => r.id}
        empty={<EmptyState icon={<FileSpreadsheet />} title="Nenhum inventário fiscal" description="Sem inventário declarado, o SPED monta o Bloco H com o saldo do momento da geração." />} />
    </>
  )
}
