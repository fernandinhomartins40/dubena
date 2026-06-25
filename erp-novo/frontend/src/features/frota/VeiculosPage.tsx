import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { Search, Plus, Pencil, Trash2, Truck, ArrowLeft, Save } from 'lucide-react'
import {
  Button, Card, CardContent, PageHeader, Input, DataTable, type Column, EmptyState, Field, AsyncSelect,
  Tabs, TabsList, TabsTrigger, TabsContent, ConfirmDialog, toast,
} from '@/components/ui'
import { useAuth } from '@/lib/auth'
import { useResourceForm } from '@/lib/useResourceForm'
import {
  useVeiculos, useVeiculo, useSalvarVeiculo, useExcluirVeiculo, type Veiculo,
  useAbastecimentos, useTrocasOleo, usePneus,
} from './api'
import { num, data as fmtData } from '@/lib/format'

export function VeiculosListPage() {
  const navigate = useNavigate(); const { can } = useAuth()
  const [busca, setBusca] = useState(''); const [q, setQ] = useState('')
  const { data, isLoading } = useVeiculos(q)
  const excluir = useExcluirVeiculo(); const [del, setDel] = useState<Veiculo | null>(null)

  const columns: Column<Veiculo>[] = [
    { key: 'desc', header: 'Veículo', cell: (v) => <div className="flex items-center gap-3"><div className="grid size-9 place-items-center rounded-md bg-secondary text-muted-foreground"><Truck size={16} /></div><div><div className="font-medium">{v.descricao}</div><div className="text-xs text-muted-foreground tabular-nums">{v.placa}</div></div></div> },
    { key: 'km', header: 'KM atual', align: 'right', cell: (v) => <span className="tabular-nums">{num(v.kmatual)}</span> },
    {
      key: 'acoes', header: '', align: 'right', width: 'w-24', cell: (v) => (
        <div className="flex justify-end gap-1" onClick={(e) => e.stopPropagation()}>
          {can('veiculo.edit') && <Button variant="ghost" size="icon" onClick={() => navigate(`/veiculos/${v.id}`)}><Pencil size={16} /></Button>}
          {can('veiculo.delete') && <Button variant="ghost" size="icon" onClick={() => setDel(v)}><Trash2 size={16} /></Button>}
        </div>
      ),
    },
  ]
  return (
    <div>
      <PageHeader title="Veículos" subtitle="Frota e manutenção" action={can('veiculo.create') && <Button onClick={() => navigate('/veiculos/novo')}><Plus size={16} /> Novo veículo</Button>} />
      <Card className="mb-4 p-3"><form onSubmit={(e) => { e.preventDefault(); setQ(busca) }} className="flex gap-2">
        <div className="relative flex-1"><Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" /><Input value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Buscar por descrição ou placa…" className="pl-9" /></div>
        <Button type="submit" variant="secondary">Buscar</Button>
      </form></Card>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(v) => v.id} onRowClick={can('veiculo.edit') ? (v) => navigate(`/veiculos/${v.id}`) : undefined} empty={<EmptyState icon={<Truck />} title="Nenhum veículo" />} />
      <ConfirmDialog
        open={!!del} onOpenChange={(o) => !o && setDel(null)}
        title="Excluir veículo"
        description={<>Excluir <strong>{del?.descricao}</strong>?</>}
        loading={excluir.isPending}
        onConfirm={async () => { try { await excluir.mutateAsync(del!.id); toast.success('Excluído.') } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro.') } finally { setDel(null) } }}
      />
    </div>
  )
}

const VAZIO = { placa: '', descricao: '', veiculotipo_id: null as number | null, tipocombustivel_id: null as number | null, kmatual: '', kminicial: '' }

export function VeiculoFormPage() {
  const { id } = useParams(); const navigate = useNavigate()
  const editId = id && id !== 'novo' ? Number(id) : null
  const { data: existente } = useVeiculo(editId)
  const salvar = useSalvarVeiculo()
  const { form, campo, erros, submit } = useResourceForm<any>({ vazio: { ...VAZIO }, existente })
  const [labels, setLabels] = useState<Record<string, string | null>>({}); const [aba, setAba] = useState('dados')

  useEffect(() => {
    if (existente) setLabels({ tipo: existente.tipo_label, comb: existente.combustivel_label })
  }, [existente])

  async function onSubmit() {
    try { const salvo = await submit((data) => salvar.mutateAsync({ id: editId, data })); toast.success(editId ? 'Atualizado.' : 'Cadastrado.'); navigate(`/veiculos/${salvo.id}`) }
    catch (e: any) { if (e?.response?.status === 422) { setAba('dados'); toast.error('Verifique os campos.') } else toast.error('Erro ao salvar.') }
  }

  return (
    <div>
      <PageHeader breadcrumb={<button onClick={() => navigate('/veiculos')} className="hover:text-foreground">Veículos</button>}
        title={editId ? (form.descricao || 'Veículo') : 'Novo veículo'}
        action={<><Button variant="outline" onClick={() => navigate('/veiculos')}><ArrowLeft size={16} /> Voltar</Button><Button loading={salvar.isPending} onClick={onSubmit}><Save size={16} /> Salvar</Button></>} />
      <Tabs value={aba} onValueChange={setAba}>
        <TabsList className="overflow-x-auto">
          <TabsTrigger value="dados">Dados</TabsTrigger>
          {editId && <TabsTrigger value="abast">Abastecimentos</TabsTrigger>}
          {editId && <TabsTrigger value="oleo">Trocas de óleo</TabsTrigger>}
          {editId && <TabsTrigger value="pneus">Pneus</TabsTrigger>}
        </TabsList>
        <TabsContent value="dados">
          <Card><CardContent className="pt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <Field label="Placa" required error={erros.placa}><Input value={form.placa} error={!!erros.placa} onChange={(e) => campo('placa', e.target.value.toUpperCase())} /></Field>
            <Field label="Descrição" required error={erros.descricao}><Input value={form.descricao} error={!!erros.descricao} onChange={(e) => campo('descricao', e.target.value)} /></Field>
            <Field label="Tipo de veículo" required error={erros.veiculotipo_id}><AsyncSelect endpoint="/lookups/veiculo-tipos" value={form.veiculotipo_id} valueLabel={labels.tipo} error={!!erros.veiculotipo_id} onChange={(id, o) => { campo('veiculotipo_id', id); setLabels((l) => ({ ...l, tipo: o?.label ?? null })) }} /></Field>
            <Field label="Combustível" required error={erros.tipocombustivel_id}><AsyncSelect endpoint="/lookups/combustiveis" value={form.tipocombustivel_id} valueLabel={labels.comb} error={!!erros.tipocombustivel_id} onChange={(id, o) => { campo('tipocombustivel_id', id); setLabels((l) => ({ ...l, comb: o?.label ?? null })) }} /></Field>
            <Field label="KM inicial"><Input type="number" value={form.kminicial ?? ''} onChange={(e) => campo('kminicial', e.target.value)} /></Field>
            <Field label="KM atual"><Input type="number" value={form.kmatual ?? ''} onChange={(e) => campo('kmatual', e.target.value)} /></Field>
          </CardContent></Card>
        </TabsContent>
        {editId && <TabsContent value="abast"><TimelineTab hook={useAbastecimentos} id={editId} cols={[['data', 'Data', (r) => fmtData(r.data)], ['kmatual', 'KM', (r) => num(r.kmatual)], ['totallitros', 'Litros', (r) => num(r.totallitros)], ['mediaconsumo', 'Média', (r) => num(r.mediaconsumo)]]} /></TabsContent>}
        {editId && <TabsContent value="oleo"><TimelineTab hook={useTrocasOleo} id={editId} cols={[['data', 'Data', (r) => fmtData(r.data)], ['kmtrocaoleo', 'KM troca', (r) => num(r.kmtrocaoleo)], ['oleoproximatroca', 'Próxima', (r) => num(r.oleoproximatroca)]]} /></TabsContent>}
        {editId && <TabsContent value="pneus"><TimelineTab hook={usePneus} id={editId} cols={[['data', 'Data', (r) => fmtData(r.data)], ['km', 'KM', (r) => num(r.km)], ['quantidade', 'Qtd', (r) => num(r.quantidade)], ['valor', 'Valor', (r) => num(r.valor)]]} /></TabsContent>}
      </Tabs>
    </div>
  )
}

function TimelineTab({ hook, id, cols }: { hook: (id: number) => { data: any[] | undefined; isLoading: boolean }; id: number; cols: [string, string, (r: any) => any][] }) {
  const { data, isLoading } = hook(id)
  const columns: Column<any>[] = cols.map(([key, header, cell]) => ({ key, header, cell }))
  return <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(r) => r.id} empty={<EmptyState icon={<Truck />} title="Sem registros" />} />
}
