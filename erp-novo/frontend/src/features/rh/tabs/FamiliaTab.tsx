import { useState } from 'react'
import { Plus, Trash2, Users } from 'lucide-react'
import { Button, Field, Input, AsyncSelect, AsyncState, toast } from '@/components/ui'
import { useFamilia, useAddFamilia, useDelFamilia } from '../api'

export function FamiliaTab({ colaboradorId }: { colaboradorId: number }) {
  const { data, isLoading } = useFamilia(colaboradorId)
  const add = useAddFamilia(colaboradorId); const del = useDelFamilia(colaboradorId)
  const [nome, setNome] = useState(''); const [parId, setParId] = useState<number | null>(null); const [parLabel, setParLabel] = useState<string | null>(null); const [nasc, setNasc] = useState('')
  async function adicionar() {
    if (!nome || !parId) { toast.error('Informe nome e parentesco.'); return }
    await add.mutateAsync({ nome, parentesco_id: parId, datanascimento: nasc || null }); toast.success('Familiar adicionado.'); setNome(''); setParId(null); setParLabel(null); setNasc('')
  }
  return (
    <div className="space-y-4">
      <div className="grid grid-cols-1 md:grid-cols-4 gap-3 items-end rounded-lg border border-border p-4">
        <Field label="Nome"><Input value={nome} onChange={(e) => setNome(e.target.value)} /></Field>
        <Field label="Parentesco"><AsyncSelect endpoint="/lookups/parentescos" value={parId} valueLabel={parLabel} onChange={(id, o) => { setParId(id); setParLabel(o?.label ?? null) }} /></Field>
        <Field label="Nascimento"><Input type="date" value={nasc} onChange={(e) => setNasc(e.target.value)} /></Field>
        <Button onClick={adicionar} loading={add.isPending}><Plus size={16} /> Adicionar</Button>
      </div>
      <AsyncState loading={isLoading} empty={!data?.length} emptyIcon={<Users />} emptyTitle="Nenhum familiar">
        <div className="rounded-lg border border-border divide-y divide-border">
          {data?.map((f) => (<div key={f.id} className="flex items-center justify-between px-4 py-2.5"><span className="text-sm">{f.nome} <span className="text-muted-foreground">· {f.parentesco}</span></span><Button variant="ghost" size="icon" onClick={() => { del.mutate(f.id); toast.success('Removido.') }}><Trash2 size={16} /></Button></div>))}
        </div>
      </AsyncState>
    </div>
  )
}
