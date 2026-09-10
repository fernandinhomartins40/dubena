import { ReportResult } from './ReportResult'
import { useState } from 'react'
import { FileBarChart, FileText, FileSpreadsheet, Search } from 'lucide-react'
import {
  Button, Card, CardContent, PageHeader, Input, Field, EmptyState, AsyncState,
  Select, SelectTrigger, SelectValue, SelectContent, SelectItem, toast,
} from '@/components/ui'
import { RELATORIOS, useRelatorio, baixarRelatorio, type RelatorioDef } from './api'

const localDate = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
const hoje = localDate(new Date())
const inicioMes = localDate(new Date(new Date().getFullYear(), new Date().getMonth(), 1))

export function RelatoriosPage() {
  const [sel, setSel] = useState<RelatorioDef>(RELATORIOS[0])
  const [inicio, setInicio] = useState(inicioMes)
  const [fim, setFim] = useState(hoje)
  const [mes, setMes] = useState(String(new Date().getMonth() + 1))
  // 60 dias: o giro tipico de um P13 domestico fica entre 30 e 45, entao quem
  // passou disso nao esta atrasado — esta comprando de outro.
  const [dias, setDias] = useState('60')
  const [submitted, setSubmitted] = useState<{ slug: string; title: string; params: Record<string, unknown> } | null>(null)
  const [baixando, setBaixando] = useState(false)

  const params: Record<string, unknown> = {}
  if (sel.periodo) { params.inicio = inicio; params.fim = fim }
  if (sel.mes) params.mes = Number(mes)
  if (sel.dias) params.dias = Number(dias)

  const { data, isLoading, isFetching, error, refetch } = useRelatorio(submitted?.slug ?? sel.slug, submitted?.params ?? params, !!submitted)
  const changed = !!submitted && (submitted.slug !== sel.slug || JSON.stringify(submitted.params) !== JSON.stringify(params))
  const invalid = (sel.periodo && (!inicio || !fim || inicio > fim)) || (sel.dias && (!Number.isInteger(Number(dias)) || Number(dias) < 1))
  function consultar() {
    if (invalid) return
    if (submitted && !changed) { void refetch(); return }
    setSubmitted({ slug: sel.slug, title: sel.titulo, params: { ...params } })
  }

  async function exportar(formato: 'csv' | 'pdf') {
    setBaixando(true)
    try { await baixarRelatorio(sel.slug, formato, params); toast.success(`Exportado (${formato.toUpperCase()}).`) }
    catch { toast.error('Erro ao exportar.') }
    finally { setBaixando(false) }
  }

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
            {sel.dias && (
              <Field label="Sem comprar há (dias)" hint="Acima deste corte o cliente entra na lista">
                <Input type="number" min={1} value={dias} onChange={(e) => setDias(e.target.value)} />
              </Field>
            )}
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
            <Button disabled={!!invalid} loading={isFetching} onClick={consultar}><Search size={16} /> Consultar</Button>
            <Button variant="outline" disabled={!!invalid || changed} loading={baixando} onClick={() => exportar('csv')}><FileSpreadsheet size={16} /> CSV</Button>
            <Button variant="outline" disabled={!!invalid || changed} loading={baixando} onClick={() => exportar('pdf')}><FileText size={16} /> PDF</Button>
          </div>
        </CardContent>
      </Card>

      {invalid && <p role="alert" className="mb-4 text-sm text-destructive">Confira o período e os parâmetros antes de consultar.</p>}
      {changed && <p role="status" className="mb-4 rounded-lg border bg-card p-3 text-sm">Filtros alterados. Clique em Consultar para atualizar o resultado e a exportação.</p>}
      {!submitted ? (
        <EmptyState icon={<FileBarChart />} title="Selecione e consulte" description="Escolha um relatório e clique em Consultar, ou exporte direto em CSV/PDF." />
      ) : (
        <section aria-label={submitted.title}>
          <h2 className="mb-3 text-lg font-semibold">{submitted.title}</h2>
          <p className="mb-4 text-sm text-muted-foreground">{Object.entries(submitted.params).map(([key, value]) => `${key === 'inicio' ? 'Início' : key === 'fim' ? 'Fim' : key === 'mes' ? 'Mês' : 'Dias'}: ${value}`).join(' · ') || 'Consulta sem filtro de período'}</p>
          <AsyncState loading={isLoading} error={error} onRetry={() => { void refetch() }}>
            <ReportResult value={data} title={submitted.title} />
          </AsyncState>
        </section>
      )}
    </div>
  )
}
