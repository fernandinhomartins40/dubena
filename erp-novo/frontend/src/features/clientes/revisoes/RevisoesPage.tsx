import { useState } from 'react'
import { Users, Merge, SplitSquareHorizontal, CheckCircle2, ArrowRight } from 'lucide-react'
import {
  PageHeader, Badge, Card, CardContent, EmptyState, AsyncState, Button,
  Tabs, TabsList, TabsTrigger, ConfirmDialog, toast,
} from '@/components/ui'
import { dataHora as fmtDataHora } from '@/lib/format'
import {
  useRevisoes, useConsolidarRevisao, useDescartarRevisao,
  type RevisaoItem, type CadastroRevisao,
} from './api'

/**
 * Fila de cadastros possivelmente duplicados.
 *
 * Cada linha é um par que o motor de identidade achou parecido demais para
 * ignorar e diferente demais para fundir sozinho. A venda que originou o
 * cadastro JÁ ACONTECEU — aqui é só a arrumação da base.
 */
export function RevisoesClientesPage() {
  const [situacao, setSituacao] = useState('pendente')
  const [page, setPage] = useState(1)
  const { data, isLoading, error } = useRevisoes(situacao, page)

  function trocarAba(v: string) {
    setSituacao(v)
    setPage(1)
  }

  return (
    <div>
      <PageHeader
        title="Cadastros a revisar"
        subtitle={
          data
            ? `${data.meta.pendentes} par(es) aguardando decisão`
            : 'Pares de cadastros que podem ser a mesma pessoa'
        }
      />

      <Tabs value={situacao} onValueChange={trocarAba}>
        <TabsList>
          <TabsTrigger value="pendente">Pendentes</TabsTrigger>
          <TabsTrigger value="consolidado">Consolidados</TabsTrigger>
          <TabsTrigger value="descartado">Pessoas diferentes</TabsTrigger>
          <TabsTrigger value="todas">Todos</TabsTrigger>
        </TabsList>
      </Tabs>

      <AsyncState loading={isLoading} error={error} skeletonRows={4}>
        {!data?.data.length ? (
          <EmptyState
            icon={<CheckCircle2 />}
            title={situacao === 'pendente' ? 'Nada a revisar' : 'Nenhum registro'}
            description={
              situacao === 'pendente'
                ? 'Quando um cadastro novo parecer com um já existente, o par aparece aqui.'
                : undefined
            }
          />
        ) : (
          <div className="space-y-4">
            {data.data.map((r) => <ParRevisao key={r.id} revisao={r} />)}

            {data.meta.last_page > 1 && (
              <div className="flex items-center justify-between">
                <span className="text-sm text-muted-foreground">
                  Página {data.meta.current_page} de {data.meta.last_page} · {data.meta.total} pares
                </span>
                <div className="flex gap-2">
                  <Button variant="outline" disabled={page <= 1} onClick={() => setPage(page - 1)}>Anterior</Button>
                  <Button variant="outline" disabled={page >= data.meta.last_page} onClick={() => setPage(page + 1)}>Próxima</Button>
                </div>
              </div>
            )}
          </div>
        )}
      </AsyncState>
    </div>
  )
}

function ParRevisao({ revisao }: { revisao: RevisaoItem }) {
  const consolidar = useConsolidarRevisao()
  const descartar = useDescartarRevisao()
  const [confirmando, setConfirmando] = useState<number | null>(null)

  const pendente = revisao.situacao === 'pendente'
  const a = revisao.cliente
  const b = revisao.candidato

  if (!a || !b) return null

  async function fundir(principalId: number) {
    try {
      await consolidar.mutateAsync({ id: revisao.id, principalId })
      toast.success('Cadastros consolidados. O histórico foi preservado.')
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Não foi possível consolidar.')
    } finally {
      setConfirmando(null)
    }
  }

  async function separar() {
    try {
      await descartar.mutateAsync({ id: revisao.id })
      toast.success('Marcado como pessoas diferentes.')
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Não foi possível descartar.')
    }
  }

  return (
    <Card>
      <CardContent className="space-y-4 pt-4">
        <div className="flex flex-wrap items-center gap-2">
          <Badge variant={revisao.confianca === 'alta' ? 'destructive' : 'secondary'}>
            {revisao.confianca === 'alta' ? 'Muito provável' : 'Possível'} · {revisao.escore} pts
          </Badge>
          {revisao.motivos.map((m) => (
            <span key={m} className="rounded bg-secondary px-2 py-0.5 text-xs text-muted-foreground">{m}</span>
          ))}
          {revisao.origem && (
            <span className="text-xs text-muted-foreground">via {revisao.origem}</span>
          )}
          {!pendente && (
            <span className="ml-auto text-xs text-muted-foreground">
              {revisao.situacao === 'consolidado' ? 'Consolidado' : 'Pessoas diferentes'}
              {revisao.decidido_por && ` por ${revisao.decidido_por}`}
              {revisao.decidido_em && ` · ${fmtDataHora(revisao.decidido_em)}`}
            </span>
          )}
        </div>

        {/* Lado a lado: é a comparação que permite decidir num relance. */}
        <div className="grid gap-3 md:grid-cols-2">
          <Cadastro dados={a} rotulo="Cadastro A" />
          <Cadastro dados={b} rotulo="Cadastro B" />
        </div>

        {pendente && (
          <div className="flex flex-wrap gap-2 border-t pt-3">
            <Button onClick={() => setConfirmando(a.id)} disabled={consolidar.isPending}>
              <Merge size={16} /> É a mesma pessoa — manter A
            </Button>
            <Button variant="outline" onClick={() => setConfirmando(b.id)} disabled={consolidar.isPending}>
              <Merge size={16} /> Manter B
            </Button>
            <Button variant="ghost" onClick={separar} loading={descartar.isPending}>
              <SplitSquareHorizontal size={16} /> São pessoas diferentes
            </Button>
          </div>
        )}
      </CardContent>

      <ConfirmDialog
        open={confirmando !== null}
        onOpenChange={(o) => !o && setConfirmando(null)}
        title="Consolidar cadastros"
        confirmLabel="Consolidar"
        variant="default"
        description={
          <>
            Todo o histórico (pedidos, títulos, telefones) passa para o cadastro
            escolhido. O outro <strong>não é apagado</strong>: fica desativado e
            apontando para ele.
          </>
        }
        loading={consolidar.isPending}
        onConfirm={() => confirmando !== null && fundir(confirmando)}
      />
    </Card>
  )
}

function Cadastro({ dados, rotulo }: { dados: CadastroRevisao; rotulo: string }) {
  return (
    <div className="rounded-md border p-3">
      <div className="mb-2 flex items-center gap-2">
        <span className="text-xs uppercase tracking-wide text-muted-foreground">{rotulo}</span>
        <span className="text-xs text-muted-foreground">#{dados.id}</span>
        {!dados.ativo && <Badge variant="secondary">desativado</Badge>}
      </div>

      <div className="font-medium">{dados.nome}</div>

      <dl className="mt-2 space-y-1 text-sm">
        <Linha rotulo="Documento" valor={dados.documento} />
        <Linha rotulo="Telefone" valor={dados.telefones.join(', ') || null} />
        <Linha rotulo="Endereço" valor={dados.endereco} />
        <Linha rotulo="E-mail" valor={dados.email} />
        <Linha rotulo="Cadastrado" valor={dados.criado_em ? fmtDataHora(dados.criado_em) : null} />
      </dl>
    </div>
  )
}

function Linha({ rotulo, valor }: { rotulo: string; valor: string | null }) {
  return (
    <div className="flex gap-2">
      <dt className="w-24 shrink-0 text-muted-foreground">{rotulo}</dt>
      <dd className="min-w-0 break-words">{valor || '—'}</dd>
    </div>
  )
}

/** Sugestões inline para a tela de cadastro: "já existe alguém parecido". */
export function AvisoSugestoes({
  sugestoes, onEscolher,
}: {
  sugestoes: { cliente_id: number; nome: string; documento: string | null; escore: number; motivos: string[] }[]
  onEscolher?: (id: number) => void
}) {
  if (!sugestoes.length) return null

  return (
    <div className="rounded-md border border-amber-500/40 bg-amber-500/5 p-3">
      <div className="mb-2 flex items-center gap-2 text-sm font-medium">
        <Users size={16} /> Já existe cadastro parecido
      </div>
      <div className="space-y-1">
        {sugestoes.slice(0, 3).map((s) => (
          <button
            key={s.cliente_id}
            type="button"
            onClick={() => onEscolher?.(s.cliente_id)}
            className="flex w-full items-center gap-2 rounded px-2 py-1 text-left text-sm hover:bg-secondary/60"
          >
            <span className="min-w-0 flex-1 truncate">
              <span className="font-medium">{s.nome}</span>
              {s.documento && <span className="text-muted-foreground"> · {s.documento}</span>}
            </span>
            <span className="shrink-0 text-xs text-muted-foreground">{s.motivos[0]}</span>
            {onEscolher && <ArrowRight size={14} className="shrink-0 text-muted-foreground" />}
          </button>
        ))}
      </div>
    </div>
  )
}
