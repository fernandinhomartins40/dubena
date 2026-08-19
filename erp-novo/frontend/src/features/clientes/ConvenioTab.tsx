import { useEffect, useState } from 'react'
import { Trash2, Plus, Save, Users } from 'lucide-react'
import {
  Button, Field, Input, Switch, AsyncSelect, EmptyState, AsyncState, toast,
} from '@/components/ui'
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
      // O endpoint devolve os campos no topo de `data` (não em `data.convenio`),
      // e só estes três existem: os demais que a tela editava
      // (limite de compra, comissão, dia de fechamento/vencimento, dados do
      // representante) não têm coluna no banco NEM no legado — o representante
      // pertence a `comodatos`, não a convênio. Eram campos que o usuário
      // preenchia e o backend descartava em silêncio.
      setForm({
        convenio: !!data.convenio,
        convenio_ativo: !!data.convenio_ativo,
        convenio_limite: data.convenio_limite ?? '',
      })
    }
  }, [data])

  const set = (k: string, v: any) => setForm((f) => ({ ...f, [k]: v }))

  async function onSalvar() {
    try {
      await salvar.mutateAsync({
        convenio: !!form.convenio_ativo || !!form.convenio,
        convenio_ativo: !!form.convenio_ativo,
        convenio_limite: form.convenio_limite === '' ? null : Number(form.convenio_limite),
      })
      toast.success('Convênio salvo.')
    }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao salvar convênio.') }
  }

  if (isLoading) return <AsyncState loading skeletonRows={4}>{null}</AsyncState>

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3 rounded-lg border border-border p-4">
        <Switch checked={!!form.convenio_ativo} onCheckedChange={(b) => set('convenio_ativo', b)} />
        <div>
          <p className="text-sm font-medium">Empresa conveniada</p>
          <p className="text-xs text-muted-foreground">Habilita compra por convênio com fechamento mensal.</p>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <Field label="Limite do convênio">
          <Input type="number" step="0.01" value={form.convenio_limite ?? ''}
            onChange={(e) => set('convenio_limite', e.target.value)} />
        </Field>
      </div>

      <div className="space-y-3">
        <Button onClick={onSalvar} loading={salvar.isPending}><Save size={16} /> Salvar convênio</Button>
      </div>

      <div className="border-t border-border pt-4 space-y-3">
        <p className="text-sm font-semibold text-muted-foreground">Dependentes / parentescos</p>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-3 items-end rounded-lg border border-border p-4">
          <Field label="Nome"><Input value={depNome} onChange={(e) => setDepNome(e.target.value)} /></Field>
          <Field label="Parentesco">
            <AsyncSelect endpoint="/lookups/parentescos" value={parId} valueLabel={parLabel} onChange={(id, o) => { setParId(id); setParLabel(o?.label ?? null) }} />
          </Field>
          <Button onClick={async () => {
            if (depNome && parId) { await addDep.mutateAsync({ nome: depNome, parentesco_id: parId, ativo: true }); setDepNome(''); setParId(null); setParLabel(null); toast.success('Dependente adicionado.') }
            else toast.error('Informe nome e parentesco.')
          }}><Plus size={16} /> Adicionar</Button>
        </div>
        {data?.dependentes && data.dependentes.length > 0 ? (
          <div className="rounded-lg border border-border divide-y divide-border">
            {data.dependentes.map((d: any) => (
              <div key={d.id} className="flex items-center justify-between px-4 py-2.5">
                <span className="text-sm">{d.nome} <span className="text-muted-foreground">· {d.parentesco}</span></span>
                <Button variant="ghost" size="icon" onClick={() => { delDep.mutate(d.id); toast.success('Dependente removido.') }}><Trash2 size={16} /></Button>
              </div>
            ))}
          </div>
        ) : <EmptyState icon={<Users />} title="Nenhum dependente" />}
      </div>
    </div>
  )
}
