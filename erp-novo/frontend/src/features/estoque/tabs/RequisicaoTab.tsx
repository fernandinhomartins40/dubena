import { useState } from 'react'
import { ClipboardList, PackageCheck } from 'lucide-react'
import {
  Button, DataTable, type Column, EmptyState, Badge, Field, Input, AsyncSelect, Checkbox,
  FormDialog, toast,
} from '@/components/ui'
import { useRequisicoes, useCriarRequisicao, useAtenderRequisicao, type RequisicaoRow } from '../api'
import { dataHora as fmtData, qtd } from '@/lib/format'

const SITUACAO: Record<RequisicaoRow['situacao'], { rotulo: string; variant: 'warning' | 'success' | 'destructive' }> = {
  pendente: { rotulo: 'Pendente', variant: 'warning' },
  atendida: { rotulo: 'Atendida', variant: 'success' },
  cancelada: { rotulo: 'Cancelada', variant: 'destructive' },
}

/**
 * Requisição: um setor pede um produto a outro. Atender é transferir.
 *
 * A origem é opcional no pedido ("preciso de 10 no balcão") e pode ser
 * decidida por quem atende — por isso o atendimento pede a origem quando ela
 * ficou em aberto.
 */
export function RequisicaoTab() {
  const { data, isLoading, error, refetch } = useRequisicoes()
  const criar = useCriarRequisicao()
  const atender = useAtenderRequisicao()
  const [open, setOpen] = useState(false)
  const [origem, setOrigem] = useState<number | null>(null); const [origemL, setOrigemL] = useState<string | null>(null)
  const [destino, setDestino] = useState<number | null>(null); const [destinoL, setDestinoL] = useState<string | null>(null)
  const [produto, setProduto] = useState<number | null>(null); const [produtoL, setProdutoL] = useState<string | null>(null)
  const [qtde, setQtde] = useState(''); const [obs, setObs] = useState(''); const [atenderJa, setAtenderJa] = useState(false)
  // Requisição pendente sem origem, aguardando a escolha de quem atende.
  const [atendendo, setAtendendo] = useState<RequisicaoRow | null>(null)
  const [origemAt, setOrigemAt] = useState<number | null>(null); const [origemAtL, setOrigemAtL] = useState<string | null>(null)

  function limpar() {
    setOrigem(null); setOrigemL(null); setDestino(null); setDestinoL(null); setProduto(null); setProdutoL(null)
    setQtde(''); setObs(''); setAtenderJa(false)
  }

  async function salvar() {
    if (!destino || !produto || !(Number(qtde) > 0)) { toast.error('Informe destino, produto e quantidade.'); return }
    if (origem === destino) { toast.error('Origem e destino devem ser setores diferentes.'); return }
    if (atenderJa && !origem) { toast.error('Para atender agora, informe o setor de origem.'); return }
    try {
      await criar.mutateAsync({ setor_origem_id: origem, setor_destino_id: destino, produto_id: produto, quantidade: Number(qtde), observacao: obs.trim() || null, atender: atenderJa })
      toast.success(atenderJa ? 'Requisição atendida.' : 'Requisição registrada.'); setOpen(false); limpar()
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro na requisição.') }
  }

  async function onAtender(r: RequisicaoRow, origemId: number | null) {
    try {
      await atender.mutateAsync({ id: r.id, setor_origem_id: origemId })
      toast.success('Requisição atendida.'); setAtendendo(null); setOrigemAt(null); setOrigemAtL(null)
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao atender.') }
  }

  function iniciarAtendimento(r: RequisicaoRow) {
    if (r.setor_origem_id) { void onAtender(r, null); return }
    setOrigemAt(null); setOrigemAtL(null); setAtendendo(r)
  }

  const columns: Column<RequisicaoRow>[] = [
    { key: 'id', header: 'Nº', cell: (r) => `#${r.id}` },
    { key: 'data', header: 'Data', cell: (r) => fmtData(r.created_at) },
    { key: 'produto', header: 'Produto', cell: (r) => <span className="font-medium">{r.produto?.descricao ?? `#${r.produto_id}`}</span> },
    { key: 'qtd', header: 'Quantidade', align: 'right', cell: (r) => <span className="tabular-nums">{qtd(Number(r.quantidade))}</span> },
    { key: 'origem', header: 'Origem', cell: (r) => r.setor_origem?.descricao ?? <span className="text-muted-foreground">A definir</span> },
    { key: 'destino', header: 'Destino', cell: (r) => r.setor_destino?.descricao ?? `#${r.setor_destino_id}` },
    { key: 'situacao', header: 'Situação', cell: (r) => <Badge variant={SITUACAO[r.situacao]?.variant ?? 'warning'}>{SITUACAO[r.situacao]?.rotulo ?? r.situacao}</Badge> },
    { key: 'acoes', header: '', align: 'right', cell: (r) => r.situacao === 'pendente' && <Button variant="outline" size="sm" loading={atender.isPending} onClick={() => iniciarAtendimento(r)}><PackageCheck size={14} /> Atender</Button> },
  ]
  return (
    <>
      <div className="mb-3 flex justify-end">
        <Button onClick={() => { limpar(); setOpen(true) }}><ClipboardList size={16} /> Nova requisição</Button>
        <FormDialog open={open} onOpenChange={(next) => { setOpen(next); if (!next) limpar() }} title="Nova requisição de estoque"
          dirty={origem !== null || destino !== null || produto !== null || !!qtde || !!obs} widthClass="max-w-2xl" loading={criar.isPending} onConfirm={salvar} confirmLabel="Registrar">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Field label="Setor de destino" required><AsyncSelect endpoint="/lookups/setores" value={destino} valueLabel={destinoL} onChange={(id, o) => { setDestino(id); setDestinoL(o?.label ?? null) }} /></Field>
              <Field label="Setor de origem"><AsyncSelect endpoint="/lookups/setores" value={origem} valueLabel={origemL} placeholder="A definir no atendimento" onChange={(id, o) => { setOrigem(id); setOrigemL(o?.label ?? null) }} /></Field>
              <Field label="Produto" required><AsyncSelect endpoint="/lookups/produtos" value={produto} valueLabel={produtoL} onChange={(id, o) => { setProduto(id); setProdutoL(o?.label ?? null) }} /></Field>
              <Field label="Quantidade" required><Input type="number" min="0" step="0.001" value={qtde} onChange={(e) => setQtde(e.target.value)} /></Field>
              <Field label="Observação" className="md:col-span-2"><Input maxLength={255} value={obs} onChange={(e) => setObs(e.target.value)} /></Field>
              <label className="md:col-span-2 flex items-center gap-2 text-sm">
                <Checkbox checked={atenderJa} onCheckedChange={(v) => setAtenderJa(v === true)} /> Atender agora (transfere da origem para o destino)
              </label>
            </div>
        </FormDialog>
      </div>
      <FormDialog open={atendendo !== null} onOpenChange={(next) => { if (!next) setAtendendo(null) }} title={`Atender requisição #${atendendo?.id ?? ''}`}
        dirty={origemAt !== null} loading={atender.isPending} confirmLabel="Atender"
        onConfirm={() => { if (!origemAt) { toast.error('Informe de qual setor a mercadoria sai.'); return } if (atendendo) void onAtender(atendendo, origemAt) }}>
          <Field label="Setor de origem" required><AsyncSelect endpoint="/lookups/setores" value={origemAt} valueLabel={origemAtL} onChange={(id, o) => { setOrigemAt(id); setOrigemAtL(o?.label ?? null) }} /></Field>
      </FormDialog>
      <DataTable columns={columns} rows={data} loading={isLoading} error={error} onRetry={() => { void refetch() }} rowKey={(r) => r.id} empty={<EmptyState icon={<ClipboardList />} title="Nenhuma requisição" />} />
    </>
  )
}
