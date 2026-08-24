import { createContext, useContext, useEffect, useState } from 'react'
import { Plus, Pencil, Trash2, Search, MapPin, CheckCircle2, AlertTriangle } from 'lucide-react'
import {
  Button, Card, PageHeader, Input, Badge, DataTable, type Column, EmptyState,
  Field, CheckboxField, AsyncSelect, AsyncState,
  Tabs, TabsList, TabsTrigger, TabsContent,
  FormDialog, ConfirmDialog, toast,
} from '@/components/ui'
import {
  useCidades, useBairros, useRuas, useRegioes, useSalvarGeo, useExcluirGeo,
  useInconsistencias, useIgnorarPar,
  type Cidade, type Bairro, type Rua, type Regiao,
  type ParInconsistente, type TipoInconsistencia,
} from './api'
import { ImportacaoTab } from './ImportacaoTab'

/**
 * Cidade selecionada, compartilhada entre as abas.
 *
 * Bairro e rua só existem DENTRO de uma cidade — a tabela tem `cidade_id`
 * obrigatório. Antes, cada aba tinha seu próprio filtro começando vazio, então
 * a lista abria misturando todas as cidades: "Centro" aparecia 35 vezes (uma
 * por cidade) sem nada que as distinguisse, e o filtro escolhido numa aba se
 * perdia ao trocar para a outra. A cidade agora é o CONTEXTO da página, não um
 * filtro opcional de cada aba.
 */
const CidadeCtx = createContext<{
  id: number | null
  label: string | null
  definir: (id: number | null, label: string | null) => void
  /** Seleciona a cidade E vai para a aba do conteúdo dela, em um clique. */
  abrir: (c: { id: number; descricao: string }, aba: 'bairros' | 'ruas') => void
}>({ id: null, label: null, definir: () => {}, abrir: () => {} })

const useCidadeSelecionada = () => useContext(CidadeCtx)

export function GeograficoPage() {
  const [aba, setAba] = useState('cidades')
  const [cidade, setCidade] = useState<{ id: number | null; label: string | null }>({ id: null, label: null })

  const ctx = {
    id: cidade.id,
    label: cidade.label,
    definir: (id: number | null, label: string | null) => setCidade({ id, label }),
    abrir: (c: { id: number; descricao: string }, destino: 'bairros' | 'ruas') => {
      setCidade({ id: c.id, label: c.descricao })
      setAba(destino)
    },
  }

  // Só as abas que listam conteúdo DE uma cidade usam o seletor.
  const dependeDeCidade = aba === 'bairros' || aba === 'ruas'

  return (
    <CidadeCtx.Provider value={ctx}>
      <PageHeader title="Geográfico" subtitle="Cidades, bairros, ruas e regiões usados em endereços e entregas" />
      <Tabs value={aba} onValueChange={setAba}>
        <TabsList className="overflow-x-auto">
          <TabsTrigger value="cidades">Cidades</TabsTrigger>
          <TabsTrigger value="bairros">Bairros</TabsTrigger>
          <TabsTrigger value="ruas">Ruas</TabsTrigger>
          <TabsTrigger value="regioes">Regiões</TabsTrigger>
          <TabsTrigger value="importacao">Catálogo IBGE</TabsTrigger>
          <TabsTrigger value="inconsistencias">Inconsistências</TabsTrigger>
        </TabsList>

        {dependeDeCidade && <BarraCidade />}

        <TabsContent value="cidades"><CidadesTab /></TabsContent>
        <TabsContent value="bairros"><BairrosTab /></TabsContent>
        <TabsContent value="ruas"><RuasTab /></TabsContent>
        <TabsContent value="regioes"><RegioesTab /></TabsContent>
        <TabsContent value="importacao"><ImportacaoTab /></TabsContent>
        <TabsContent value="inconsistencias"><InconsistenciasTab /></TabsContent>
      </Tabs>
    </CidadeCtx.Provider>
  )
}

/** Seletor de cidade que vale para as abas de bairros e ruas. */
function BarraCidade() {
  const { id, label, definir } = useCidadeSelecionada()

  return (
    <Card className="mt-4 flex flex-wrap items-center gap-3 p-3">
      <div className="flex items-center gap-2 text-sm font-medium">
        <MapPin size={16} className="text-muted-foreground" />
        Cidade
      </div>
      <div className="w-64">
        <AsyncSelect endpoint="/lookups/cidades" value={id} valueLabel={label}
          placeholder="Escolha a cidade" onChange={(v, opt) => definir(v, opt?.label ?? null)} />
      </div>
      {id !== null && (
        <Button variant="ghost" size="sm" onClick={() => definir(null, null)}>Ver todas</Button>
      )}
      {id === null ? (
        <p className="flex items-center gap-1.5 text-xs text-amber-600 dark:text-amber-500">
          <AlertTriangle size={13} />
          Mostrando <strong>todas as cidades juntas</strong> — nomes como "Centro" se repetem em dezenas delas.
        </p>
      ) : (
        <p className="text-xs text-muted-foreground">Listando apenas o que pertence a esta cidade.</p>
      )}
    </Card>
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
  const { abrir } = useCidadeSelecionada()
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
    {
      // Atalho para o conteúdo da cidade: sem ele, ver os bairros de uma cidade
      // exigia trocar de aba e reencontrá-la no seletor.
      key: 'conteudo', header: '', align: 'right', width: 'w-40',
      cell: (c) => (
        <div className="flex justify-end gap-1" onClick={(e) => e.stopPropagation()}>
          <Button variant="ghost" size="sm" onClick={() => abrir(c, 'bairros')}>Bairros</Button>
          <Button variant="ghost" size="sm" onClick={() => abrir(c, 'ruas')}>Ruas</Button>
        </div>
      ),
    },
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
  const { id: cidadeId, label: cidadeLabel } = useCidadeSelecionada()
  const [q, setQ] = useState(''); const [page, setPage] = useState(1)
  const { data, isLoading, isFetching } = useBairros(q, cidadeId, page)
  const salvar = useSalvarGeo('bairros'); const excluir = useExcluirGeo('bairros')
  const [edit, setEdit] = useState<Partial<Bairro> | null>(null)
  const [editCidadeLabel, setEditCidadeLabel] = useState<string | null>(null)
  const [del, setDel] = useState<Bairro | null>(null)

  // Trocar de cidade tem de voltar à primeira página: a página 7 da cidade
  // anterior quase sempre nem existe na nova, e a lista abriria vazia.
  useEffect(() => { setPage(1) }, [cidadeId])

  async function onSalvar() {
    if (!edit?.descricao?.trim() || !edit?.cidade_id) { toast.error('Descrição e cidade são obrigatórios.'); return }
    try { await salvar.mutateAsync({ id: edit.id, descricao: edit.descricao, cidade_id: edit.cidade_id }); toast.success('Bairro salvo.'); setEdit(null) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao salvar.') }
  }

  const columns: Column<Bairro>[] = [
    { key: 'descricao', header: 'Bairro', cell: (b) => <span className="font-medium">{b.descricao}</span> },
    // A coluna Cidade só faz sentido quando a lista mistura cidades; com uma
    // cidade escolhida ela repetiria o mesmo valor em todas as linhas.
    ...(cidadeId === null
      ? [{ key: 'cidade', header: 'Cidade', cell: (b: Bairro) => <span className="text-muted-foreground">{b.cidade ?? '—'}</span> } as Column<Bairro>]
      : []),
    acoesCol<Bairro>((b) => { setEdit(b); setEditCidadeLabel(b.cidade) }, setDel),
  ]

  return (
    <>
      <Toolbar q={q} setQ={(v) => { setPage(1); setQ(v) }}
        onNovo={() => { setEdit({ cidade_id: cidadeId ?? undefined }); setEditCidadeLabel(cidadeLabel) }}
        placeholder={cidadeId !== null ? `Buscar bairro em ${cidadeLabel}…` : 'Buscar bairro…'} />
      <DataTable columns={columns} rows={data?.data} loading={isLoading} rowKey={(b) => b.id} onRowClick={(b) => { setEdit(b); setEditCidadeLabel(b.cidade) }}
        page={data?.meta.current_page} lastPage={data?.meta.last_page} onPageChange={setPage} fetching={isFetching}
        empty={<EmptyState icon={<MapPin />} title="Nenhum bairro"
          description={cidadeLabel ? `Nenhum bairro cadastrado em ${cidadeLabel}.` : undefined} />} />

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
  const { id: cidadeId, label: cidadeLabel } = useCidadeSelecionada()
  const [q, setQ] = useState(''); const [page, setPage] = useState(1)
  const { data, isLoading, isFetching } = useRuas(q, cidadeId, page)
  const salvar = useSalvarGeo('ruas'); const excluir = useExcluirGeo('ruas')
  const [edit, setEdit] = useState<Partial<Rua> | null>(null)
  const [editCidadeLabel, setEditCidadeLabel] = useState<string | null>(null)
  const [editBairroLabel, setEditBairroLabel] = useState<string | null>(null)
  const [del, setDel] = useState<Rua | null>(null)

  useEffect(() => { setPage(1) }, [cidadeId])

  async function onSalvar() {
    if (!edit?.descricao?.trim() || !edit?.cidade_id) { toast.error('Descrição e cidade são obrigatórios.'); return }
    try {
      await salvar.mutateAsync({
        id: edit.id, descricao: edit.descricao, cidade_id: edit.cidade_id,
        bairro_id: edit.bairro_id ?? null, cep: edit.cep ?? null, ativo: edit.ativo !== 0,
      })
      toast.success('Rua salva.'); setEdit(null)
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao salvar.') }
  }

  const columns: Column<Rua>[] = [
    { key: 'descricao', header: 'Rua', cell: (r) => <span className="font-medium">{r.descricao} {r.ativo === 0 && <Badge variant="secondary">inativa</Badge>}</span> },
    ...(cidadeId === null
      ? [{ key: 'cidade', header: 'Cidade', cell: (r: Rua) => <span className="text-muted-foreground">{r.cidade ?? '—'}</span> } as Column<Rua>]
      : []),
    { key: 'bairro', header: 'Bairro', cell: (r) => <span className="text-muted-foreground">{r.bairro?.descricao ?? '—'}</span> },
    { key: 'cep', header: 'CEP', cell: (r) => <span className="text-muted-foreground tabular-nums">{r.cep || '—'}</span> },
    acoesCol<Rua>((r) => { setEdit(r); setEditCidadeLabel(r.cidade); setEditBairroLabel(r.bairro?.descricao ?? null) }, setDel),
  ]

  return (
    <>
      <Toolbar q={q} setQ={(v) => { setPage(1); setQ(v) }}
        onNovo={() => { setEdit({ ativo: 1, cidade_id: cidadeId ?? undefined }); setEditCidadeLabel(cidadeLabel); setEditBairroLabel(null) }}
        placeholder={cidadeId !== null ? `Buscar rua em ${cidadeLabel}…` : 'Buscar rua…'} />
      <DataTable columns={columns} rows={data?.data} loading={isLoading} rowKey={(r) => r.id}
        onRowClick={(r) => { setEdit(r); setEditCidadeLabel(r.cidade); setEditBairroLabel(r.bairro?.descricao ?? null) }}
        page={data?.meta.current_page} lastPage={data?.meta.last_page} onPageChange={setPage} fetching={isFetching}
        empty={<EmptyState icon={<MapPin />} title="Nenhuma rua"
          description={cidadeLabel ? `Nenhuma rua cadastrada em ${cidadeLabel}.` : undefined} />} />

      <FormDialog open={!!edit} onOpenChange={(o) => !o && setEdit(null)}
        title={edit?.id ? 'Editar rua' : 'Nova rua'} loading={salvar.isPending} onConfirm={onSalvar}>
        <Field label="Descrição" required><Input autoFocus value={edit?.descricao ?? ''} onChange={(e) => setEdit((s) => ({ ...s, descricao: e.target.value }))} /></Field>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <Field label="Cidade" required>
            <AsyncSelect endpoint="/lookups/cidades" value={edit?.cidade_id ?? null} valueLabel={editCidadeLabel}
              onChange={(id, opt) => {
                // Trocar a cidade invalida o bairro: ele pertence à cidade
                // anterior e deixá-lo gravaria uma rua com bairro de outra cidade.
                setEdit((s) => ({ ...s, cidade_id: id ?? undefined, bairro_id: null }))
                setEditCidadeLabel(opt?.label ?? null)
                setEditBairroLabel(null)
              }} />
          </Field>
          <Field label="Bairro" hint={edit?.cidade_id ? undefined : 'Escolha a cidade primeiro.'}>
            <AsyncSelect endpoint="/lookups/bairros" params={{ cidade_id: edit?.cidade_id }}
              disabled={!edit?.cidade_id} value={edit?.bairro_id ?? null} valueLabel={editBairroLabel}
              onChange={(id, opt) => { setEdit((s) => ({ ...s, bairro_id: id })); setEditBairroLabel(opt?.label ?? null) }} />
          </Field>
        </div>
        <Field label="CEP"><Input value={edit?.cep ?? ''} maxLength={9} onChange={(e) => setEdit((s) => ({ ...s, cep: e.target.value }))} /></Field>
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

// =================== INCONSISTÊNCIAS (T4.1) ===================
//
// Fila de prováveis duplicatas de rua/bairro. O detector já existia; o que
// faltava era a AÇÃO de resolver o par — sem ela a tela é um relatório que
// repete os mesmos falsos positivos para sempre, e o operador não tem como
// registrar "conferi, são endereços diferentes".
function InconsistenciasTab() {
  const [tipo, setTipo] = useState<TipoInconsistencia>('todas')
  const { data, isLoading, error } = useInconsistencias(tipo)
  const ignorar = useIgnorarPar()
  const [confirmar, setConfirmar] = useState<ParInconsistente | null>(null)
  const [motivo, setMotivo] = useState('')

  const pares = data?.data ?? []

  async function onIgnorar() {
    if (!confirmar) return
    try {
      await ignorar.mutateAsync({
        tipo: confirmar.tipo,
        item_id: confirmar.a.id,
        item_ignorado_id: confirmar.b.id,
        motivo: motivo.trim() || undefined,
      })
      toast.success('Par marcado como distinto — saiu da fila.')
      setConfirmar(null); setMotivo('')
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Erro ao registrar.')
    }
  }

  const columns: Column<ParInconsistente>[] = [
    {
      key: 'tipo', header: 'Tipo',
      cell: (p) => <Badge variant="secondary">{p.tipo === 'rua' ? 'Rua' : 'Bairro'}</Badge>,
    },
    { key: 'a', header: 'Registro', cell: (p) => <span className="font-medium">{p.a.descricao}</span> },
    { key: 'b', header: 'Possível duplicata', cell: (p) => <span className="font-medium">{p.b.descricao}</span> },
    {
      key: 'similaridade', header: 'Semelhança',
      cell: (p) => (
        <Badge variant={p.similaridade >= 0.95 ? 'destructive' : 'warning'}>
          {Math.round(p.similaridade * 100)}%
        </Badge>
      ),
    },
    {
      key: 'acoes', header: '', className: 'w-px',
      cell: (p) => (
        <Button
          size="sm" variant="outline"
          onClick={(e) => { e.stopPropagation(); setConfirmar(p); setMotivo('') }}
        >
          <CheckCircle2 size={15} /> São distintos
        </Button>
      ),
    },
  ]

  return (
    <>
      <Card className="mb-3 p-3">
        <div className="flex flex-wrap items-center gap-2">
          <span className="text-sm text-muted-foreground">Mostrar:</span>
          {(['todas', 'ruas', 'bairros'] as const).map((t) => (
            <Button key={t} size="sm" variant={tipo === t ? 'default' : 'outline'} onClick={() => setTipo(t)}>
              {t === 'todas' ? 'Todas' : t === 'ruas' ? 'Ruas' : 'Bairros'}
            </Button>
          ))}
          {pares.length > 0 && (
            <span className="ml-auto text-sm text-muted-foreground">
              {pares.length} par(es) a conferir
            </span>
          )}
        </div>
      </Card>

      <AsyncState
        loading={isLoading} error={error} empty={pares.length === 0}
        emptyIcon={<CheckCircle2 />}
        emptyTitle="Nenhuma inconsistência"
        emptyDescription="Todos os pares semelhantes já foram conferidos."
      >
        <DataTable
          columns={columns} rows={pares} rowKey={(p) => `${p.tipo}-${p.a.id}-${p.b.id}`}
          empty={<EmptyState icon={<AlertTriangle />} title="Nenhuma inconsistência" />}
        />
      </AsyncState>

      <ConfirmDialog
        open={!!confirmar}
        onOpenChange={(o) => { if (!o) { setConfirmar(null); setMotivo('') } }}
        title="Marcar como registros distintos?"
        description={
          confirmar
            ? `"${confirmar.a.descricao}" e "${confirmar.b.descricao}" deixarão de aparecer nesta lista.`
            : ''
        }
        confirmLabel="Confirmar"
        loading={ignorar.isPending}
        onConfirm={onIgnorar}
      >
        <Field label="Motivo (opcional)">
          <Input
            autoFocus value={motivo} onChange={(e) => setMotivo(e.target.value)}
            placeholder="Ex.: quadras diferentes, conferido no mapa"
          />
        </Field>
      </ConfirmDialog>
    </>
  )
}
