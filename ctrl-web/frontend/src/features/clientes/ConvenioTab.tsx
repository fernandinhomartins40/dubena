import { useEffect, useState } from 'react'
import { Trash2, Plus, Save, Users } from 'lucide-react'
import {
  Button, Field, Input, Switch, AsyncSelect, EmptyState, toast,
  Select, SelectTrigger, SelectValue, SelectContent, SelectItem,
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

  async function onSalvar() {
    try { await salvar.mutateAsync(form); toast.success('Convênio salvo.') }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao salvar convênio.') }
  }

  if (isLoading) return <p className="text-sm text-muted-foreground">Carregando…</p>

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3 rounded-lg border border-border p-4">
        <Switch checked={!!form.convenioativo} onCheckedChange={(b) => set('convenioativo', b)} />
        <div>
          <p className="text-sm font-medium">Empresa conveniada</p>
          <p className="text-xs text-muted-foreground">Habilita compra por convênio com fechamento mensal.</p>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <Field label="Data do contrato"><Input type="date" value={form.datacontrato ?? ''} onChange={(e) => set('datacontrato', e.target.value)} /></Field>
        <Field label="Limite de compra"><Input value={form.limitecompra ?? ''} onChange={(e) => set('limitecompra', e.target.value)} /></Field>
        <Field label="Desconto (%)"><Input value={form.comissao ?? ''} onChange={(e) => set('comissao', e.target.value)} /></Field>
        <Field label="Desconto para">
          <Select value={String(form.comissaodestino ?? '')} onValueChange={(v) => set('comissaodestino', v)}>
            <SelectTrigger><SelectValue placeholder="Selecione" /></SelectTrigger>
            <SelectContent><SelectItem value="1">Conveniado</SelectItem><SelectItem value="2">Empresa</SelectItem></SelectContent>
          </Select>
        </Field>
        <Field label="Dia de fechamento"><Input type="number" value={form.diafechamento ?? ''} onChange={(e) => set('diafechamento', e.target.value)} /></Field>
        <Field label="Dia de vencimento"><Input type="number" value={form.diavencimento ?? ''} onChange={(e) => set('diavencimento', e.target.value)} /></Field>
      </div>

      <div className="space-y-3">
        <p className="text-sm font-semibold text-muted-foreground">Representante legal</p>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <Field label="Nome"><Input value={form.nomerepresentante ?? ''} onChange={(e) => set('nomerepresentante', e.target.value)} /></Field>
          <Field label="CPF"><Input value={form.cpfrepresentante ?? ''} onChange={(e) => set('cpfrepresentante', e.target.value)} /></Field>
          <Field label="RG"><Input value={form.rgrepresentante ?? ''} onChange={(e) => set('rgrepresentante', e.target.value)} /></Field>
        </div>
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
