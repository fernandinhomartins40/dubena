import { useState } from 'react'
import { Trash2, Plus } from 'lucide-react'
import { Button, Input } from '@/components/ui'
import { AsyncSelect } from '@/components/AsyncSelect'
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
  const [erro, setErro] = useState<string | null>(null)

  async function adicionar() {
    setErro(null)
    if (!tipoId || !sitId || !descricao) { setErro('Tipo, situação e descrição são obrigatórios.'); return }
    await add.mutateAsync({ tipo_id: tipoId, situacao_id: sitId, descricao, acao })
    setDescricao(''); setAcao(''); setTipoId(null); setTipoLabel(null); setSitId(null); setSitLabel(null)
  }

  return (
    <div className="col-span-full space-y-4">
      <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
        <AsyncSelect label="Tipo" endpoint="/lookups/contato-tipos" value={tipoId} valueLabel={tipoLabel} onChange={(id, o) => { setTipoId(id); setTipoLabel(o?.label ?? null) }} />
        <AsyncSelect label="Situação" endpoint="/lookups/contato-situacoes" value={sitId} valueLabel={sitLabel} onChange={(id, o) => { setSitId(id); setSitLabel(o?.label ?? null) }} />
        <Input label="Descrição" value={descricao} onChange={(e) => setDescricao(e.target.value)} className="md:col-span-2" />
        <Input label="Ação" value={acao} onChange={(e) => setAcao(e.target.value)} className="md:col-span-2" />
      </div>
      {erro && <p className="text-sm text-red-600">{erro}</p>}
      <Button type="button" onClick={adicionar} disabled={add.isPending}><Plus size={16} /> Adicionar interação</Button>

      <div className="border border-slate-200 dark:border-slate-800 rounded-lg divide-y divide-slate-100 dark:divide-slate-800">
        {isLoading ? (
          <p className="px-4 py-3 text-sm text-slate-400">Carregando…</p>
        ) : data && data.length > 0 ? (
          data.map((i: any) => (
            <div key={i.id} className="flex items-start justify-between px-4 py-2.5">
              <div>
                <div className="text-xs text-slate-400">{i.datahora ? new Date(i.datahora).toLocaleString('pt-BR') : ''} · {i.tipo} · {i.situacao}</div>
                <div className="text-sm">{i.descricao}</div>
                {i.acao && <div className="text-xs text-slate-500">Ação: {i.acao}</div>}
              </div>
              <button onClick={() => del.mutate(i.id)} className="text-slate-400 hover:text-red-600" title="Remover"><Trash2 size={16} /></button>
            </div>
          ))
        ) : (
          <p className="px-4 py-3 text-sm text-slate-400">Nenhuma interação.</p>
        )}
      </div>
    </div>
  )
}
