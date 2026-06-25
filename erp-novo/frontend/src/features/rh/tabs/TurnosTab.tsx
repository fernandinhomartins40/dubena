import { useState } from 'react'
import { Plus, Users } from 'lucide-react'
import { Button, Field, Input, DataTable, type Column, EmptyState, toast } from '@/components/ui'
import { useTurnos, useAddTurno } from '../api'

const DIAS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']

export function TurnosTab({ colaboradorId }: { colaboradorId: number }) {
  const { data, isLoading } = useTurnos(colaboradorId)
  const add = useAddTurno(colaboradorId)
  const [f, setF] = useState<Record<string, any>>({ dia_semana: 1, entrada: '08:00', saida: '17:00' })
  async function adicionar() {
    try { await add.mutateAsync(f); toast.success('Turno salvo.') }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro.') }
  }
  const columns: Column<any>[] = [
    { key: 'dia', header: 'Dia', cell: (r) => DIAS[r.dia_semana] ?? r.dia_semana },
    { key: 'ent', header: 'Entrada', cell: (r) => r.entrada },
    { key: 'sai', header: 'Saída', cell: (r) => r.saida },
  ]
  return (
    <div className="space-y-4">
      <div className="grid grid-cols-1 md:grid-cols-4 gap-3 items-end rounded-lg border border-border p-4">
        <Field label="Dia da semana">
          <select className="h-9 rounded-md border border-input bg-transparent px-3 text-sm" value={f.dia_semana} onChange={(e) => setF((x) => ({ ...x, dia_semana: Number(e.target.value) }))}>
            {DIAS.map((d, i) => <option key={i} value={i}>{d}</option>)}
          </select>
        </Field>
        <Field label="Entrada"><Input type="time" value={f.entrada} onChange={(e) => setF((x) => ({ ...x, entrada: e.target.value }))} /></Field>
        <Field label="Saída"><Input type="time" value={f.saida} onChange={(e) => setF((x) => ({ ...x, saida: e.target.value }))} /></Field>
        <Button onClick={adicionar} loading={add.isPending}><Plus size={16} /> Salvar turno</Button>
      </div>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(r) => r.id} empty={<EmptyState icon={<Users />} title="Nenhum turno" />} />
    </div>
  )
}
