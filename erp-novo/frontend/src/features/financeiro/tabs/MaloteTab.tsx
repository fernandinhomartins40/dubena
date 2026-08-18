import { useState } from 'react'
import { Briefcase, CheckCircle2, Search } from 'lucide-react'
import {
  Button, Card, CardContent, Badge, DataTable, type Column, EmptyState, Field, Input,
  AsyncSelect, ConfirmDialog, toast,
} from '@/components/ui'
import { useConferenciaMalote, useFecharMalote, type MalotePedido } from '../api'
import { brl } from '@/lib/format'

const hoje = new Date().toISOString().slice(0, 10)

/**
 * Fechamento de malote (T4.3) — o acerto de valores do entregador.
 *
 * ⚠️ Condicionado à decisão do dono: o plano exige confirmar se o acerto físico
 * ainda acontece na operação.
 *
 * O fluxo é: filtrar o turno → conferir a lista contra o que o entregador
 * trouxe → fechar, o que baixa as parcelas na conta de malote.
 */
export function MaloteTab() {
  const [inicio, setInicio] = useState(hoje)
  const [fim, setFim] = useState(hoje)
  const [setorId, setSetorId] = useState<number | null>(null)
  const [setorLabel, setSetorLabel] = useState<string | null>(null)
  const [entregadorId, setEntregadorId] = useState<number | null>(null)
  const [entregadorLabel, setEntregadorLabel] = useState<string | null>(null)
  const [run, setRun] = useState(false)
  const [selecionados, setSelecionados] = useState<Set<number>>(new Set())
  const [confirmando, setConfirmando] = useState(false)

  const { data, isLoading, refetch } = useConferenciaMalote(
    { inicio, fim, setor_id: setorId, entregador_user_id: entregadorId }, run,
  )
  const fechar = useFecharMalote()

  // Só o que ainda tem parcela em aberto pode ser fechado — o resto já foi
  // acertado num malote anterior.
  const fechaveis = (data?.pedidos ?? []).filter((p) => !p.ja_baixado)
  const totalSelecionado = fechaveis
    .filter((p) => selecionados.has(p.pedido_id))
    .reduce((s, p) => s + p.valor_a_baixar, 0)

  function alternar(id: number) {
    setSelecionados((atual) => {
      const novo = new Set(atual)
      novo.has(id) ? novo.delete(id) : novo.add(id)
      return novo
    })
  }

  function alternarTodos() {
    setSelecionados((atual) =>
      atual.size === fechaveis.length ? new Set() : new Set(fechaveis.map((p) => p.pedido_id)),
    )
  }

  async function onFechar() {
    try {
      const r = await fechar.mutateAsync({ pedidos: [...selecionados] })
      const ignorados = r.ignorados.length ? ` (${r.ignorados.length} já estavam baixados)` : ''
      toast.success(`${r.baixadas} parcela(s) baixada(s) — ${brl(r.valor)}${ignorados}.`)
      setSelecionados(new Set())
      refetch()
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Erro ao fechar o malote.')
    } finally {
      setConfirmando(false)
    }
  }

  const columns: Column<MalotePedido>[] = [
    {
      key: 'sel', header: '', width: 'w-10',
      cell: (p) => p.ja_baixado
        ? null
        : <input type="checkbox" className="size-4 accent-primary"
            checked={selecionados.has(p.pedido_id)}
            onChange={() => alternar(p.pedido_id)}
            onClick={(e) => e.stopPropagation()} />,
    },
    { key: 'pedido', header: 'Pedido', width: 'w-24', cell: (p) => <span className="tabular-nums">{p.pedido_id}</span> },
    { key: 'cliente', header: 'Cliente', cell: (p) => <span className="truncate">{p.cliente || '—'}</span> },
    { key: 'condicao', header: 'Condição', cell: (p) => p.condicao },
    { key: 'situacao', header: 'Situação', cell: (p) => p.situacao || '—' },
    { key: 'valor', header: 'Valor', align: 'right', cell: (p) => <span className="tabular-nums">{brl(p.valor)}</span> },
    {
      key: 'baixar', header: 'A baixar', align: 'right',
      cell: (p) => p.ja_baixado
        ? <Badge variant="secondary">baixado</Badge>
        : <span className="tabular-nums font-medium">{brl(p.valor_a_baixar)}</span>,
    },
  ]

  return (
    <>
      <Card className="mb-4"><CardContent className="pt-6 flex flex-wrap items-end gap-3">
        <Field label="Início"><Input type="date" value={inicio} onChange={(e) => setInicio(e.target.value)} /></Field>
        <Field label="Fim"><Input type="date" value={fim} onChange={(e) => setFim(e.target.value)} /></Field>
        <div className="w-48">
          <Field label="Setor">
            <AsyncSelect endpoint="/lookups/setores" value={setorId} valueLabel={setorLabel}
              onChange={(id, o) => { setSetorId(id); setSetorLabel(o?.label ?? null) }} />
          </Field>
        </div>
        <div className="w-56">
          <Field label="Entregador">
            <AsyncSelect endpoint="/lookups/usuarios" value={entregadorId} valueLabel={entregadorLabel}
              onChange={(id, o) => { setEntregadorId(id); setEntregadorLabel(o?.label ?? null) }} />
          </Field>
        </div>
        <Button onClick={() => { setSelecionados(new Set()); setRun(true); refetch() }}>
          <Search size={16} /> Conferir
        </Button>
      </CardContent></Card>

      {run && data && (
        <>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
            <Card><CardContent className="pt-6">
              <p className="text-sm text-muted-foreground">Pedidos</p>
              <p className="mt-1 text-xl font-bold tabular-nums">{data.totais.pedidos}</p>
            </CardContent></Card>
            <Card><CardContent className="pt-6">
              <p className="text-sm text-muted-foreground">Valor total</p>
              <p className="mt-1 text-xl font-bold tabular-nums">{brl(data.totais.valor_total)}</p>
            </CardContent></Card>
            <Card><CardContent className="pt-6">
              <p className="text-sm text-muted-foreground">A baixar</p>
              <p className="mt-1 text-xl font-bold tabular-nums text-success">{brl(data.totais.valor_a_baixar)}</p>
            </CardContent></Card>
          </div>

          {data.por_condicao.length > 0 && (
            <Card className="mb-4"><CardContent className="pt-6">
              <p className="text-sm font-medium mb-2">Por condição de pagamento</p>
              <div className="flex flex-wrap gap-2">
                {data.por_condicao.map((c) => (
                  <Badge key={c.condicao_id} variant="secondary">
                    {c.condicao}: {c.pedidos} · {brl(c.valor)}
                  </Badge>
                ))}
              </div>
            </CardContent></Card>
          )}

          <div className="mb-3 flex items-center justify-between gap-3">
            <Button variant="outline" size="sm" onClick={alternarTodos} disabled={fechaveis.length === 0}>
              {selecionados.size === fechaveis.length && fechaveis.length > 0 ? 'Limpar seleção' : 'Selecionar todos'}
            </Button>
            <Button disabled={selecionados.size === 0} onClick={() => setConfirmando(true)}>
              <CheckCircle2 size={16} /> Fechar malote ({selecionados.size}) — {brl(totalSelecionado)}
            </Button>
          </div>

          <DataTable columns={columns} rows={data.pedidos} loading={isLoading}
            rowKey={(p) => p.pedido_id}
            empty={<EmptyState icon={<Briefcase />} title="Nenhum pedido no período" />} />
        </>
      )}

      <ConfirmDialog open={confirmando} onOpenChange={setConfirmando} title="Fechar malote"
        description={<>Baixar <strong>{brl(totalSelecionado)}</strong> em {selecionados.size} pedido(s)
          na conta de malote? As parcelas passam a constar como recebidas.</>}
        loading={fechar.isPending} onConfirm={onFechar} />
    </>
  )
}
