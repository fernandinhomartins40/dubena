import { useState } from 'react'
import { Plus, Pencil, Trash2, Search, MapPin } from 'lucide-react'
import {
  Button, Card, PageHeader, Input, Badge, DataTable, type Column, EmptyState,
  Field, CheckboxField, AsyncSelect,
  Tabs, TabsList, TabsTrigger, TabsContent,
  FormDialog, ConfirmDialog, toast,
} from '@/components/ui'
import {
  useCidades, useBairros, useRuas, useRegioes, useSalvarGeo, useExcluirGeo,
  type Cidade, type Bairro, type Rua, type Regiao,
} from './api'

export function GeograficoPage() {
  return (
    <div>
      <PageHeader title="Geográfico" subtitle="Cidades, bairros, ruas e regiões usados em endereços e entregas" />
      <Tabs defaultValue="cidades">
        <TabsList className="overflow-x-auto">
          <TabsTrigger value="cidades">Cidades</TabsTrigger>
          <TabsTrigger value="bairros">Bairros</TabsTrigger>
          <TabsTrigger value="ruas">Ruas</TabsTrigger>
          <TabsTrigger value="regioes">Regiões</TabsTrigger>
        </TabsList>
        <TabsContent value="cidades"><CidadesTab /></TabsContent>
        <TabsContent value="bairros"><BairrosTab /></TabsContent>
        <TabsContent value="ruas"><RuasTab /></TabsContent>
        <TabsContent value="regioes"><RegioesTab /></TabsContent>
      </Tabs>
    </div>
  )
}

/** Barra de busca + botão novo, reusada nas abas. */
function Toolbar({ q, setQ, onNovo, placeholder, children }: {
  q: string; setQ: (s: string) => void; onNovo: () => void; placeholder: string; children?: React.ReactNode
}) {
  const [texto, setTexto] = useState(q)
  return (
    <Card className="mb-4 p-3">
      <form onSubmit={(e) => { e.preventDefault(); setQ(texto) }} className="flex flex-wrap gap-2 items-center">
        <div className="relative flex-1 min-w-[200px]">
          <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
          <Input value={texto} onChange={(e) => setTexto(e.target.value)} placeholder={placeholder} className="pl-9" />
        </div>
        {children}
        <Button type="submit" variant="secondary">Buscar</Button>
        <Button type="button" onClick={onNovo}><Plus size={16} /> Novo</Button>
      </form>
    </Card>
  )
}

function acoesCol<T extends { id: number; descricao: string }>(onEdit: (r: T) => void, onDel: (r: T) => void): Column<T> {
  return {
    key: 'acoes', header: '', align: 'right', width: 'w-24',
    cell: (r) => (
      <div className="flex justify-end gap-1" onClick={(e) => e.stopPropagation()}>
        <Button variant="ghost" size="icon" onClick={() => onEdit(r)} aria-label="Editar"><Pencil size={16} /></Button>
        <Button variant="ghost" size="icon" onClick={() => onDel(r)} aria-label="Excluir"><Trash2 size={16} /></Button>
      </div>
    ),
  }
}

function ConfirmDelete({ open, nome, tipo, loading, onClose, onConfirm }: {
  open: boolean; nome?: string; tipo: string; loading: boolean; onClose: () => void; onConfirm: () => void
}) {
  return (
    <ConfirmDialog
      open={open} onOpenChange={(o) => !o && onClose()}
      title={`Excluir ${tipo}`}
      description={<>Excluir <strong>{nome}</strong>? Esta ação não pode ser desfeita.</>}
      loading={loading} onConfirm={onConfirm}
    />
  )
}

// =================== CIDADES ===================
function CidadesTab() {
  const [q, setQ] = useState(''); const [uf, setUf] = useState(''); const [page, setPage] = useState(1)
  const { data, isLoading, isFetching } = useCidades(q, uf, page)
  const salvar = useSalvarGeo('cidades'); const excluir = useExcluirGeo('cidades')
  const [edit, setEdit] = useState<Partial<Cidade> | null>(null)
  const [del, setDel] = useState<Cidade | null>(null)
  const [ufLabel, setUfLabel] = useState<string | null>(null)

  async function onSalvar() {
    if (!edit?.descricao?.trim() || !edit?.uf || edit?.cod_ibge == null) { toast.error('Descrição, UF e código IBGE são obrigatórios.'); return }
    try {
      await salvar.mutateAsync({ id: edit.id, descricao: edit.descricao, uf: edit.uf, cod_ibge: edit.cod_ibge })
      toast.success('Cidade salva.'); setEdit(null)
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao salvar.') }
  }

  const columns: Column<Cidade>[] = [
    { key: 'descricao', header: 'Cidade', cell: (c) => <span className="font-medium">{c.descricao} {c.grupo_id === null && <Badge variant="secondary">global</Badge>}</span> },
    { key: 'uf', header: 'UF', align: 'center', width: 'w-16', cell: (c) => c.uf },
    { key: 'ibge', header: 'Cód. IBGE', align: 'right', cell: (c) => <span className="tabular-nums text-muted-foreground">{c.cod_ibge}</span> },
    acoesCol<Cidade>((c) => { setEdit(c); setUfLabel(c.uf) }, setDel),
  ]

  return (
    <>
      <Toolbar q={q} setQ={(v) => { setPage(1); setQ(v) }} onNovo={() => { setEdit({}); setUfLabel(null) }} placeholder="Buscar cidade…">
        <div className="w-40"><AsyncSelect endpoint="/lookups/estados" value={uf ? -1 : null} valueLabel={uf || null} placeholder="Filtrar UF"
          onChange={(_, opt) => { setPage(1); setUf(opt?.uf ? String(opt.uf) : '') }} /></div>
      </Toolbar>
      <DataTable columns={columns} rows={data?.data} loading={isLoading} rowKey={(c) => c.id} onRowClick={(c) => { setEdit(c); setUfLabel(c.uf) }}
        page={data?.meta.current_page} lastPage={data?.meta.last_page} onPageChange={setPage} fetching={isFetching}
        empty={<EmptyState icon={<MapPin />} title="Nenhuma cidade" />} />

      <FormDialog open={!!edit} onOpenChange={(o) => !o && setEdit(null)}
        title={edit?.id ? 'Editar cidade' : 'Nova cidade'} loading={salvar.isPending} onConfirm={onSalvar}>
        <Field label="Descrição" required><Input autoFocus value={edit?.descricao ?? ''} onChange={(e) => setEdit((s) => ({ ...s, descricao: e.target.value }))} /></Field>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <Field label="UF" required><AsyncSelect endpoint="/lookups/estados" value={edit?.uf ? -1 : null} valueLabel={ufLabel}
            onChange={(_, opt) => { setEdit((s) => ({ ...s, uf: opt?.uf ? String(opt.uf) : '' })); setUfLabel(opt?.label ?? null) }} /></Field>
          <Field label="Código IBGE" required><Input type="number" value={edit?.cod_ibge ?? ''} onChange={(e) => setEdit((s) => ({ ...s, cod_ibge: e.target.value ? Number(e.target.value) : undefined }))} /></Field>
        </div>
      </FormDialog>

      <ConfirmDelete open={!!del} nome={del?.descricao} tipo="cidade" loading={excluir.isPending} onClose={() => setDel(null)}
        onConfirm={async () => { try { await excluir.mutateAsync(del!.id); toast.success('Cidade excluída.') } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro.') } finally { setDel(null) } }} />
    </>
  )
}

// =================== BAIRROS ===================
function BairrosTab() {
  const [q, setQ] = useState(''); const [cidadeId, setCidadeId] = useState<number | null>(null); const [cidadeLabel, setCidadeLabel] = useState<string | null>(null); const [page, setPage] = useState(1)
  const { data, isLoading, isFetching } = useBairros(q, cidadeId, page)
  const salvar = useSalvarGeo('bairros'); const excluir = useExcluirGeo('bairros')
  const [edit, setEdit] = useState<Partial<Bairro> | null>(null)
  const [editCidadeLabel, setEditCidadeLabel] = useState<string | null>(null)
  const [del, setDel] = useState<Bairro | null>(null)

  async function onSalvar() {
    if (!edit?.descricao?.trim() || !edit?.cidade_id) { toast.error('Descrição e cidade são obrigatórios.'); return }
    try { await salvar.mutateAsync({ id: edit.id, descricao: edit.descricao, cidade_id: edit.cidade_id }); toast.success('Bairro salvo.'); setEdit(null) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao salvar.') }
  }

  const columns: Column<Bairro>[] = [
    { key: 'descricao', header: 'Bairro', cell: (b) => <span className="font-medium">{b.descricao}</span> },
    { key: 'cidade', header: 'Cidade', cell: (b) => <span className="text-muted-foreground">{b.cidade ?? '—'}</span> },
    acoesCol<Bairro>((b) => { setEdit(b); setEditCidadeLabel(b.cidade) }, setDel),
  ]

  return (
    <>
      <Toolbar q={q} setQ={(v) => { setPage(1); setQ(v) }} onNovo={() => { setEdit({}); setEditCidadeLabel(null) }} placeholder="Buscar bairro…">
        <div className="w-56"><AsyncSelect endpoint="/lookups/cidades" value={cidadeId} valueLabel={cidadeLabel} placeholder="Filtrar cidade"
          onChange={(id, opt) => { setPage(1); setCidadeId(id); setCidadeLabel(opt?.label ?? null) }} /></div>
      </Toolbar>
      <DataTable columns={columns} rows={data?.data} loading={isLoading} rowKey={(b) => b.id} onRowClick={(b) => { setEdit(b); setEditCidadeLabel(b.cidade) }}
        page={data?.meta.current_page} lastPage={data?.meta.last_page} onPageChange={setPage} fetching={isFetching}
        empty={<EmptyState icon={<MapPin />} title="Nenhum bairro" />} />

      <FormDialog open={!!edit} onOpenChange={(o) => !o && setEdit(null)}
        title={edit?.id ? 'Editar bairro' : 'Novo bairro'} loading={salvar.isPending} onConfirm={onSalvar}>
        <Field label="Descrição" required><Input autoFocus value={edit?.descricao ?? ''} onChange={(e) => setEdit((s) => ({ ...s, descricao: e.target.value }))} /></Field>
        <Field label="Cidade" required><AsyncSelect endpoint="/lookups/cidades" value={edit?.cidade_id ?? null} valueLabel={editCidadeLabel}
          onChange={(id, opt) => { setEdit((s) => ({ ...s, cidade_id: id ?? undefined })); setEditCidadeLabel(opt?.label ?? null) }} /></Field>
      </FormDialog>

      <ConfirmDelete open={!!del} nome={del?.descricao} tipo="bairro" loading={excluir.isPending} onClose={() => setDel(null)}
        onConfirm={async () => { try { await excluir.mutateAsync(del!.id); toast.success('Bairro excluído.') } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro.') } finally { setDel(null) } }} />
    </>
  )
}

// =================== RUAS ===================
function RuasTab() {
  const [q, setQ] = useState(''); const [cidadeId, setCidadeId] = useState<number | null>(null); const [cidadeLabel, setCidadeLabel] = useState<string | null>(null); const [page, setPage] = useState(1)
  const { data, isLoading, isFetching } = useRuas(q, cidadeId, page)
  const salvar = useSalvarGeo('ruas'); const excluir = useExcluirGeo('ruas')
  const [edit, setEdit] = useState<Partial<Rua> | null>(null)
  const [editCidadeLabel, setEditCidadeLabel] = useState<string | null>(null)
  const [del, setDel] = useState<Rua | null>(null)

  async function onSalvar() {
    if (!edit?.descricao?.trim() || !edit?.cidade_id) { toast.error('Descrição e cidade são obrigatórios.'); return }
    try { await salvar.mutateAsync({ id: edit.id, descricao: edit.descricao, cidade_id: edit.cidade_id, cep: edit.cep ?? null, ativo: edit.ativo !== 0 }); toast.success('Rua salva.'); setEdit(null) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao salvar.') }
  }

  const columns: Column<Rua>[] = [
    { key: 'descricao', header: 'Rua', cell: (r) => <span className="font-medium">{r.descricao} {r.ativo === 0 && <Badge variant="secondary">inativa</Badge>}</span> },
    { key: 'cidade', header: 'Cidade', cell: (r) => <span className="text-muted-foreground">{r.cidade ?? '—'}</span> },
    { key: 'cep', header: 'CEP', cell: (r) => <span className="text-muted-foreground tabular-nums">{r.cep || '—'}</span> },
    acoesCol<Rua>((r) => { setEdit(r); setEditCidadeLabel(r.cidade) }, setDel),
  ]

  return (
    <>
      <Toolbar q={q} setQ={(v) => { setPage(1); setQ(v) }} onNovo={() => { setEdit({ ativo: 1 }); setEditCidadeLabel(null) }} placeholder="Buscar rua…">
        <div className="w-56"><AsyncSelect endpoint="/lookups/cidades" value={cidadeId} valueLabel={cidadeLabel} placeholder="Filtrar cidade"
          onChange={(id, opt) => { setPage(1); setCidadeId(id); setCidadeLabel(opt?.label ?? null) }} /></div>
      </Toolbar>
      <DataTable columns={columns} rows={data?.data} loading={isLoading} rowKey={(r) => r.id} onRowClick={(r) => { setEdit(r); setEditCidadeLabel(r.cidade) }}
        page={data?.meta.current_page} lastPage={data?.meta.last_page} onPageChange={setPage} fetching={isFetching}
        empty={<EmptyState icon={<MapPin />} title="Nenhuma rua" />} />

      <FormDialog open={!!edit} onOpenChange={(o) => !o && setEdit(null)}
        title={edit?.id ? 'Editar rua' : 'Nova rua'} loading={salvar.isPending} onConfirm={onSalvar}>
        <Field label="Descrição" required><Input autoFocus value={edit?.descricao ?? ''} onChange={(e) => setEdit((s) => ({ ...s, descricao: e.target.value }))} /></Field>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <Field label="Cidade" required><AsyncSelect endpoint="/lookups/cidades" value={edit?.cidade_id ?? null} valueLabel={editCidadeLabel}
            onChange={(id, opt) => { setEdit((s) => ({ ...s, cidade_id: id ?? undefined })); setEditCidadeLabel(opt?.label ?? null) }} /></Field>
          <Field label="CEP"><Input value={edit?.cep ?? ''} maxLength={9} onChange={(e) => setEdit((s) => ({ ...s, cep: e.target.value }))} /></Field>
        </div>
        <CheckboxField label="Ativa" checked={edit?.ativo !== 0} onChange={(b) => setEdit((s) => ({ ...s, ativo: b ? 1 : 0 }))} />
      </FormDialog>

      <ConfirmDelete open={!!del} nome={del?.descricao} tipo="rua" loading={excluir.isPending} onClose={() => setDel(null)}
        onConfirm={async () => { try { await excluir.mutateAsync(del!.id); toast.success('Rua excluída.') } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro.') } finally { setDel(null) } }} />
    </>
  )
}

// =================== REGIÕES ===================
function RegioesTab() {
  const [q, setQ] = useState(''); const [page, setPage] = useState(1)
  const { data, isLoading, isFetching } = useRegioes(q, page)
  const salvar = useSalvarGeo('regioes'); const excluir = useExcluirGeo('regioes')
  const [edit, setEdit] = useState<Partial<Regiao> | null>(null)
  const [del, setDel] = useState<Regiao | null>(null)

  async function onSalvar() {
    if (!edit?.descricao?.trim()) { toast.error('Informe a descrição.'); return }
    try { await salvar.mutateAsync({ id: edit.id, descricao: edit.descricao, ativo: edit.ativo !== 0 }); toast.success('Região salva.'); setEdit(null) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao salvar.') }
  }

  const columns: Column<Regiao>[] = [
    { key: 'descricao', header: 'Região', cell: (r) => <span className="font-medium">{r.descricao}</span> },
    { key: 'ativo', header: 'Status', cell: (r) => r.ativo ? <Badge variant="success">Ativa</Badge> : <Badge variant="secondary">Inativa</Badge> },
    acoesCol<Regiao>((r) => setEdit(r), setDel),
  ]

  return (
    <>
      <Toolbar q={q} setQ={(v) => { setPage(1); setQ(v) }} onNovo={() => setEdit({ ativo: 1 })} placeholder="Buscar região…" />
      <DataTable columns={columns} rows={data?.data} loading={isLoading} rowKey={(r) => r.id} onRowClick={(r) => setEdit(r)}
        page={data?.meta.current_page} lastPage={data?.meta.last_page} onPageChange={setPage} fetching={isFetching}
        empty={<EmptyState icon={<MapPin />} title="Nenhuma região" />} />

      <FormDialog open={!!edit} onOpenChange={(o) => !o && setEdit(null)}
        title={edit?.id ? 'Editar região' : 'Nova região'} loading={salvar.isPending} onConfirm={onSalvar}>
        <Field label="Descrição" required><Input autoFocus value={edit?.descricao ?? ''} onChange={(e) => setEdit((s) => ({ ...s, descricao: e.target.value }))} /></Field>
        <CheckboxField label="Ativa" checked={edit?.ativo !== 0} onChange={(b) => setEdit((s) => ({ ...s, ativo: b ? 1 : 0 }))} />
      </FormDialog>

      <ConfirmDelete open={!!del} nome={del?.descricao} tipo="região" loading={excluir.isPending} onClose={() => setDel(null)}
        onConfirm={async () => { try { await excluir.mutateAsync(del!.id); toast.success('Região excluída.') } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro.') } finally { setDel(null) } }} />
    </>
  )
}
