import { useState } from 'react'
import { Plus, Users } from 'lucide-react'
import { Button, Field, Input, Badge, DataTable, type Column, EmptyState, toast } from '@/components/ui'
import { useExames, useAddExame } from '../api'
import { data as fmtData } from '@/lib/format'

export function ExamesTab({ colaboradorId }: { colaboradorId: number }) {
  const { data, isLoading } = useExames(colaboradorId)
  const add = useAddExame(colaboradorId)
  const [f, setF] = useState<Record<string, any>>({ tipo: 'periodico', resultado: 'apto' })
  async function adicionar() {
    if (!f.realizado_em) { toast.error('Informe a data realizada.'); return }
    try { await add.mutateAsync(f); toast.success('Exame registrado.'); setF({ tipo: 'periodico', resultado: 'apto' }) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro.') }
  }
  const columns: Column<any>[] = [
    { key: 'tipo', header: 'Tipo', cell: (r) => r.tipo },
    { key: 'real', header: 'Realizado', cell: (r) => fmtData(r.realizado_em) },
    { key: 'venc', header: 'Vencimento', cell: (r) => fmtData(r.vencimento) },
    { key: 'res', header: 'Resultado', cell: (r) => <Badge variant={r.resultado === 'apto' ? 'success' : 'warning'}>{r.resultado}</Badge> },
  ]
  return (
    <div className="space-y-4">
      <div className="grid grid-cols-1 md:grid-cols-5 gap-3 items-end rounded-lg border border-border p-4">
        <Field label="Tipo">
          <select className="h-9 rounded-md border border-input bg-transparent px-3 text-sm" value={f.tipo} onChange={(e) => setF((x) => ({ ...x, tipo: e.target.value }))}>
            <option value="admissional">Admissional</option><option value="periodico">Periódico</option><option value="demissional">Demissional</option><option value="retorno">Retorno</option>
          </select>
        </Field>
        <Field label="Realizado em"><Input type="date" value={f.realizado_em ?? ''} onChange={(e) => setF((x) => ({ ...x, realizado_em: e.target.value }))} /></Field>
        <Field label="Vencimento"><Input type="date" value={f.vencimento ?? ''} onChange={(e) => setF((x) => ({ ...x, vencimento: e.target.value }))} /></Field>
        <Field label="Resultado">
          <select className="h-9 rounded-md border border-input bg-transparent px-3 text-sm" value={f.resultado} onChange={(e) => setF((x) => ({ ...x, resultado: e.target.value }))}>
            <option value="apto">Apto</option><option value="inapto">Inapto</option><option value="apto-com-restricao">Apto c/ restrição</option>
          </select>
        </Field>
        <Button onClick={adicionar} loading={add.isPending}><Plus size={16} /> Adicionar</Button>
      </div>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(r) => r.id} empty={<EmptyState icon={<Users />} title="Nenhum exame" />} />
    </div>
  )
}
