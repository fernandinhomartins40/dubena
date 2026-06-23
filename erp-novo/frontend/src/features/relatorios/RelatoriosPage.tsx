import { useState } from 'react'
import { FileBarChart, FileText, FileSpreadsheet, Search } from 'lucide-react'
import {
  Button, Card, CardContent, PageHeader, Input, Field, EmptyState, Skeleton,
  Select, SelectTrigger, SelectValue, SelectContent, SelectItem, toast,
} from '@/components/ui'
import { RELATORIOS, useRelatorio, baixarRelatorio, type RelatorioDef } from './api'

const hoje = new Date().toISOString().slice(0, 10)
const inicioMes = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10)

export function RelatoriosPage() {
  const [sel, setSel] = useState<RelatorioDef>(RELATORIOS[0])
  const [inicio, setInicio] = useState(inicioMes)
  const [fim, setFim] = useState(hoje)
  const [mes, setMes] = useState(String(new Date().getMonth() + 1))
  const [run, setRun] = useState(0)
  const [baixando, setBaixando] = useState(false)

  const params: Record<string, unknown> = {}
  if (sel.periodo) { params.inicio = inicio; params.fim = fim }
  if (sel.mes) params.mes = Number(mes)

  const { data, isLoading, isFetching } = useRelatorio(sel.slug, params, run > 0)

  async function exportar(formato: 'csv' | 'pdf') {
    setBaixando(true)
    try { await baixarRelatorio(sel.slug, formato, params); toast.success(`Exportado (${formato.toUpperCase()}).`) }
    catch { toast.error('Erro ao exportar.') }
    finally { setBaixando(false) }
  }

  // O relatório pode vir como array (lista) ou objeto (resumo/dre). Normaliza p/ preview.
  const linhas = Array.isArray(data) ? (data as Record<string, unknown>[]) : null
  const colunas = linhas && linhas.length ? Object.keys(linhas[0]) : []

  return (
    <div>
      <PageHeader title="Relatórios" subtitle="Consulta e exportação (CSV / PDF)" />

      <Card className="mb-4">
        <CardContent className="p-4 space-y-4">
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 items-end">
            <Field label="Relatório">
              <Select value={sel.slug} onValueChange={(v) => setSel(RELATORIOS.find((r) => r.slug === v)!)}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>{RELATORIOS.map((r) => <SelectItem key={r.slug} value={r.slug}>{r.titulo}</SelectItem>)}</SelectContent>
              </Select>
            </Field>
            {sel.periodo && <>
              <Field label="Início"><Input type="date" value={inicio} onChange={(e) => setInicio(e.target.value)} /></Field>
              <Field label="Fim"><Input type="date" value={fim} onChange={(e) => setFim(e.target.value)} /></Field>
            </>}
            {sel.mes && (
              <Field label="Mês">
                <Select value={mes} onValueChange={setMes}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>{Array.from({ length: 12 }, (_, i) => <SelectItem key={i + 1} value={String(i + 1)}>{i + 1}</SelectItem>)}</SelectContent>
                </Select>
              </Field>
            )}
          </div>
          <div className="flex flex-wrap gap-2">
            <Button onClick={() => setRun((n) => n + 1)}><Search size={16} /> Consultar</Button>
            <Button variant="outline" loading={baixando} onClick={() => exportar('csv')}><FileSpreadsheet size={16} /> CSV</Button>
            <Button variant="outline" loading={baixando} onClick={() => exportar('pdf')}><FileText size={16} /> PDF</Button>
          </div>
        </CardContent>
      </Card>

      {run === 0 ? (
        <EmptyState icon={<FileBarChart />} title="Selecione e consulte" description="Escolha um relatório e clique em Consultar, ou exporte direto em CSV/PDF." />
      ) : isLoading || isFetching ? (
        <div className="space-y-2">{Array.from({ length: 5 }).map((_, i) => <Skeleton key={i} className="h-9 w-full" />)}</div>
      ) : linhas ? (
        linhas.length === 0
          ? <EmptyState icon={<FileBarChart />} title="Sem dados no período" />
          : (
            <Card><CardContent className="p-0 overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-muted/50 text-left">
                  <tr>{colunas.map((c) => <th key={c} className="px-3 py-2 font-medium uppercase text-xs text-muted-foreground">{c}</th>)}</tr>
                </thead>
                <tbody>
                  {linhas.map((l, i) => (
                    <tr key={i} className="border-t border-border">
                      {colunas.map((c) => <td key={c} className="px-3 py-2 tabular-nums">{String(l[c] ?? '—')}</td>)}
                    </tr>
                  ))}
                </tbody>
              </table>
            </CardContent></Card>
          )
      ) : (
        // resumo/objeto (ex.: financeiro, dre) → renderiza chave/valor
        <Card><CardContent className="p-4">
          <pre className="text-sm overflow-x-auto whitespace-pre-wrap">{JSON.stringify(data, null, 2)}</pre>
        </CardContent></Card>
      )}
    </div>
  )
}
