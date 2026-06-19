import { useEffect, useState } from 'react'
import { Trash2, Plus, Save } from 'lucide-react'
import { Button, Input } from '@/components/ui'
import { AsyncSelect } from '@/components/AsyncSelect'
import { useConvenio, useSalvarConvenio, useAddDependente, useDelDependente } from './api'

export function ConvenioTab({ clienteId }: { clienteId: number }) {
  const { data, isLoading } = useConvenio(clienteId)
  const salvar = useSalvarConvenio(clienteId)
  const addDep = useAddDependente(clienteId)
  const delDep = useDelDependente(clienteId)

  const [form, setForm] = useState<Record<string, any>>({})
  const [depNome, setDepNome] = useState('')
  const [parId, setParId] = useState<number | null>(null)
  const [parLabel, setParLabel] = useState<string | null>(null)

  useEffect(() => {
    if (data) {
      setForm({
        convenioativo: Number(data.convenioativo) === 1,
        datacontrato: data.convenio?.datacontrato ?? '',
        limitecompra: data.convenio?.limitecompra ?? '',
        comissao: data.convenio?.comissao ?? '',
        comissaodestino: data.convenio?.comissaodestino ?? '',
        diafechamento: data.convenio?.diafechamento ?? '',
        diavencimento: data.convenio?.diavencimento ?? '',
        nomerepresentante: data.convenio?.nomerepresentante ?? '',
        cpfrepresentante: data.convenio?.cpfrepresentante ?? '',
        rgrepresentante: data.convenio?.rgrepresentante ?? '',
      })
    }
  }, [data])

  const set = (k: string, v: any) => setForm((f) => ({ ...f, [k]: v }))

  if (isLoading) return <p className="col-span-full text-sm text-slate-400">Carregando…</p>

  return (
    <div className="col-span-full space-y-6">
      <div>
        <label className="flex items-center gap-2 text-sm font-medium mb-3">
          <input type="checkbox" checked={!!form.convenioativo} onChange={(e) => set('convenioativo', e.target.checked)} /> Habilitar Convênio (empresa conveniada)
        </label>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
          <Input label="Data Contrato" type="date" value={form.datacontrato ?? ''} onChange={(e) => set('datacontrato', e.target.value)} />
          <Input label="Limite Compra" value={form.limitecompra ?? ''} onChange={(e) => set('limitecompra', e.target.value)} />
          <Input label="Desconto (%)" value={form.comissao ?? ''} onChange={(e) => set('comissao', e.target.value)} />
          <div className="space-y-1">
            <label className="block text-sm font-medium">Desconto para</label>
            <select value={form.comissaodestino ?? ''} onChange={(e) => set('comissaodestino', e.target.value)} className="w-full rounded-md border border-slate-300 dark:border-slate-700 bg-transparent px-3 py-2">
              <option value="">Selecione</option><option value="1">Conveniado</option><option value="2">Empresa</option>
            </select>
          </div>
          <Input label="Dia Fechamento" type="number" value={form.diafechamento ?? ''} onChange={(e) => set('diafechamento', e.target.value)} />
          <Input label="Dia Vencimento" type="number" value={form.diavencimento ?? ''} onChange={(e) => set('diavencimento', e.target.value)} />
        </div>
        <p className="text-sm font-semibold text-slate-500 mt-4 mb-2">Representante Legal</p>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
          <Input label="Nome" value={form.nomerepresentante ?? ''} onChange={(e) => set('nomerepresentante', e.target.value)} />
          <Input label="CPF" value={form.cpfrepresentante ?? ''} onChange={(e) => set('cpfrepresentante', e.target.value)} />
          <Input label="RG" value={form.rgrepresentante ?? ''} onChange={(e) => set('rgrepresentante', e.target.value)} />
        </div>
        <Button className="mt-3" onClick={() => salvar.mutate(form)} disabled={salvar.isPending}>
          <Save size={16} /> {salvar.isPending ? 'Salvando…' : 'Salvar convênio'}
        </Button>
      </div>

      <div className="border-t border-slate-100 dark:border-slate-800 pt-4">
        <p className="text-sm font-semibold text-slate-500 mb-2">Dependentes / Parentescos</p>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
          <Input label="Nome" value={depNome} onChange={(e) => setDepNome(e.target.value)} />
          <AsyncSelect label="Parentesco" endpoint="/lookups/parentescos" value={parId} valueLabel={parLabel} onChange={(id, o) => { setParId(id); setParLabel(o?.label ?? null) }} />
          <Button type="button" onClick={async () => { if (depNome && parId) { await addDep.mutateAsync({ nome: depNome, parentesco_id: parId, ativo: true }); setDepNome(''); setParId(null); setParLabel(null) } }}>
            <Plus size={16} /> Adicionar
          </Button>
        </div>
        <div className="mt-3 border border-slate-200 dark:border-slate-800 rounded-lg divide-y divide-slate-100 dark:divide-slate-800">
          {data?.dependentes && data.dependentes.length > 0 ? (
            data.dependentes.map((d: any) => (
              <div key={d.id} className="flex items-center justify-between px-4 py-2">
                <span className="text-sm">{d.nome} <span className="text-slate-400">· {d.parentesco}</span></span>
                <button onClick={() => delDep.mutate(d.id)} className="text-slate-400 hover:text-red-600"><Trash2 size={16} /></button>
              </div>
            ))
          ) : <p className="px-4 py-3 text-sm text-slate-400">Nenhum dependente.</p>}
        </div>
      </div>
    </div>
  )
}
