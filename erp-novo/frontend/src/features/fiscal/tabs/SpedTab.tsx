import { useState } from 'react'
import { FileSpreadsheet } from 'lucide-react'
import { Button, Card, CardContent, EmptyState, Field, Input, AsyncState } from '@/components/ui'
import { useSpedPreview } from '../api'

export function SpedTab() {
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
      {!run ? <EmptyState icon={<FileSpreadsheet />} title="Informe o período para pré-visualizar o SPED" /> : (
        <AsyncState loading={isLoading} skeletonRows={2}>
          {data && (
            <div className="grid gap-4 md:grid-cols-2">
              <Card><CardContent className="pt-6"><p className="text-sm text-muted-foreground">Notas emitidas no período</p><p className="mt-1 text-3xl font-bold tabular-nums">{data.notas_emitidas}</p></CardContent></Card>
              <Card><CardContent className="pt-6"><p className="text-sm text-muted-foreground">Notas recebidas no período</p><p className="mt-1 text-3xl font-bold tabular-nums">{data.notas_recebidas}</p></CardContent></Card>
              <p className="md:col-span-2 text-xs text-muted-foreground">A geração do arquivo TXT do SPED (validável no PVA da Receita) é executada pelo motor fiscal e depende dos dados completos do período.</p>
            </div>
          )}
        </AsyncState>
      )}
    </>
  )
}
