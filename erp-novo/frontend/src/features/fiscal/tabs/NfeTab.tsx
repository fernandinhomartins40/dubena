import { useState } from 'react'
import { FileText, Send, Ban, AlertCircle, Printer } from 'lucide-react'
import {
  Button, Input, Badge, DataTable, type Column, EmptyState, Field, FormDialog, SearchBar, toast,
} from '@/components/ui'
import { useNfe, useTransmitirNfe, useCancelarNfe, abrirDanfe, type NfeRow } from '../api'
import { dataHora as fmtData } from '@/lib/format'
import { useBusca } from '@/lib/useBusca'

/**
 * Situação da nota — os valores do enum `SituacaoNota` do backend.
 *
 * Antes isto era um mapa por CÓDIGO da SEFAZ (100/101), que a API nunca
 * devolveu: o resultado era toda nota cair no fallback "Pendente", inclusive as
 * 239 mil autorizadas.
 */
const SITUACAO_NFE: Record<string, { l: string; v: 'success' | 'warning' | 'destructive' | 'secondary' }> = {
  AUTORIZADA: { l: 'Autorizada', v: 'success' },
  CANCELADA: { l: 'Cancelada', v: 'destructive' },
  REJEITADA: { l: 'Rejeitada', v: 'destructive' },
  DENEGADA: { l: 'Denegada', v: 'destructive' },
  EMITIDA: { l: 'Emitida', v: 'warning' },
  RASCUNHO: { l: 'Rascunho', v: 'secondary' },
}

const fmtMoeda = (v: string | number | null | undefined) =>
  v == null ? '—' : Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })

export function NfeTab() {
  const { busca, setBusca, q, submit } = useBusca()
  const [pagina, setPagina] = useState(1)
  const { data: resposta, isLoading, isFetching } = useNfe(q, pagina)
  const data = resposta?.data
  const meta = resposta?.meta
  const transmitir = useTransmitirNfe(); const cancelar = useCancelarNfe()
  const [cancelando, setCancelando] = useState<NfeRow | null>(null); const [justif, setJustif] = useState('')

  async function onTransmitir(nf: NfeRow) {
    try { const r = await transmitir.mutateAsync(nf.id); toast.success(r?.message ?? 'Transmissão solicitada.') }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Falha na transmissão.') }
  }
  async function onImprimir(nf: NfeRow) {
    try { await abrirDanfe(nf.id) }
    catch (e: any) {
      // Com responseType blob o corpo do erro também vem como blob: sem ler o
      // texto, o motivo da recusa ("nota não autorizada") não chegaria à tela.
      let msg = 'Falha ao gerar o DANFE.'
      try { msg = JSON.parse(await (e?.response?.data as Blob).text())?.message ?? msg } catch { /* mantém o genérico */ }
      toast.error(msg)
    }
  }
  async function onCancelar() {
    if (justif.trim().length < 15) { toast.error('A justificativa deve ter ao menos 15 caracteres.'); return }
    try { await cancelar.mutateAsync({ id: cancelando!.id, justificativa: justif }); toast.success('Cancelamento solicitado.'); setCancelando(null); setJustif('') }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Falha no cancelamento.') }
  }

  const columns: Column<NfeRow>[] = [
    {
      key: 'num', header: 'Número',
      cell: (n) => <span className="font-medium tabular-nums">{n.serie ?? '—'}/{n.numero ?? '—'}</span>,
    },
    { key: 'modelo', header: 'Modelo', width: 'w-20', cell: (n) => n.modelo || '—' },
    {
      key: 'cliente', header: 'Cliente',
      cell: (n) => <span className="truncate">{n.cliente?.nome ?? '—'}</span>,
    },
    {
      key: 'chave', header: 'Chave de acesso',
      cell: (n) => <span className="text-xs text-muted-foreground tabular-nums">{n.chave || '—'}</span>,
    },
    { key: 'emissao', header: 'Emissão', cell: (n) => fmtData(n.emitida_em) },
    { key: 'valor', header: 'Valor', align: 'right', cell: (n) => fmtMoeda(n.valor_total) },
    {
      key: 'sit', header: 'Situação', align: 'center',
      cell: (n) => {
        const s = n.situacao ? SITUACAO_NFE[n.situacao] : undefined
        return s
          ? <Badge variant={s.v}>{s.l}</Badge>
          : <Badge variant="secondary">{n.situacao ?? '—'}</Badge>
      },
    },
    {
      key: 'acoes', header: '', align: 'right',
      cell: (n) => {
        // Transmitir/cancelar só fazem sentido conforme o estado: oferecer
        // "Transmitir" numa nota já autorizada convida ao erro.
        const podeTransmitir = n.situacao === 'RASCUNHO' || n.situacao === 'REJEITADA'
        const podeCancelar = n.situacao === 'AUTORIZADA'
        // DANFE de nota cancelada sai com tarja: reimprimir para arquivo e
        // legítimo, e o papel diz que a nota nao vale.
        const podeImprimir = n.situacao === 'AUTORIZADA' || n.situacao === 'CANCELADA'

        if (!podeTransmitir && !podeCancelar && !podeImprimir) return null

        return (
          <div className="flex justify-end gap-1" onClick={(e) => e.stopPropagation()}>
            {podeImprimir && (
              <Button variant="outline" size="sm" onClick={() => onImprimir(n)}>
                <Printer size={14} /> DANFE
              </Button>
            )}
            {podeTransmitir && (
              <Button variant="outline" size="sm" loading={transmitir.isPending} onClick={() => onTransmitir(n)}>
                <Send size={14} /> Transmitir
              </Button>
            )}
            {podeCancelar && (
              <Button variant="ghost" size="sm" onClick={() => setCancelando(n)}>
                <Ban size={14} /> Cancelar
              </Button>
            )}
          </div>
        )
      },
    },
  ]

  return (
    <>
      <div className="mb-4 flex items-start gap-2 rounded-lg border border-amber-300/40 bg-amber-50/60 dark:bg-amber-950/20 px-4 py-3 text-sm text-amber-700 dark:text-amber-300">
        <AlertCircle size={18} className="shrink-0 mt-0.5" />
        <span>A transmissão e o cancelamento dependem do <strong>certificado digital</strong> (configurável em Empresas → Fiscal) e do ambiente SEFAZ. Valide em <strong>homologação</strong> antes de usar em produção.</span>
      </div>
      <SearchBar
        value={busca}
        onChange={setBusca}
        onSearch={() => { setPagina(1); submit() }}
        placeholder="Buscar número ou chave de acesso…"
      />
      <DataTable
        columns={columns}
        rows={data}
        loading={isLoading}
        fetching={isFetching}
        rowKey={(n) => n.id}
        page={meta?.current_page}
        lastPage={meta?.last_page}
        onPageChange={setPagina}
        pageInfo={meta ? `${meta.total.toLocaleString('pt-BR')} nota(s)` : undefined}
        empty={<EmptyState icon={<FileText />} title="Nenhuma NF-e" description="Notas são geradas a partir de pedidos." />}
      />
      <FormDialog open={!!cancelando} onOpenChange={(o) => !o && setCancelando(null)}
        title={`Cancelar NF-e ${cancelando?.serie ?? ''}/${cancelando?.numero ?? ''}`}
        confirmLabel="Cancelar NF-e" loading={cancelar.isPending} onConfirm={onCancelar}>
        <Field label="Justificativa (mín. 15 caracteres)" required><Input value={justif} onChange={(e) => setJustif(e.target.value)} /></Field>
      </FormDialog>
    </>
  )
}
