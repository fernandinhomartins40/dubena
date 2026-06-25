import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { Search, Plus, MoreHorizontal, Pencil, Trash2, Users, ArrowLeft, Save, Settings } from 'lucide-react'
import {
  Button, Card, CardContent, PageHeader, Input, Badge, DataTable, type Column, EmptyState, Field, AsyncSelect, AsyncState,
  Tabs, TabsList, TabsTrigger, TabsContent,
  DropdownMenu, DropdownMenuTrigger, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator,
  ConfirmDialog, toast,
} from '@/components/ui'
import { useAuth } from '@/lib/auth'
import { useResourceForm } from '@/lib/useResourceForm'
import {
  useColaboradores, useColaborador, useSalvarColaborador, useExcluirColaborador, type Colaborador,
  useFamilia, useAddFamilia, useDelFamilia, useRecessos, useComissoes,
  useExames, useAddExame, useTurnos, useAddTurno, usePontos, useAddPonto,
} from './api'

const fmtData = (s: string | null) => (s ? new Date(s).toLocaleDateString('pt-BR') : '—')

export function ColaboradoresListPage() {
  const navigate = useNavigate()
  const { can } = useAuth()
  const [busca, setBusca] = useState(''); const [q, setQ] = useState(''); const [page, setPage] = useState(1)
  const { data, isLoading, isFetching } = useColaboradores(q, page)
  const excluir = useExcluirColaborador()
  const [del, setDel] = useState<Colaborador | null>(null)

  const columns: Column<Colaborador>[] = [
    { key: 'nome', header: 'Nome', cell: (c) => <div className="flex items-center gap-3"><div className="grid size-9 place-items-center rounded-full bg-secondary text-muted-foreground shrink-0"><Users size={16} /></div><div><div className="font-medium">{c.nome} {c.datadesligamento && <Badge variant="secondary">desligado</Badge>}</div>{c.cargo && <div className="text-xs text-muted-foreground">{c.cargo}</div>}</div></div> },
    { key: 'cpf', header: 'CPF', cell: (c) => <span className="text-muted-foreground tabular-nums">{c.cpf || '—'}</span> },
    { key: 'adm', header: 'Admissão', cell: (c) => fmtData(c.dataadmissao) },
    {
      key: 'acoes', header: '', align: 'right', width: 'w-16', cell: (c) => (
        <div onClick={(e) => e.stopPropagation()} className="flex justify-end">
          <DropdownMenu>
            <DropdownMenuTrigger asChild><Button variant="ghost" size="icon"><MoreHorizontal size={18} /></Button></DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              {can('colaborador.edit') && <DropdownMenuItem onClick={() => navigate(`/colaboradores/${c.id}`)}><Pencil /> Editar</DropdownMenuItem>}
              {can('colaborador.delete') && (<><DropdownMenuSeparator /><DropdownMenuItem destructive onClick={() => setDel(c)}><Trash2 /> Excluir</DropdownMenuItem></>)}
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      ),
    },
  ]
  return (
    <div>
      <PageHeader title="Colaboradores" subtitle={data ? `${data.meta.total} colaboradores` : 'Carregando…'}
        action={<div className="flex gap-2">
          {can('colaborador.view') && <Button variant="outline" onClick={() => navigate('/configuracoes?tab=colaboradores')}><Settings size={16} /> Configurações</Button>}
          {can('colaborador.create') && <Button onClick={() => navigate('/colaboradores/novo')}><Plus size={16} /> Novo colaborador</Button>}
        </div>} />
      <Card className="mb-4 p-3"><form onSubmit={(e) => { e.preventDefault(); setPage(1); setQ(busca) }} className="flex gap-2">
        <div className="relative flex-1"><Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" /><Input value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Buscar por nome…" className="pl-9" /></div>
        <Button type="submit" variant="secondary">Buscar</Button>
      </form></Card>
      <DataTable columns={columns} rows={data?.data} loading={isLoading} rowKey={(c) => c.id} onRowClick={can('colaborador.edit') ? (c) => navigate(`/colaboradores/${c.id}`) : undefined}
        page={data?.meta.current_page} lastPage={data?.meta.last_page} onPageChange={setPage} fetching={isFetching}
        empty={<EmptyState icon={<Users />} title="Nenhum colaborador" />} />
      <ConfirmDialog
        open={!!del} onOpenChange={(o) => !o && setDel(null)}
        title="Excluir colaborador"
        description={<>Excluir <strong>{del?.nome}</strong>?</>}
        loading={excluir.isPending}
        onConfirm={async () => { try { await excluir.mutateAsync(del!.id); toast.success('Excluído.') } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro.') } finally { setDel(null) } }}
      />
    </div>
  )
}

const VAZIO = { nome: '', numero: '', cidade_id: null as number | null, bairro_id: null as number | null, cpf: '', rg: '', email: '', sexo: '', cargo_id: null as number | null, datanascimento: '', dataadmissao: '', datadesligamento: '', cep: '' }

export function ColaboradorFormPage() {
  const { id } = useParams(); const navigate = useNavigate()
  const editId = id && id !== 'novo' ? Number(id) : null
  const { data: existente } = useColaborador(editId)
  const salvar = useSalvarColaborador()
  const { form, campo, erros, submit } = useResourceForm<any>({ vazio: { ...VAZIO }, existente })
  const [labels, setLabels] = useState<Record<string, string | null>>({})
  const [aba, setAba] = useState('dados')

  useEffect(() => {
    if (existente) setLabels({ cidade: existente.cidade_label, bairro: existente.bairro_label, cargo: existente.cargo_label })
  }, [existente])

  async function onSubmit() {
    try { const salvo = await submit((data) => salvar.mutateAsync({ id: editId, data })); toast.success(editId ? 'Atualizado.' : 'Cadastrado.'); navigate(`/colaboradores/${salvo.id}`) }
    catch (e: any) { if (e?.response?.status === 422) { setAba('dados'); toast.error('Verifique os campos.') } else toast.error('Erro ao salvar.') }
  }

  return (
    <div>
      <PageHeader breadcrumb={<button onClick={() => navigate('/colaboradores')} className="hover:text-foreground">Colaboradores</button>}
        title={editId ? (form.nome || 'Colaborador') : 'Novo colaborador'}
        action={<><Button variant="outline" onClick={() => navigate('/colaboradores')}><ArrowLeft size={16} /> Voltar</Button><Button loading={salvar.isPending} onClick={onSubmit}><Save size={16} /> Salvar</Button></>} />
      <Tabs value={aba} onValueChange={setAba}>
        <TabsList className="overflow-x-auto">
          <TabsTrigger value="dados">Dados</TabsTrigger>
          {editId && <TabsTrigger value="familia">Família</TabsTrigger>}
          {editId && <TabsTrigger value="recessos">Recessos</TabsTrigger>}
          {editId && <TabsTrigger value="comissoes">Comissões</TabsTrigger>}
          {editId && <TabsTrigger value="exames">Exames</TabsTrigger>}
          {editId && <TabsTrigger value="turnos">Turnos</TabsTrigger>}
          {editId && <TabsTrigger value="ponto">Ponto</TabsTrigger>}
        </TabsList>
        <TabsContent value="dados">
          <Card><CardContent className="pt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <Field label="Nome" required error={erros.nome} className="md:col-span-2"><Input value={form.nome} error={!!erros.nome} onChange={(e) => campo('nome', e.target.value)} /></Field>
            <Field label="Cargo"><AsyncSelect endpoint="/lookups/cargos" value={form.cargo_id} valueLabel={labels.cargo} onChange={(id, o) => { campo('cargo_id', id); setLabels((l) => ({ ...l, cargo: o?.label ?? null })) }} /></Field>
            <Field label="E-mail"><Input type="email" value={form.email ?? ''} onChange={(e) => campo('email', e.target.value)} /></Field>
            <Field label="CPF"><Input value={form.cpf ?? ''} onChange={(e) => campo('cpf', e.target.value)} /></Field>
            <Field label="RG"><Input value={form.rg ?? ''} onChange={(e) => campo('rg', e.target.value)} /></Field>
            <Field label="Nascimento"><Input type="date" value={form.datanascimento ?? ''} onChange={(e) => campo('datanascimento', e.target.value)} /></Field>
            <Field label="Admissão"><Input type="date" value={form.dataadmissao ?? ''} onChange={(e) => campo('dataadmissao', e.target.value)} /></Field>
            <Field label="Desligamento"><Input type="date" value={form.datadesligamento ?? ''} onChange={(e) => campo('datadesligamento', e.target.value)} /></Field>
            <Field label="Cidade" required error={erros.cidade_id}><AsyncSelect endpoint="/lookups/cidades" value={form.cidade_id} valueLabel={labels.cidade} error={!!erros.cidade_id} onChange={(id, o) => { campo('cidade_id', id); setLabels((l) => ({ ...l, cidade: o?.label ?? null, bairro: null })); campo('bairro_id', null) }} /></Field>
            <Field label="Bairro" required error={erros.bairro_id}><AsyncSelect endpoint="/lookups/bairros" params={{ cidade_id: form.cidade_id }} value={form.bairro_id} valueLabel={labels.bairro} disabled={!form.cidade_id} error={!!erros.bairro_id} onChange={(id, o) => { campo('bairro_id', id); setLabels((l) => ({ ...l, bairro: o?.label ?? null })) }} /></Field>
            <Field label="Número" required error={erros.numero}><Input value={form.numero} error={!!erros.numero} onChange={(e) => campo('numero', e.target.value)} /></Field>
            <Field label="CEP"><Input value={form.cep ?? ''} onChange={(e) => campo('cep', e.target.value)} /></Field>
          </CardContent></Card>
        </TabsContent>
        {editId && <TabsContent value="familia"><Card><CardContent className="pt-6"><FamiliaTab colaboradorId={editId} /></CardContent></Card></TabsContent>}
        {editId && <TabsContent value="recessos"><RecessosTab colaboradorId={editId} /></TabsContent>}
        {editId && <TabsContent value="comissoes"><ComissoesTab colaboradorId={editId} /></TabsContent>}
        {editId && <TabsContent value="exames"><Card><CardContent className="pt-6"><ExamesTab colaboradorId={editId} /></CardContent></Card></TabsContent>}
        {editId && <TabsContent value="turnos"><Card><CardContent className="pt-6"><TurnosTab colaboradorId={editId} /></CardContent></Card></TabsContent>}
        {editId && <TabsContent value="ponto"><Card><CardContent className="pt-6"><PontoTab colaboradorId={editId} /></CardContent></Card></TabsContent>}
      </Tabs>
    </div>
  )
}

function FamiliaTab({ colaboradorId }: { colaboradorId: number }) {
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

function RecessosTab({ colaboradorId }: { colaboradorId: number }) {
  const { data, isLoading } = useRecessos(colaboradorId)
  const columns: Column<any>[] = [
    { key: 'desc', header: 'Descrição', cell: (r) => r.descricao || '—' },
    { key: 'ini', header: 'Início', cell: (r) => fmtData(r.datainicio) },
    { key: 'fim', header: 'Fim', cell: (r) => fmtData(r.datafinal) },
  ]
  return <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(r) => r.id} empty={<EmptyState icon={<Users />} title="Nenhum recesso" />} />
}

function ComissoesTab({ colaboradorId }: { colaboradorId: number }) {
  const { data, isLoading } = useComissoes(colaboradorId)
  const columns: Column<any>[] = [
    { key: 'prod', header: 'Produto', cell: (c) => c.produto || '—' },
    { key: 'perc', header: '%', align: 'right', cell: (c) => <span className="tabular-nums">{c.percentual ?? 0}</span> },
    { key: 'ini', header: 'Início', cell: (c) => fmtData(c.datainicio) },
    { key: 'fim', header: 'Fim', cell: (c) => fmtData(c.datafim) },
  ]
  return <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(c) => c.id} empty={<EmptyState icon={<Users />} title="Nenhuma comissão" />} />
}

const DIAS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']

function ExamesTab({ colaboradorId }: { colaboradorId: number }) {
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

function TurnosTab({ colaboradorId }: { colaboradorId: number }) {
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

function PontoTab({ colaboradorId }: { colaboradorId: number }) {
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
