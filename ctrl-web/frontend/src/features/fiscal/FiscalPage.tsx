import { useState } from 'react'
import { Search, Plus, Pencil, Trash2, FileText, Send, Ban, FileSpreadsheet, AlertCircle } from 'lucide-react'
import {
  Button, Card, CardContent, PageHeader, Input, Badge, DataTable, type Column, EmptyState, Field, CheckboxField,
  Tabs, TabsList, TabsTrigger, TabsContent,
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogClose, toast,
} from '@/components/ui'
import {
  useMalha, useSalvarMalha, useExcluirMalha, type MalhaRow,
  useOperacoes, useSalvarOperacao, useExcluirOperacao, type OperacaoRow,
  useNfe, useTransmitirNfe, useCancelarNfe, type NfeRow,
  useSpedPreview,
} from './api'

const fmtData = (s: string | null) => (s ? new Date(s).toLocaleString('pt-BR') : '—')
const SITUACAO_NFE: Record<number, { l: string; v: 'success' | 'warning' | 'destructive' | 'secondary' }> = {
  100: { l: 'Autorizada', v: 'success' },
  101: { l: 'Cancelada', v: 'destructive' },
  3: { l: 'Autorizada', v: 'success' },
}

export function FiscalPage() {
  return (
    <div>
      <PageHeader title="Fiscal" subtitle="Malha fiscal, NF-e/NFC-e e SPED" />
      <Tabs defaultValue="malha">
        <TabsList className="overflow-x-auto">
          <TabsTrigger value="malha">Malha Fiscal</TabsTrigger>
          <TabsTrigger value="nfe">NF-e</TabsTrigger>
          <TabsTrigger value="sped">SPED</TabsTrigger>
        </TabsList>
        <TabsContent value="malha"><MalhaTab /></TabsContent>
        <TabsContent value="nfe"><NfeTab /></TabsContent>
        <TabsContent value="sped"><SpedTab /></TabsContent>
      </Tabs>
    </div>
  )
}

// ---------- MALHA FISCAL ----------
function MalhaTab() {
  return (
    <Tabs defaultValue="grupos-fiscais">
      <TabsList className="overflow-x-auto">
        <TabsTrigger value="grupos-fiscais">Grupos</TabsTrigger>
        <TabsTrigger value="operacoes">Operações</TabsTrigger>
        <TabsTrigger value="cst-icms">CST ICMS</TabsTrigger>
        <TabsTrigger value="cst-ipi">CST IPI</TabsTrigger>
        <TabsTrigger value="cst-pis">CST PIS</TabsTrigger>
        <TabsTrigger value="cst-cofins">CST COFINS</TabsTrigger>
        <TabsTrigger value="cst">CST</TabsTrigger>
      </TabsList>
      <TabsContent value="grupos-fiscais"><MalhaCadastro tipo="grupos-fiscais" titulo="Grupo Fiscal" comCodigo={false} /></TabsContent>
      <TabsContent value="operacoes"><OperacoesTab /></TabsContent>
      <TabsContent value="cst-icms"><MalhaCadastro tipo="cst-icms" titulo="CST ICMS" /></TabsContent>
      <TabsContent value="cst-ipi"><MalhaCadastro tipo="cst-ipi" titulo="CST IPI" /></TabsContent>
      <TabsContent value="cst-pis"><MalhaCadastro tipo="cst-pis" titulo="CST PIS" /></TabsContent>
      <TabsContent value="cst-cofins"><MalhaCadastro tipo="cst-cofins" titulo="CST COFINS" /></TabsContent>
      <TabsContent value="cst"><MalhaCadastro tipo="cst" titulo="CST" /></TabsContent>
    </Tabs>
  )
}

function MalhaCadastro({ tipo, titulo, comCodigo = true }: { tipo: string; titulo: string; comCodigo?: boolean }) {
  const { data, isLoading } = useMalha(tipo)
  const salvar = useSalvarMalha(tipo); const excluir = useExcluirMalha(tipo)
  const [edit, setEdit] = useState<Partial<MalhaRow> | null>(null); const [del, setDel] = useState<MalhaRow | null>(null)

  async function onSalvar() {
    if (!edit?.descricao?.trim()) { toast.error('Informe a descrição.'); return }
    try { await salvar.mutateAsync({ id: edit.id, descricao: edit.descricao, codigo: edit.codigo }); toast.success(`${titulo} salvo.`); setEdit(null) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro.') }
  }
  const columns: Column<MalhaRow>[] = [
    ...(comCodigo ? [{ key: 'codigo', header: 'Código', width: 'w-24', cell: (r: MalhaRow) => <span className="tabular-nums text-muted-foreground">{r.codigo || '—'}</span> }] : []),
    { key: 'descricao', header: 'Descrição', cell: (r) => <span className="font-medium">{r.descricao}</span> },
    { key: 'acoes', header: '', align: 'right', width: 'w-24', cell: (r) => <div className="flex justify-end gap-1" onClick={(e) => e.stopPropagation()}><Button variant="ghost" size="icon" onClick={() => setEdit(r)}><Pencil size={16} /></Button><Button variant="ghost" size="icon" onClick={() => setDel(r)}><Trash2 size={16} /></Button></div> },
  ]
  return (
    <>
      <div className="mb-3 flex justify-end"><Button onClick={() => setEdit({})}><Plus size={16} /> Novo</Button></div>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(r) => r.id} onRowClick={(r) => setEdit(r)} empty={<EmptyState icon={<FileText />} title={`Nenhum registro em ${titulo}`} />} />
      <Dialog open={!!edit} onOpenChange={(o) => !o && setEdit(null)}>
        <DialogContent>
          <DialogHeader><DialogTitle>{edit?.id ? `Editar ${titulo}` : `Novo ${titulo}`}</DialogTitle></DialogHeader>
          <div className="space-y-4">
            {comCodigo && <Field label="Código"><Input value={edit?.codigo ?? ''} onChange={(e) => setEdit((s) => ({ ...s, codigo: e.target.value }))} /></Field>}
            <Field label="Descrição" required><Input autoFocus value={edit?.descricao ?? ''} onChange={(e) => setEdit((s) => ({ ...s, descricao: e.target.value }))} /></Field>
          </div>
          <DialogFooter><DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose><Button loading={salvar.isPending} onClick={onSalvar}>Salvar</Button></DialogFooter>
        </DialogContent>
      </Dialog>
      <Dialog open={!!del} onOpenChange={(o) => !o && setDel(null)}>
        <DialogContent>
          <DialogHeader><DialogTitle>Excluir</DialogTitle></DialogHeader>
          <p className="text-sm text-muted-foreground">Excluir <strong>{del?.descricao}</strong>?</p>
          <DialogFooter><DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose><Button variant="destructive" loading={excluir.isPending} onClick={async () => { try { await excluir.mutateAsync(del!.id); toast.success('Excluído.') } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro.') } finally { setDel(null) } }}><Trash2 size={16} /> Excluir</Button></DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}

function OperacoesTab() {
  const { data, isLoading } = useOperacoes()
  const salvar = useSalvarOperacao(); const excluir = useExcluirOperacao()
  const [edit, setEdit] = useState<Partial<OperacaoRow> | null>(null); const [del, setDel] = useState<OperacaoRow | null>(null)

  async function onSalvar() {
    if (!edit?.descricao?.trim()) { toast.error('Informe a descrição.'); return }
    try { await salvar.mutateAsync({ id: edit.id, descricao: edit.descricao, descricaofiscal: edit.descricaofiscal, cfop: edit.cfop, movimentaestoque: edit.movimentaestoque === 1, movimentafinanceiro: edit.movimentafinanceiro === 1 }); toast.success('Operação salva.'); setEdit(null) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro.') }
  }
  const columns: Column<OperacaoRow>[] = [
    { key: 'descricao', header: 'Descrição', cell: (o) => <span className="font-medium">{o.descricao}</span> },
    { key: 'cfop', header: 'CFOP', width: 'w-24', cell: (o) => <span className="tabular-nums text-muted-foreground">{o.cfop || '—'}</span> },
    { key: 'mov', header: 'Movimenta', cell: (o) => <div className="flex gap-1">{Number(o.movimentaestoque) ? <Badge variant="secondary">Estoque</Badge> : null}{Number(o.movimentafinanceiro) ? <Badge variant="secondary">Financeiro</Badge> : null}</div> },
    { key: 'acoes', header: '', align: 'right', width: 'w-24', cell: (o) => <div className="flex justify-end gap-1" onClick={(e) => e.stopPropagation()}><Button variant="ghost" size="icon" onClick={() => setEdit(o)}><Pencil size={16} /></Button><Button variant="ghost" size="icon" onClick={() => setDel(o)}><Trash2 size={16} /></Button></div> },
  ]
  return (
    <>
      <div className="mb-3 flex justify-end"><Button onClick={() => setEdit({})}><Plus size={16} /> Nova operação</Button></div>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(o) => o.id} onRowClick={(o) => setEdit(o)} empty={<EmptyState icon={<FileText />} title="Nenhuma operação fiscal" />} />
      <Dialog open={!!edit} onOpenChange={(o) => !o && setEdit(null)}>
        <DialogContent>
          <DialogHeader><DialogTitle>{edit?.id ? 'Editar operação' : 'Nova operação'}</DialogTitle></DialogHeader>
          <div className="space-y-4">
            <Field label="Descrição" required><Input autoFocus value={edit?.descricao ?? ''} onChange={(e) => setEdit((s) => ({ ...s, descricao: e.target.value }))} /></Field>
            <Field label="Descrição fiscal"><Input value={edit?.descricaofiscal ?? ''} onChange={(e) => setEdit((s) => ({ ...s, descricaofiscal: e.target.value }))} /></Field>
            <Field label="CFOP"><Input value={edit?.cfop ?? ''} onChange={(e) => setEdit((s) => ({ ...s, cfop: e.target.value }))} /></Field>
            <div className="flex gap-6">
              <CheckboxField label="Movimenta estoque" checked={edit?.movimentaestoque === 1} onChange={(b) => setEdit((s) => ({ ...s, movimentaestoque: b ? 1 : 0 }))} />
              <CheckboxField label="Movimenta financeiro" checked={edit?.movimentafinanceiro === 1} onChange={(b) => setEdit((s) => ({ ...s, movimentafinanceiro: b ? 1 : 0 }))} />
            </div>
          </div>
          <DialogFooter><DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose><Button loading={salvar.isPending} onClick={onSalvar}>Salvar</Button></DialogFooter>
        </DialogContent>
      </Dialog>
      <Dialog open={!!del} onOpenChange={(o) => !o && setDel(null)}>
        <DialogContent>
          <DialogHeader><DialogTitle>Excluir operação</DialogTitle></DialogHeader>
          <p className="text-sm text-muted-foreground">Excluir <strong>{del?.descricao}</strong>?</p>
          <DialogFooter><DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose><Button variant="destructive" loading={excluir.isPending} onClick={async () => { try { await excluir.mutateAsync(del!.id); toast.success('Excluída.') } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro.') } finally { setDel(null) } }}><Trash2 size={16} /> Excluir</Button></DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}

// ---------- NF-e ----------
function NfeTab() {
  const [busca, setBusca] = useState(''); const [q, setQ] = useState('')
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
        <span>A transmissão e o cancelamento dependem de certificado digital e do ambiente SEFAZ configurado. Valide em <strong>homologação</strong> antes de usar em produção.</span>
      </div>
      <Card className="mb-4 p-3"><div className="flex gap-2">
        <div className="relative flex-1"><Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" /><Input value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Buscar número ou chave de acesso…" className="pl-9" onKeyDown={(e) => e.key === 'Enter' && setQ(busca)} /></div>
        <Button variant="secondary" onClick={() => setQ(busca)}>Buscar</Button>
      </div></Card>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(n) => n.id} empty={<EmptyState icon={<FileText />} title="Nenhuma NF-e" description="Notas são geradas a partir de pedidos." />} />
      <Dialog open={!!cancelando} onOpenChange={(o) => !o && setCancelando(null)}>
        <DialogContent>
          <DialogHeader><DialogTitle>Cancelar NF-e {cancelando?.nfserie}/{cancelando?.nfnumero}</DialogTitle></DialogHeader>
          <Field label="Justificativa (mín. 15 caracteres)" required><Input value={justif} onChange={(e) => setJustif(e.target.value)} /></Field>
          <DialogFooter><DialogClose asChild><Button variant="outline">Voltar</Button></DialogClose><Button variant="destructive" loading={cancelar.isPending} onClick={onCancelar}><Ban size={16} /> Cancelar NF-e</Button></DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}

// ---------- SPED ----------
function SpedTab() {
  const hoje = new Date()
  const [inicio, setInicio] = useState(new Date(hoje.getFullYear(), hoje.getMonth(), 1).toISOString().slice(0, 10))
  const [fim, setFim] = useState(hoje.toISOString().slice(0, 10))
  const [run, setRun] = useState(false)
  const { data, isLoading } = useSpedPreview(inicio, fim, run)

  return (
    <>
      <Card className="mb-4"><CardContent className="pt-6 flex flex-wrap items-end gap-3">
        <Field label="Início"><Input type="date" value={inicio} onChange={(e) => setInicio(e.target.value)} /></Field>
        <Field label="Fim"><Input type="date" value={fim} onChange={(e) => setFim(e.target.value)} /></Field>
        <Button onClick={() => setRun(true)}><FileSpreadsheet size={16} /> Pré-visualizar SPED</Button>
      </CardContent></Card>
      {!run ? <EmptyState icon={<FileSpreadsheet />} title="Informe o período para pré-visualizar o SPED" /> : isLoading ? <p className="text-sm text-muted-foreground">Calculando…</p> : data && (
        <div className="grid gap-4 md:grid-cols-2">
          <Card><CardContent className="pt-6"><p className="text-sm text-muted-foreground">Notas emitidas no período</p><p className="mt-1 text-3xl font-bold tabular-nums">{data.notas_emitidas}</p></CardContent></Card>
          <Card><CardContent className="pt-6"><p className="text-sm text-muted-foreground">Notas recebidas no período</p><p className="mt-1 text-3xl font-bold tabular-nums">{data.notas_recebidas}</p></CardContent></Card>
          <p className="md:col-span-2 text-xs text-muted-foreground">A geração do arquivo TXT do SPED (validável no PVA da Receita) é executada pelo motor fiscal e depende dos dados completos do período.</p>
        </div>
      )}
    </>
  )
}
