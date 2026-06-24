import { useState } from 'react'
import { Trash2, Plus, MessagesSquare } from 'lucide-react'
import { Button, Field, Input, Textarea, AsyncSelect, EmptyState, toast } from '@/components/ui'
import { dataHora } from '@/lib/format'
import { useInteracoes, useAddInteracao, useDelInteracao } from './api'

export function InteracoesTab({ clienteId }: { clienteId: number }) {
  const { data, isLoading } = useInteracoes(clienteId)
  const add = useAddInteracao(clienteId)
  const del = useDelInteracao(clienteId)

  const [tipoId, setTipoId] = useState<number | null>(null)
  const [tipoLabel, setTipoLabel] = useState<string | null>(null)
  const [sitId, setSitId] = useState<number | null>(null)
  const [sitLabel, setSitLabel] = useState<string | null>(null)
  const [descricao, setDescricao] = useState('')
  const [acao, setAcao] = useState('')

  async function adicionar() {
    if (!tipoId || !sitId || !descricao) { toast.error('Tipo, situação e descrição são obrigatórios.'); return }
    await add.mutateAsync({ tipo_id: tipoId, situacao_id: sitId, descricao, acao })
    setDescricao(''); setAcao(''); setTipoId(null); setTipoLabel(null); setSitId(null); setSitLabel(null)
    toast.success('Interação registrada.')
  }

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-1 md:grid-cols-2 gap-3 rounded-lg border border-border p-4">
        <Field label="Tipo">
          <AsyncSelect endpoint="/lookups/contato-tipos" value={tipoId} valueLabel={tipoLabel} onChange={(id, o) => { setTipoId(id); setTipoLabel(o?.label ?? null) }} />
        </Field>
        <Field label="Situação">
          <AsyncSelect endpoint="/lookups/contato-situacoes" value={sitId} valueLabel={sitLabel} onChange={(id, o) => { setSitId(id); setSitLabel(o?.label ?? null) }} />
        </Field>
        <Field label="Descrição" className="md:col-span-2"><Textarea value={descricao} onChange={(e) => setDescricao(e.target.value)} /></Field>
        <Field label="Ação" className="md:col-span-2"><Input value={acao} onChange={(e) => setAcao(e.target.value)} /></Field>
        <div className="md:col-span-2"><Button onClick={adicionar} loading={add.isPending}><Plus size={16} /> Adicionar interação</Button></div>
      </div>

      {isLoading ? (
        <p className="text-sm text-muted-foreground">Carregando…</p>
      ) : data && data.length > 0 ? (
        <div className="rounded-lg border border-border divide-y divide-border">
          {data.map((i: any) => (
            <div key={i.id} className="flex items-start justify-between px-4 py-3">
              <div>
                <div className="text-xs text-muted-foreground">{dataHora(i.datahora)} · {i.tipo} · {i.situacao}</div>
                <div className="text-sm">{i.descricao}</div>
                {i.acao && <div className="text-xs text-muted-foreground">Ação: {i.acao}</div>}
              </div>
              <Button variant="ghost" size="icon" onClick={() => { del.mutate(i.id); toast.success('Interação removida.') }} aria-label="Remover"><Trash2 size={16} /></Button>
            </div>
          ))}
        </div>
      ) : (
        <EmptyState icon={<MessagesSquare />} title="Nenhuma interação" description="Registre o histórico de contato com o cliente." />
      )}
    </div>
  )
}
