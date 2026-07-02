import { useEffect, useState } from 'react'
import { CheckCircle2, XCircle, Eye, Plus, Compass, Clock3, ImageIcon } from 'lucide-react'
import {
  Button, Card, CardContent, PageHeader, Badge, AsyncState, StatCard,
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogClose,
  Select, SelectTrigger, SelectValue, SelectContent, SelectItem, Field, Input, Textarea,
  Tabs, TabsList, TabsTrigger, TabsContent, toast, Can, CheckboxField,
} from '@/components/ui'
import { dataHora } from '@/lib/format'
import {
  useMissoes, useAtribuicoes, useAtribuicaoDetalhe, useSalvarMissao, useAuditar, useDecidirAdiamento,
  carregarEvidencia, TIPOS_MISSAO, type Atribuicao, type Missao,
} from './api'

const tipoLabel = (t: string | null) => TIPOS_MISSAO.find((x) => x.valor === t)?.label ?? t ?? '—'

/**
 * Missões de campo (L7/L9) — o operador cria os moldes, acompanha as execuções
 * (métricas/visitas/evidências/trilha), aprova/reprova com sanção e decide
 * adiamentos (ETAPA 11).
 */
export function MissoesPage() {
  const [detalhe, setDetalhe] = useState<number | null>(null)
  const [novaMissao, setNovaMissao] = useState(false)
  const atribuicoes = useAtribuicoes()
  const missoes = useMissoes()

  const lista = atribuicoes.data ?? []
  const pendentesAuditoria = lista.filter((a) => a.status === 'concluida' && !a.auditoria)
  const adiamentosPendentes = lista.filter((a) => a.adiamento?.aprovacao === 'pendente')

  return (
    <div>
      <PageHeader
        title="Missões de campo"
        subtitle="Panfletagem, prospecção e vendas quando a entrega está ociosa"
        action={
          <Can permission="missao.create">
            <Button onClick={() => setNovaMissao(true)}><Plus size={15} className="mr-1" /> Nova missão</Button>
          </Can>
        }
      />

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
        <StatCard titulo="Em execução" valor={String(lista.filter((a) => a.status === 'em_andamento').length)} icon={Compass} accent="primary" />
        <StatCard titulo="Aguardando revisão" valor={String(pendentesAuditoria.length)} icon={Eye} accent="lime" />
        <StatCard titulo="Adiamentos pendentes" valor={String(adiamentosPendentes.length)} icon={Clock3} accent="destructive" />
      </div>

      <Tabs defaultValue="execucoes">
        <TabsList>
          <TabsTrigger value="execucoes">Execuções</TabsTrigger>
          <TabsTrigger value="moldes">Missões cadastradas</TabsTrigger>
        </TabsList>

        <TabsContent value="execucoes">
          <AsyncState loading={atribuicoes.isLoading} error={atribuicoes.error} empty={lista.length === 0}
            emptyTitle="Nenhuma execução" emptyDescription="As missões atribuídas aos entregadores aparecem aqui.">
            <div className="space-y-2">
              {lista.map((a) => <AtribuicaoCard key={a.id} atr={a} onVer={() => setDetalhe(a.id)} />)}
            </div>
          </AsyncState>
        </TabsContent>

        <TabsContent value="moldes">
          <AsyncState loading={missoes.isLoading} error={missoes.error} empty={(missoes.data ?? []).length === 0}
            emptyTitle="Nenhuma missão" emptyDescription="Crie a primeira missão para o motor de ociosidade usar.">
            <div className="space-y-2">
              {(missoes.data ?? []).map((m) => (
                <Card key={m.id}>
                  <CardContent className="p-3 flex items-center justify-between gap-2">
                    <div className="min-w-0">
                      <p className="font-semibold truncate">{m.titulo}</p>
                      <p className="text-xs text-muted-foreground">
                        {tipoLabel(m.tipo)}{m.meta_visitas ? ` · meta ${m.meta_visitas} visitas` : ''}{m.exige_foto ? ' · exige foto' : ''} · {m.atribuicoes_count ?? 0} execução(ões)
                      </p>
                    </div>
                    {m.ativo ? <Badge variant="success">Ativa</Badge> : <Badge variant="destructive">Inativa</Badge>}
                  </CardContent>
                </Card>
              ))}
            </div>
          </AsyncState>
        </TabsContent>
      </Tabs>

      <DetalheDialog id={detalhe} onClose={() => setDetalhe(null)} />
      <NovaMissaoDialog open={novaMissao} onClose={() => setNovaMissao(false)} />
    </div>
  )
}

function statusBadge(a: Atribuicao) {
  if (a.auditoria?.resultado === 'aprovada') return <Badge variant="success">Aprovada</Badge>
  if (a.auditoria?.resultado === 'reprovada') return <Badge variant="destructive">Reprovada</Badge>
  if (a.auditoria?.resultado === 'revisao') return <Badge variant="warning">Em revisão</Badge>
  if (a.status === 'concluida') return <Badge variant="warning">Aguardando revisão</Badge>
  if (a.status === 'em_andamento') return <Badge variant="success">Em andamento</Badge>
  if (a.status === 'adiada') return <Badge variant="destructive">Adiada</Badge>
  return <Badge>{a.status}</Badge>
}

function AtribuicaoCard({ atr, onVer }: { atr: Atribuicao; onVer: () => void }) {
  const decidir = useDecidirAdiamento()
  return (
    <Card>
      <CardContent className="p-3">
        <div className="flex items-start justify-between gap-2">
          <div className="min-w-0">
            <div className="flex items-center gap-2">
              <span className="font-semibold">#{atr.id}</span>
              {statusBadge(atr)}
              {atr.automatica && <Badge>Auto</Badge>}
            </div>
            <p className="text-sm truncate">{atr.missao ?? '—'} · {tipoLabel(atr.tipo)}</p>
            <p className="text-xs text-muted-foreground">
              {atr.entregador ?? '—'} · {atr.visitas} visita(s) · {dataHora(atr.iniciada_em)}
            </p>
            {atr.adiamento?.aprovacao === 'pendente' && (
              <p className="text-xs text-amber-600 mt-1">Adiamento: {atr.adiamento.motivo}{atr.adiamento.detalhe ? ` — ${atr.adiamento.detalhe}` : ''}</p>
            )}
          </div>
          <div className="flex flex-col gap-1 shrink-0">
            <Button size="sm" variant="outline" onClick={onVer}><Eye size={14} className="mr-1" /> Detalhe</Button>
            {atr.adiamento?.aprovacao === 'pendente' && (
              <Can permission="missao.aprovar">
                <div className="flex gap-1">
                  <Button size="sm" variant="ghost" onClick={() => decidir.mutate({ id: atr.id, decisao: 'aprovado' })}><CheckCircle2 size={14} /></Button>
                  <Button size="sm" variant="ghost" onClick={() => decidir.mutate({ id: atr.id, decisao: 'reprovado' })}><XCircle size={14} /></Button>
                </div>
              </Can>
            )}
          </div>
        </div>
      </CardContent>
    </Card>
  )
}

function Evidencia({ id }: { id: number }) {
  const [url, setUrl] = useState<string | null>(null)
  useEffect(() => {
    let ativo = true
    carregarEvidencia(id).then((u) => ativo && setUrl(u)).catch(() => {})
    return () => { ativo = false; if (url) URL.revokeObjectURL(url) }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id])
  if (!url) return <div className="size-16 rounded bg-muted grid place-items-center"><ImageIcon size={16} className="text-muted-foreground" /></div>
  return <a href={url} target="_blank" rel="noreferrer"><img src={url} alt="Evidência" className="size-16 rounded object-cover border" /></a>
}

function DetalheDialog({ id, onClose }: { id: number | null; onClose: () => void }) {
  const detalhe = useAtribuicaoDetalhe(id)
  const auditar = useAuditar()
  const [sancao, setSancao] = useState('nenhuma')
  const [obs, setObs] = useState('')

  const decidir = (resultado: 'aprovada' | 'reprovada' | 'revisao') => {
    if (!id) return
    auditar.mutate({ id, resultado, sancao, observacao: obs || undefined }, {
      onSuccess: () => { toast.success('Auditoria registrada.'); onClose() },
      onError: () => toast.error('Não foi possível registrar.'),
    })
  }

  const d = detalhe.data

  return (
    <Dialog open={id !== null} onOpenChange={(o) => !o && onClose()}>
      <DialogContent className="max-w-2xl">
        <DialogHeader><DialogTitle>Execução #{id} — {d?.missao.titulo ?? ''}</DialogTitle></DialogHeader>
        <AsyncState loading={detalhe.isLoading} error={detalhe.error} empty={!d}>
          {d && (
            <div className="space-y-3 max-h-[65vh] overflow-auto pr-1">
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center">
                <MetricBox label="Visitas" valor={d.metricas.visitas_total} />
                <MetricBox label="Vendas" valor={d.metricas.vendas} />
                <MetricBox label="Percurso" valor={`${d.metricas.distancia_km} km`} />
                <MetricBox label="Duração" valor={d.metricas.duracao_min != null ? `${d.metricas.duracao_min} min` : '—'} />
              </div>

              <div>
                <h4 className="text-sm font-semibold mb-1">Visitas ({d.visitas.length})</h4>
                <div className="space-y-2">
                  {d.visitas.map((v) => (
                    <div key={v.id} className="flex items-center gap-3 border rounded-lg p-2">
                      <div className="flex-1 min-w-0">
                        <p className="text-sm">
                          <Badge variant={v.status === 'venda' ? 'success' : v.status === 'frustrada' ? 'destructive' : undefined}>{v.status}</Badge>
                          {' '}{v.cliente ?? 'Residência'} {v.pedido_id ? `· pedido #${v.pedido_id}` : ''}
                        </p>
                        <p className="text-xs text-muted-foreground">{dataHora(v.em)}{v.observacao ? ` — ${v.observacao}` : ''}</p>
                      </div>
                      <div className="flex gap-1">
                        {v.evidencias.map((e) => <Evidencia key={e.id} id={e.id} />)}
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              <Can permission="missao.aprovar">
                <div className="border-t pt-3 space-y-2">
                  <h4 className="text-sm font-semibold">Decisão da auditoria</h4>
                  <div className="grid grid-cols-2 gap-2">
                    <Field label="Sanção">
                      <Select value={sancao} onValueChange={setSancao}>
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                          <SelectItem value="nenhuma">Nenhuma</SelectItem>
                          <SelectItem value="bonificacao">Bonificação</SelectItem>
                          <SelectItem value="advertencia">Advertência</SelectItem>
                        </SelectContent>
                      </Select>
                    </Field>
                    <Field label="Observação"><Input value={obs} onChange={(e) => setObs(e.target.value)} /></Field>
                  </div>
                  <div className="flex gap-2">
                    <Button size="sm" onClick={() => decidir('aprovada')} disabled={auditar.isPending}><CheckCircle2 size={14} className="mr-1" /> Aprovar</Button>
                    <Button size="sm" variant="outline" onClick={() => decidir('revisao')} disabled={auditar.isPending}>Pedir revisão</Button>
                    <Button size="sm" variant="destructive" onClick={() => decidir('reprovada')} disabled={auditar.isPending}><XCircle size={14} className="mr-1" /> Reprovar</Button>
                  </div>
                </div>
              </Can>
            </div>
          )}
        </AsyncState>
        <DialogFooter><DialogClose asChild><Button variant="outline">Fechar</Button></DialogClose></DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

function MetricBox({ label, valor }: { label: string; valor: number | string }) {
  return (
    <div className="border rounded-lg p-2">
      <p className="text-lg font-bold">{valor}</p>
      <p className="text-[11px] text-muted-foreground">{label}</p>
    </div>
  )
}

function NovaMissaoDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
  const salvar = useSalvarMissao()
  const [tipo, setTipo] = useState('prospeccao')
  const [titulo, setTitulo] = useState('')
  const [descricao, setDescricao] = useState('')
  const [meta, setMeta] = useState('')
  const [exigeFoto, setExigeFoto] = useState(true)

  const onSalvar = () => {
    if (!titulo.trim()) { toast.error('Informe o título.'); return }
    salvar.mutate({
      tipo, titulo: titulo.trim(), descricao: descricao || null,
      meta_visitas: meta ? Number(meta) : null, exige_foto: exigeFoto, ativo: true,
    } as Partial<Missao>, {
      onSuccess: () => { toast.success('Missão criada.'); onClose() },
      onError: () => toast.error('Não foi possível criar.'),
    })
  }

  return (
    <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader><DialogTitle>Nova missão de campo</DialogTitle></DialogHeader>
        <div className="space-y-3">
          <Field label="Tipo">
            <Select value={tipo} onValueChange={setTipo}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                {TIPOS_MISSAO.map((t) => <SelectItem key={t.valor} value={t.valor}>{t.label}</SelectItem>)}
              </SelectContent>
            </Select>
          </Field>
          <Field label="Título" required><Input value={titulo} onChange={(e) => setTitulo(e.target.value)} placeholder="Ex.: Panfletagem bairro Santana" /></Field>
          <Field label="Instruções"><Textarea value={descricao} onChange={(e) => setDescricao(e.target.value)} rows={3} /></Field>
          <div className="grid grid-cols-2 gap-3 items-end">
            <Field label="Meta de visitas"><Input value={meta} onChange={(e) => setMeta(e.target.value)} inputMode="numeric" placeholder="sem meta" /></Field>
            <CheckboxField label="Exigir foto por visita" checked={exigeFoto} onChange={setExigeFoto} />
          </div>
          <p className="text-xs text-muted-foreground">O motor de ociosidade atribui a missão automaticamente aos entregadores em jornada sem entregas (configure os minutos na Central de Logística).</p>
        </div>
        <DialogFooter>
          <DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose>
          <Button onClick={onSalvar} disabled={salvar.isPending}>Criar missão</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
