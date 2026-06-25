import { useState } from 'react'
import { Plus, Users } from 'lucide-react'
import { Button, Field, Input, DataTable, type Column, EmptyState, toast } from '@/components/ui'
import { usePontos, useAddPonto } from '../api'
import { data as fmtData } from '@/lib/format'

export function PontoTab({ colaboradorId }: { colaboradorId: number }) {
  const { data, isLoading } = usePontos(colaboradorId)
  const add = useAddPonto(colaboradorId)
  const [f, setF] = useState<Record<string, any>>({ data: new Date().toISOString().slice(0, 10) })
  async function adicionar() {
    try { await add.mutateAsync(f); toast.success('Ponto registrado.') }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro.') }
  }
  const columns: Column<any>[] = [
    { key: 'data', header: 'Data', cell: (r) => fmtData(r.data) },
    { key: 'ent', header: 'Entrada', cell: (r) => r.entrada ?? '—' },
    { key: 'sai', header: 'Saída', cell: (r) => r.saida ?? '—' },
  ]
  return (
    <div className="space-y-4">
      <div className="grid grid-cols-1 md:grid-cols-4 gap-3 items-end rounded-lg border border-border p-4">
        <Field label="Data"><Input type="date" value={f.data} onChange={(e) => setF((x) => ({ ...x, data: e.target.value }))} /></Field>
        <Field label="Entrada"><Input type="time" value={f.entrada ?? ''} onChange={(e) => setF((x) => ({ ...x, entrada: e.target.value }))} /></Field>
        <Field label="Saída"><Input type="time" value={f.saida ?? ''} onChange={(e) => setF((x) => ({ ...x, saida: e.target.value }))} /></Field>
        <Button onClick={adicionar} loading={add.isPending}><Plus size={16} /> Registrar</Button>
      </div>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(r) => r.id} empty={<EmptyState icon={<Users />} title="Nenhum registro de ponto" />} />
    </div>
  )
}
