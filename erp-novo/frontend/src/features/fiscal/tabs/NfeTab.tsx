import { useState } from 'react'
import { FileText, Send, Ban, AlertCircle } from 'lucide-react'
import {
  Button, Input, Badge, DataTable, type Column, EmptyState, Field, FormDialog, SearchBar, toast,
} from '@/components/ui'
import { useNfe, useTransmitirNfe, useCancelarNfe, type NfeRow } from '../api'
import { dataHora as fmtData } from '@/lib/format'
import { useBusca } from '@/lib/useBusca'

const SITUACAO_NFE: Record<number, { l: string; v: 'success' | 'warning' | 'destructive' | 'secondary' }> = {
  100: { l: 'Autorizada', v: 'success' },
  101: { l: 'Cancelada', v: 'destructive' },
  3: { l: 'Autorizada', v: 'success' },
}

export function NfeTab() {
  const { busca, setBusca, q, submit } = useBusca()
  const { data, isLoading } = useNfe(q)
  const transmitir = useTransmitirNfe(); const cancelar = useCancelarNfe()
  const [cancelando, setCancelando] = useState<NfeRow | null>(null); const [justif, setJustif] = useState('')

  async function onTransmitir(nf: NfeRow) {
    try { const r = await transmitir.mutateAsync(nf.id); toast.success(r?.message ?? 'Transmissão solicitada.') }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Falha na transmissão.') }
  }
  async function onCancelar() {
    if (justif.trim().length < 15) { toast.error('A justificativa deve ter ao menos 15 caracteres.'); return }
    try { await cancelar.mutateAsync({ id: cancelando!.id, justificativa: justif }); toast.success('Cancelamento solicitado.'); setCancelando(null); setJustif('') }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Falha no cancelamento.') }
  }

  const columns: Column<NfeRow>[] = [
    { key: 'num', header: 'Número', cell: (n) => <span className="font-medium tabular-nums">{n.nfserie}/{n.nfnumero}</span> },
    { key: 'modelo', header: 'Modelo', width: 'w-20', cell: (n) => n.nfmodelo || '—' },
    { key: 'chave', header: 'Chave de acesso', cell: (n) => <span className="text-xs text-muted-foreground tabular-nums">{n.chaveacesso || '—'}</span> },
    { key: 'emissao', header: 'Emissão', cell: (n) => fmtData(n.datahoraemissao) },
    { key: 'sit', header: 'Situação', align: 'center', cell: (n) => { const s = SITUACAO_NFE[Number(n.nfsituacao_id)]; return s ? <Badge variant={s.v}>{s.l}</Badge> : <Badge variant="warning">Pendente</Badge> } },
    {
      key: 'acoes', header: '', align: 'right', cell: (n) => (
        <div className="flex justify-end gap-1" onClick={(e) => e.stopPropagation()}>
          <Button variant="outline" size="sm" loading={transmitir.isPending} onClick={() => onTransmitir(n)}><Send size={14} /> Transmitir</Button>
          <Button variant="ghost" size="sm" onClick={() => setCancelando(n)}><Ban size={14} /> Cancelar</Button>
        </div>
      ),
    },
  ]

  return (
    <>
      <div className="mb-4 flex items-start gap-2 rounded-lg border border-amber-300/40 bg-amber-50/60 dark:bg-amber-950/20 px-4 py-3 text-sm text-amber-700 dark:text-amber-300">
        <AlertCircle size={18} className="shrink-0 mt-0.5" />
        <span>A transmissão e o cancelamento dependem do <strong>certificado digital</strong> (configurável em Empresas → Fiscal) e do ambiente SEFAZ. Valide em <strong>homologação</strong> antes de usar em produção.</span>
      </div>
      <SearchBar value={busca} onChange={setBusca} onSearch={submit} placeholder="Buscar número ou chave de acesso…" />
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(n) => n.id} empty={<EmptyState icon={<FileText />} title="Nenhuma NF-e" description="Notas são geradas a partir de pedidos." />} />
      <FormDialog open={!!cancelando} onOpenChange={(o) => !o && setCancelando(null)}
        title={`Cancelar NF-e ${cancelando?.nfserie ?? ''}/${cancelando?.nfnumero ?? ''}`}
        confirmLabel="Cancelar NF-e" loading={cancelar.isPending} onConfirm={onCancelar}>
        <Field label="Justificativa (mín. 15 caracteres)" required><Input value={justif} onChange={(e) => setJustif(e.target.value)} /></Field>
      </FormDialog>
    </>
  )
}
