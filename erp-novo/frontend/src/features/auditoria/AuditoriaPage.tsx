import { useState } from 'react'
import {
  History, Search, User, ShieldAlert, X, Filter, ChevronRight, ArrowRight,
} from 'lucide-react'
import {
  PageHeader, Badge, Card, CardContent, EmptyState, AsyncState, Button, Input,
  Select, SelectTrigger, SelectValue, SelectContent, SelectItem,
  Tabs, TabsList, TabsTrigger, TabsContent,
} from '@/components/ui'
import { dataHora as fmtDataHora } from '@/lib/format'
import {
  useTrilha, useTrilhaRegistro, useOpcoesTrilha, useBuscarClientes,
  type AcaoTrilha, type FiltrosTrilha, type ClienteBusca,
} from './api'

/**
 * Auditoria interna — quem fez o quê, quando e por quê.
 *
 * Duas visões, porque são duas perguntas diferentes:
 *  - "o que andou acontecendo no sistema?"  → linha do tempo geral;
 *  - "o que aconteceu com ESTE cliente?"    → trilha do cliente, agrupada por
 *    tipo de ação e com a sua própria linha do tempo.
 */
export function AuditoriaPage() {
  const [aba, setAba] = useState('geral')
  const [cliente, setCliente] = useState<ClienteBusca | null>(null)

  /** Abrir um cliente a partir da linha do tempo geral leva para a aba dele. */
  function abrirCliente(c: ClienteBusca) {
    setCliente(c)
    setAba('cliente')
  }

  return (
    <div>
      <PageHeader
        title="Histórico de ações"
        subtitle="Quem fez o quê, quando e por quê — a trilha de auditoria do sistema"
      />

      <Tabs value={aba} onValueChange={setAba}>
        <TabsList>
          <TabsTrigger value="geral">Linha do tempo geral</TabsTrigger>
          <TabsTrigger value="cliente">Por cliente</TabsTrigger>
        </TabsList>

        <TabsContent value="geral">
          <TrilhaGeral onAbrirCliente={abrirCliente} />
        </TabsContent>

        <TabsContent value="cliente">
          <TrilhaCliente cliente={cliente} onSelecionar={setCliente} />
        </TabsContent>
      </Tabs>
    </div>
  )
}

// ─────────────────────────── Linha do tempo geral ───────────────────────────

function TrilhaGeral({ onAbrirCliente }: { onAbrirCliente: (c: ClienteBusca) => void }) {
  const [filtros, setFiltros] = useState<FiltrosTrilha>({})
  const [page, setPage] = useState(1)
  const { data, isLoading, error } = useTrilha(filtros, page)
  const { data: opcoes } = useOpcoesTrilha()

  function definir(chave: keyof FiltrosTrilha, valor: unknown) {
    // 'todas' é o valor sentinela do Select para "sem filtro" — o Radix não
    // aceita item de valor vazio, então a limpeza acontece aqui.
    setFiltros((f) => ({ ...f, [chave]: valor === 'todas' || valor === '' ? undefined : valor }))
    setPage(1)
  }

  const temFiltro = Object.values(filtros).some((v) => v !== undefined && v !== false)

  return (
    <div className="space-y-4">
      <Card>
        <CardContent className="flex flex-wrap items-end gap-3 pt-4">
          <div className="flex items-center gap-2 text-sm text-muted-foreground">
            <Filter size={16} /> Filtros
          </div>

          <Select value={filtros.entidade ?? 'todas'} onValueChange={(v) => definir('entidade', v)}>
            <SelectTrigger className="w-44"><SelectValue placeholder="Tipo de registro" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="todas">Todos os registros</SelectItem>
              {opcoes?.entidades.map((e) => <SelectItem key={e.valor} value={e.valor}>{e.rotulo}</SelectItem>)}
            </SelectContent>
          </Select>

          <Select value={filtros.acao ?? 'todas'} onValueChange={(v) => definir('acao', v)}>
            <SelectTrigger className="w-40"><SelectValue placeholder="Ação" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="todas">Todas as ações</SelectItem>
              {opcoes?.acoes.map((a) => <SelectItem key={a.valor} value={a.valor}>{a.rotulo}</SelectItem>)}
            </SelectContent>
          </Select>

          <Select
            value={filtros.user_id ? String(filtros.user_id) : 'todas'}
            onValueChange={(v) => definir('user_id', v === 'todas' ? undefined : Number(v))}
          >
            <SelectTrigger className="w-44"><SelectValue placeholder="Quem fez" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="todas">Qualquer pessoa</SelectItem>
              {opcoes?.autores.map((a) => <SelectItem key={a.valor} value={String(a.valor)}>{a.rotulo}</SelectItem>)}
            </SelectContent>
          </Select>

          <div className="flex items-center gap-2">
            <Input type="date" className="w-40" value={filtros.inicio ?? ''} onChange={(e) => definir('inicio', e.target.value)} aria-label="Data inicial" />
            <span className="text-muted-foreground text-sm">até</span>
            <Input type="date" className="w-40" value={filtros.fim ?? ''} onChange={(e) => definir('fim', e.target.value)} aria-label="Data final" />
          </div>

          <Button
            variant={filtros.apenas_sensiveis ? 'default' : 'outline'}
            onClick={() => definir('apenas_sensiveis', !filtros.apenas_sensiveis)}
          >
            <ShieldAlert size={16} /> Só decisões
          </Button>

          {temFiltro && (
            <Button variant="ghost" onClick={() => { setFiltros({}); setPage(1) }}>
              <X size={16} /> Limpar
            </Button>
          )}
        </CardContent>
      </Card>

      <AsyncState loading={isLoading} error={error} skeletonRows={5}>
        {!data?.data.length ? (
          <EmptyState
            icon={<History />}
            title="Nenhuma ação registrada"
            description={temFiltro
              ? 'Nenhuma ação corresponde a estes filtros. Tente ampliar o período.'
              : 'As ações dos usuários aparecerão aqui conforme forem acontecendo.'}
          />
        ) : (
          <>
            <LinhaDoTempo acoes={data.data} onAbrirCliente={onAbrirCliente} />
            <Paginacao
              page={data.meta.current_page}
              lastPage={data.meta.last_page}
              total={data.meta.total}
              onChange={setPage}
            />
          </>
        )}
      </AsyncState>
    </div>
  )
}

// ──────────────────────────── Trilha por cliente ────────────────────────────

function TrilhaCliente({
  cliente, onSelecionar,
}: { cliente: ClienteBusca | null; onSelecionar: (c: ClienteBusca | null) => void }) {
  const [busca, setBusca] = useState('')
  const { data: encontrados, isFetching } = useBuscarClientes(busca)
  const { data, isLoading, error } = useTrilhaRegistro(cliente ? 'clientes' : null, cliente?.id ?? null)
  const [acaoFoco, setAcaoFoco] = useState<string | null>(null)

  if (!cliente) {
    return (
      <div className="space-y-4">
        <Card>
          <CardContent className="pt-4">
            <div className="relative">
              <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
              <Input
                className="pl-9"
                autoFocus
                placeholder="Buscar cliente por nome, CPF/CNPJ… (mín. 2 letras)"
                value={busca}
                onChange={(e) => setBusca(e.target.value)}
              />
            </div>

            {busca.trim().length >= 2 && (
              <div className="mt-3 divide-y rounded-md border">
                {isFetching && !encontrados?.length && <div className="p-3 text-sm text-muted-foreground">Buscando…</div>}
                {!isFetching && !encontrados?.length && <div className="p-3 text-sm text-muted-foreground">Nenhum cliente encontrado.</div>}
                {encontrados?.map((c) => (
                  <button
                    key={c.id}
                    className="flex w-full items-center justify-between p-3 text-left hover:bg-secondary/60"
                    onClick={() => { onSelecionar(c); setBusca('') }}
                  >
                    <span className="flex min-w-0 items-center gap-2">
                      <span className="truncate font-medium">{c.nome}</span>
                      {/* Desativado aparece na busca de propósito: é sobre ele
                          que mais se pergunta "quem tirou da lista e por quê". */}
                      {!c.ativo && <Badge variant="secondary">desativado</Badge>}
                    </span>
                    <span className="flex shrink-0 items-center gap-3 text-sm text-muted-foreground tabular-nums">
                      {c.documento || '—'} <ChevronRight size={16} />
                    </span>
                  </button>
                ))}
              </div>
            )}
          </CardContent>
        </Card>

        <EmptyState
          icon={<User />}
          title="Escolha um cliente"
          description="Busque acima para ver tudo o que foi feito no cadastro dele, agrupado por tipo de ação."
        />
      </div>
    )
  }

  const acoes = data?.data ?? []
  const visiveis = acaoFoco ? acoes.filter((a) => a.acao === acaoFoco) : acoes

  return (
    <div className="space-y-4">
      <Card>
        <CardContent className="flex flex-wrap items-center justify-between gap-3 pt-4">
          <div className="min-w-0">
            <div className="flex items-center gap-2">
              <span className="truncate text-lg font-medium">{cliente.nome}</span>
              {!cliente.ativo && <Badge variant="secondary">desativado</Badge>}
            </div>
            <div className="text-sm text-muted-foreground tabular-nums">
              {cliente.documento || 'sem documento'} · código {cliente.id}
            </div>
          </div>
          <Button variant="outline" onClick={() => { onSelecionar(null); setAcaoFoco(null) }}>
            <X size={16} /> Trocar de cliente
          </Button>
        </CardContent>
      </Card>

      <AsyncState loading={isLoading} error={error} skeletonRows={5}>
        {!acoes.length ? (
          <EmptyState
            icon={<History />}
            title="Sem histórico para este cliente"
            description="Nenhuma ação foi registrada neste cadastro ainda."
          />
        ) : (
          <>
            {/* Agrupamento por tipo de ação: clicar filtra a linha do tempo. */}
            <div className="flex flex-wrap gap-2">
              <BotaoResumo ativo={acaoFoco === null} onClick={() => setAcaoFoco(null)}
                rotulo="Tudo" total={data?.meta.total ?? acoes.length} />
              {data?.resumo.map((r) => (
                <BotaoResumo
                  key={r.acao}
                  ativo={acaoFoco === r.acao}
                  onClick={() => setAcaoFoco(acaoFoco === r.acao ? null : r.acao)}
                  rotulo={r.rotulo}
                  total={r.total}
                  sensivel={r.sensivel}
                />
              ))}
            </div>

            <LinhaDoTempo acoes={visiveis} />
          </>
        )}
      </AsyncState>
    </div>
  )
}

function BotaoResumo({
  rotulo, total, ativo, sensivel, onClick,
}: { rotulo: string; total: number; ativo: boolean; sensivel?: boolean; onClick: () => void }) {
  return (
    <button
      onClick={onClick}
      className={[
        'flex items-center gap-2 rounded-md border px-3 py-2 text-sm transition-colors',
        ativo ? 'border-primary bg-primary/10 font-medium' : 'hover:bg-secondary/60',
      ].join(' ')}
    >
      {sensivel && <ShieldAlert size={14} className="text-destructive" />}
      {rotulo}
      <span className="rounded bg-secondary px-1.5 text-xs tabular-nums text-muted-foreground">{total}</span>
    </button>
  )
}

// ────────────────────────────── Linha do tempo ──────────────────────────────

function LinhaDoTempo({
  acoes, onAbrirCliente,
}: { acoes: AcaoTrilha[]; onAbrirCliente?: (c: ClienteBusca) => void }) {
  return (
    <ol className="relative space-y-0 border-l pl-6 ml-2">
      {acoes.map((a) => <ItemTrilha key={a.id} acao={a} onAbrirCliente={onAbrirCliente} />)}
    </ol>
  )
}

function ItemTrilha({
  acao, onAbrirCliente,
}: { acao: AcaoTrilha; onAbrirCliente?: (c: ClienteBusca) => void }) {
  const [aberto, setAberto] = useState(false)
  const temDetalhe = acao.alteracoes.length > 0

  return (
    <li className="relative py-3">
      {/* Marcador na linha; decisão sensível ganha cor para saltar na varredura. */}
      <span
        className={[
          'absolute -left-[31px] top-5 size-3 rounded-full ring-4 ring-background',
          acao.sensivel ? 'bg-destructive' : 'bg-muted-foreground/40',
        ].join(' ')}
      />

      <div className="rounded-md border p-3">
        <div className="flex flex-wrap items-baseline gap-x-2 gap-y-1">
          <span className="font-medium">{acao.autor ?? 'Sistema'}</span>
          <span className={acao.sensivel ? 'font-medium text-destructive' : 'text-muted-foreground'}>
            {acao.acao_rotulo.toLowerCase()}
          </span>
          <span className="text-muted-foreground">{acao.entidade_rotulo.toLowerCase()}</span>

          {acao.alvo && (
            onAbrirCliente && acao.entidade === 'clientes' && acao.entidade_id ? (
              <button
                className="font-medium underline-offset-4 hover:underline"
                onClick={() => onAbrirCliente({ id: acao.entidade_id!, nome: acao.alvo!, documento: null, ativo: true })}
              >
                {acao.alvo}
              </button>
            ) : <span className="font-medium">{acao.alvo}</span>
          )}

          <span className="ml-auto text-sm text-muted-foreground tabular-nums">{fmtDataHora(acao.criado_em)}</span>
        </div>

        {acao.motivo && (
          <div className="mt-2 rounded bg-secondary/60 px-2 py-1 text-sm">
            <span className="text-muted-foreground">Motivo: </span>{acao.motivo}
          </div>
        )}

        {temDetalhe && (
          <>
            <button
              className="mt-2 text-sm text-muted-foreground underline-offset-4 hover:underline"
              onClick={() => setAberto((v) => !v)}
            >
              {aberto ? 'Ocultar' : `Ver o que mudou (${acao.alteracoes.length})`}
            </button>

            {aberto && (
              <div className="mt-2 space-y-1 border-t pt-2">
                {acao.alteracoes.map((m) => (
                  <div key={m.campo} className="flex flex-wrap items-center gap-2 text-sm">
                    <span className="text-muted-foreground">{m.rotulo}:</span>
                    <span className="line-through opacity-60">{m.de}</span>
                    <ArrowRight size={12} className="text-muted-foreground" />
                    <span className="font-medium">{m.para}</span>
                  </div>
                ))}
                {acao.ip && <div className="pt-1 text-xs text-muted-foreground">IP {acao.ip}</div>}
              </div>
            )}
          </>
        )}
      </div>
    </li>
  )
}

function Paginacao({
  page, lastPage, total, onChange,
}: { page: number; lastPage: number; total: number; onChange: (p: number) => void }) {
  if (lastPage <= 1) return <div className="text-sm text-muted-foreground">{total} ação(ões)</div>

  return (
    <div className="flex items-center justify-between">
      <span className="text-sm text-muted-foreground">
        Página {page} de {lastPage} · {total.toLocaleString('pt-BR')} ações
      </span>
      <div className="flex gap-2">
        <Button variant="outline" disabled={page <= 1} onClick={() => onChange(page - 1)}>Anterior</Button>
        <Button variant="outline" disabled={page >= lastPage} onClick={() => onChange(page + 1)}>Próxima</Button>
      </div>
    </div>
  )
}
