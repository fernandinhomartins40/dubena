import { useState } from 'react'
import { Flame, CheckCircle2, Printer, Receipt } from 'lucide-react'
import {
  Button, PageHeader, Input, Badge, DataTable, type Column, EmptyState, Field, SearchBar,
  Select, SelectTrigger, SelectValue, SelectContent, SelectItem,
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogFooter, DialogClose, toast,
} from '@/components/ui'
import { useValeGas, useValeGasSituacoes, useBaixarValeGas, abrirValePdf, abrirDuplicata, type ValeGasRow } from './api'
import { useBusca } from '@/lib/useBusca'
import { mensagemDeErroBlob } from '@/lib/pdf'

const fmtData = (s: string | null) => (s ? new Date(s).toLocaleDateString('pt-BR') : '—')

/** Cores por situação — o enum `SituacaoValeGas` do backend. */
const SITUACAO: Record<string, 'success' | 'warning' | 'secondary' | 'destructive'> = {
  PAGO: 'success',
  EMITIDO: 'warning',
  UTILIZADO: 'secondary',
  CANCELADO: 'destructive',
  EXPIRADO: 'destructive',
}

export function ValeGasPage() {
  const { busca, setBusca, q, submit } = useBusca()
  const { data, isLoading } = useValeGas(q)
  const { data: situacoes } = useValeGasSituacoes()
  const baixar = useBaixarValeGas()
  const [open, setOpen] = useState(false); const [codigo, setCodigo] = useState(''); const [sit, setSit] = useState('')

  async function onBaixar() {
    if (!codigo || !sit) { toast.error('Informe o código e a situação.'); return }
    try { await baixar.mutateAsync({ codigo, situacao: sit }); toast.success('Situação atualizada.'); setOpen(false); setCodigo(''); setSit('') }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao baixar.') }
  }

  async function onImprimir(v: ValeGasRow) {
    try { await abrirValePdf(v.id) }
    catch (e: any) { toast.error(await mensagemDeErroBlob(e, 'Falha ao imprimir o vale.')) }
  }
  async function onDuplicata(v: ValeGasRow) {
    try { await abrirDuplicata(v.id) }
    catch (e: any) { toast.error(await mensagemDeErroBlob(e, 'Falha ao gerar a duplicata.')) }
  }

  const columns: Column<ValeGasRow>[] = [
    { key: 'codigo', header: 'Código', cell: (v) => <span className="font-medium tabular-nums">{v.codigo}</span> },
    { key: 'cliente', header: 'Cliente', cell: (v) => v.cliente?.nome ?? '—' },
    { key: 'valor', header: 'Valor', align: 'right', cell: (v) => <span className="tabular-nums">{Number(v.valor ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}</span> },
    { key: 'validade', header: 'Validade', cell: (v) => fmtData(v.validade) },
    {
      key: 'sit', header: 'Situação',
      cell: (v) => <Badge variant={SITUACAO[v.situacao] ?? 'secondary'}>{v.situacao ?? '—'}</Badge>,
    },
    {
      key: 'acoes', header: '', align: 'right', width: 'w-56',
      cell: (v) => {
        // Cancelado/expirado não vira papel: o cupom seria indistinguível de um
        // válido. Utilizado não reimprime — daria direito a uma segunda troca.
        const podeImprimir = v.situacao === 'EMITIDO' || v.situacao === 'PAGO'
        // A dívida sobrevive ao resgate: vale utilizado ainda tem o que cobrar.
        const podeDuplicata = !!v.financeiro_id && v.situacao !== 'CANCELADO' && v.situacao !== 'EXPIRADO'

        return (
          <div className="flex justify-end gap-1" onClick={(e) => e.stopPropagation()}>
            {podeImprimir && (
              <Button variant="outline" size="sm" onClick={() => onImprimir(v)}>
                <Printer size={14} /> Vale
              </Button>
            )}
            {podeDuplicata && (
              <Button variant="ghost" size="sm" onClick={() => onDuplicata(v)}>
                <Receipt size={14} /> Duplicata
              </Button>
            )}
          </div>
        )
      },
    },
  ]

  return (
    <div>
      <PageHeader title="Vale-Gás" subtitle="Gás de Bolso — consulta, impressão e baixa"
        action={
          <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild><Button><CheckCircle2 size={16} /> Baixar vale</Button></DialogTrigger>
            <DialogContent>
              <DialogHeader><DialogTitle>Mudar situação do vale</DialogTitle></DialogHeader>
              <div className="space-y-4">
                <Field label="Código" required><Input value={codigo} onChange={(e) => setCodigo(e.target.value)} /></Field>
                <Field label="Nova situação" required>
                  <Select value={sit} onValueChange={setSit}>
                    <SelectTrigger><SelectValue placeholder="Selecione" /></SelectTrigger>
                    <SelectContent>{(situacoes ?? []).map((s) => <SelectItem key={s} value={s}>{s}</SelectItem>)}</SelectContent>
                  </Select>
                </Field>
              </div>
              <DialogFooter><DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose><Button loading={baixar.isPending} onClick={onBaixar}>Confirmar</Button></DialogFooter>
            </DialogContent>
          </Dialog>
        } />
      <SearchBar value={busca} onChange={setBusca} onSearch={submit} placeholder="Buscar por código…" />
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(v) => v.id} empty={<EmptyState icon={<Flame />} title="Nenhum vale-gás" />} />
    </div>
  )
}
