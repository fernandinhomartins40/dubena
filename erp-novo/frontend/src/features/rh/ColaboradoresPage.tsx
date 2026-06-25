import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { Search, Plus, MoreHorizontal, Pencil, Trash2, Users, ArrowLeft, Save, Settings } from 'lucide-react'
import {
  Button, Card, CardContent, PageHeader, Input, Badge, DataTable, type Column, EmptyState, Field, AsyncSelect,
  Tabs, TabsList, TabsTrigger, TabsContent,
  DropdownMenu, DropdownMenuTrigger, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator,
  ConfirmDialog, toast,
} from '@/components/ui'
import { useAuth } from '@/lib/auth'
import { useResourceForm } from '@/lib/useResourceForm'
import { data as fmtData } from '@/lib/format'
import { useColaboradores, useColaborador, useSalvarColaborador, useExcluirColaborador, type Colaborador } from './api'
import { FamiliaTab } from './tabs/FamiliaTab'
import { RecessosTab } from './tabs/RecessosTab'
import { ComissoesTab } from './tabs/ComissoesTab'
import { ExamesTab } from './tabs/ExamesTab'
import { TurnosTab } from './tabs/TurnosTab'
import { PontoTab } from './tabs/PontoTab'

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
