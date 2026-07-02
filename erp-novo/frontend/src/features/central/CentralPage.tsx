import { useEffect, useState } from 'react'
import { Truck, MapPin, Zap, Bike, UserCheck, Ban, Sparkles, Settings2, PackageOpen } from 'lucide-react'
import {
  Button, Card, CardContent, PageHeader, Badge, AsyncState, StatCard,
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogClose,
  Select, SelectTrigger, SelectValue, SelectContent, SelectItem, Field, Input, toast, Can,
} from '@/components/ui'
import { brl, dataHora } from '@/lib/format'
import {
  useFila, useEntregadores, useSugestoes, useConfig,
  useAtribuir, usePriorizar, useBloquear, useDesbloquear, useSalvarConfig,
  type FilaPedido, type EntregadorStatus,
} from './api'

/**
 * Central de Logística (L1/L2/L3). Duas colunas: FILA (pedidos pendentes) e
 * ENTREGADORES (jornada/carga/posição). Atribuição assistida por sugestão (ranking
 * do backend). Config do modo (sugerir/auto). "Quase ao vivo" via polling (o
 * WebSocket entra quando o Reverb for ligado).
 */
export function CentralPage() {
  const [atribuir, setAtribuir] = useState<FilaPedido | null>(null)
  const [config, setConfig] = useState(false)
  const fila = useFila()
  const entregadores = useEntregadores()

  const pedidos = fila.data ?? []
  const lista = entregadores.data ?? []

  return (
    <div>
      <PageHeader
        title="Central de Logística"
        subtitle="Fila de entregas, distribuição e frota em campo"
        action={
          <Can permission="logistica.config">
            <Button variant="outline" onClick={() => setConfig(true)}><Settings2 size={15} className="mr-1" /> Distribuição</Button>
          </Can>
        }
      />

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
        <StatCard titulo="Na fila" valor={String(pedidos.length)} icon={PackageOpen} accent="primary" />
        <StatCard titulo="Em serviço" valor={String(lista.length)} icon={Bike} accent="success" />
        <StatCard titulo="Urgentes" valor={String(pedidos.filter((p) => p.urgente).length)} icon={Zap} accent="destructive" />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {/* Coluna: fila */}
        <div>
          <h3 className="text-sm font-semibold text-muted-foreground mb-2 flex items-center gap-1"><PackageOpen size={15} /> Fila de distribuição</h3>
          <AsyncState loading={fila.isLoading} error={fila.error} empty={pedidos.length === 0}
            emptyTitle="Fila vazia" emptyDescription="Nenhum pedido aguardando distribuição.">
            <div className="space-y-2">
              {pedidos.map((p) => <PedidoCard key={p.id} pedido={p} onAtribuir={() => setAtribuir(p)} />)}
            </div>
          </AsyncState>
        </div>

        {/* Coluna: entregadores */}
        <div>
          <h3 className="text-sm font-semibold text-muted-foreground mb-2 flex items-center gap-1"><Bike size={15} /> Entregadores em campo</h3>
          <AsyncState loading={entregadores.isLoading} error={entregadores.error} empty={lista.length === 0}
            emptyTitle="Ninguém em serviço" emptyDescription="Nenhum entregador iniciou a jornada.">
            <div className="space-y-2">
              {lista.map((e) => <EntregadorCard key={e.entregador_user_id} entregador={e} />)}
            </div>
          </AsyncState>
        </div>
      </div>

      <AtribuirDialog pedido={atribuir} onClose={() => setAtribuir(null)} />
      <ConfigDialog open={config} onClose={() => setConfig(false)} />
    </div>
  )
}

function PedidoCard({ pedido, onAtribuir }: { pedido: FilaPedido; onAtribuir: () => void }) {
  const priorizar = usePriorizar()
  return (
    <Card>
      <CardContent className="p-3">
        <div className="flex items-start justify-between gap-2">
          <div className="min-w-0">
            <div className="flex items-center gap-2">
              <span className="font-semibold">#{pedido.id}</span>
              {pedido.urgente && <Badge variant="destructive">Urgente</Badge>}
              {pedido.entregador && <Badge variant="success">{pedido.entregador.nome}</Badge>}
            </div>
            <p className="text-sm truncate">{pedido.cliente ?? 'Cliente'}</p>
            <p className="text-xs text-muted-foreground truncate flex items-center gap-1"><MapPin size={12} /> {pedido.endereco || 'Endereço não informado'}</p>
            <p className="text-xs text-muted-foreground mt-0.5">{brl(pedido.valor_venda)} · {dataHora(pedido.datahora)}</p>
          </div>
          <Can permission="logistica.distribuir">
            <div className="flex flex-col gap-1 shrink-0">
              <Button size="sm" onClick={onAtribuir}><UserCheck size={14} className="mr-1" /> {pedido.entregador ? 'Trocar' : 'Atribuir'}</Button>
              <Button size="sm" variant="ghost" onClick={() => priorizar.mutate({ pedidoId: pedido.id, urgente: !pedido.urgente })}>
                <Zap size={14} className="mr-1" /> {pedido.urgente ? 'Normal' : 'Priorizar'}
              </Button>
            </div>
          </Can>
        </div>
      </CardContent>
    </Card>
  )
}

function EntregadorCard({ entregador }: { entregador: EntregadorStatus }) {
  const bloquear = useBloquear()
  const desbloquear = useDesbloquear()
  return (
    <Card>
      <CardContent className="p-3 flex items-center justify-between gap-2">
        <div className="min-w-0">
          <p className="font-semibold truncate">{entregador.nome ?? `Entregador #${entregador.entregador_user_id}`}</p>
          <p className="text-xs text-muted-foreground flex items-center gap-1">
            <Truck size={12} /> {entregador.veiculo ? `${entregador.veiculo.placa}` : 'sem veículo'} · {entregador.carga} na carga
          </p>
          {entregador.posicao && <p className="text-[11px] text-muted-foreground">Posição {dataHora(entregador.posicao.em)}</p>}
        </div>
        <div className="flex items-center gap-2 shrink-0">
          {entregador.bloqueado
            ? <Badge variant="destructive">Bloqueado</Badge>
            : <Badge variant="success">Ativo</Badge>}
          <Can permission="logistica.distribuir">
            {entregador.bloqueado
              ? <Button size="sm" variant="ghost" onClick={() => desbloquear.mutate(entregador.entregador_user_id)}>Desbloquear</Button>
              : <Button size="sm" variant="ghost" onClick={() => bloquear.mutate({ entregadorId: entregador.entregador_user_id })}><Ban size={14} /></Button>}
          </Can>
        </div>
      </CardContent>
    </Card>
  )
}

function AtribuirDialog({ pedido, onClose }: { pedido: FilaPedido | null; onClose: () => void }) {
  const sugestoes = useSugestoes(pedido?.id ?? null)
  const atribuir = useAtribuir()
  const lista = sugestoes.data ?? []

  const acao = (entregadorId: number, veiculoId: number | null) => {
    if (!pedido) return
    atribuir.mutate({ pedidoId: pedido.id, entregador_user_id: entregadorId, veiculo_id: veiculoId }, {
      onSuccess: () => { toast.success('Pedido atribuído.'); onClose() },
      onError: () => toast.error('Não foi possível atribuir.'),
    })
  }

  return (
    <Dialog open={pedido !== null} onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader><DialogTitle>Atribuir pedido #{pedido?.id}</DialogTitle></DialogHeader>
        <p className="text-sm text-muted-foreground -mt-1 mb-1 flex items-center gap-1"><Sparkles size={14} /> Sugestões por proximidade e carga</p>
        <AsyncState loading={sugestoes.isLoading} error={sugestoes.error} empty={lista.length === 0}
          emptyTitle="Sem entregadores" emptyDescription="Ninguém em jornada para receber agora.">
          <div className="space-y-2 max-h-80 overflow-auto">
            {lista.map((s, i) => (
              <div key={s.entregador_user_id} className="flex items-center justify-between border rounded-lg p-2">
                <div>
                  <p className="text-sm font-medium flex items-center gap-1">
                    {i === 0 && <Badge variant="success">Melhor</Badge>} {s.nome ?? `#${s.entregador_user_id}`}
                  </p>
                  <p className="text-xs text-muted-foreground">
                    {s.distancia_km != null ? `${s.distancia_km} km · ` : 'sem GPS · '}{s.carga} na carga
                    {!s.elegivel && ' · fora do raio/teto'}
                  </p>
                </div>
                <Button size="sm" disabled={atribuir.isPending} onClick={() => acao(s.entregador_user_id, s.veiculo_id)}>Escolher</Button>
              </div>
            ))}
          </div>
        </AsyncState>
        <DialogFooter><DialogClose asChild><Button variant="outline">Fechar</Button></DialogClose></DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

function ConfigDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
  const config = useConfig()
  const salvar = useSalvarConfig()
  const [modo, setModo] = useState<'sugerir' | 'auto'>('sugerir')
  const [raio, setRaio] = useState('')
  const [teto, setTeto] = useState('')
  const [ociosidade, setOciosidade] = useState('30')

  // Hidrata os campos quando o dialog abre e a config chega.
  useEffect(() => {
    if (open && config.data) {
      setModo(config.data.modo)
      setRaio(config.data.raio_maximo_km ? String(config.data.raio_maximo_km) : '')
      setTeto(config.data.teto_carga ? String(config.data.teto_carga) : '')
      setOciosidade(String(config.data.ociosidade_min ?? 30))
    }
  }, [open, config.data])

  const onSalvar = () => {
    salvar.mutate({
      modo,
      raio_maximo_km: raio ? Number(raio) : null,
      teto_carga: teto ? Number(teto) : null,
      ociosidade_min: ociosidade ? Number(ociosidade) : 30,
    }, {
      onSuccess: () => { toast.success('Configuração salva.'); onClose() },
      onError: () => toast.error('Não foi possível salvar.'),
    })
  }

  return (
    <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader><DialogTitle>Distribuição de entregas</DialogTitle></DialogHeader>
        <div className="space-y-3">
          <Field label="Modo">
            <Select value={modo} onValueChange={(v) => setModo(v as 'sugerir' | 'auto')}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="sugerir">Sugerir (operador confirma)</SelectItem>
                <SelectItem value="auto">Automático (ERP atribui)</SelectItem>
              </SelectContent>
            </Select>
          </Field>
          <div className="grid grid-cols-3 gap-3">
            <Field label="Raio máximo (km)"><Input value={raio} onChange={(e) => setRaio(e.target.value)} inputMode="numeric" placeholder="sem limite" /></Field>
            <Field label="Teto de carga"><Input value={teto} onChange={(e) => setTeto(e.target.value)} inputMode="numeric" placeholder="sem teto" /></Field>
            <Field label="Ociosidade (min)"><Input value={ociosidade} onChange={(e) => setOciosidade(e.target.value)} inputMode="numeric" /></Field>
          </div>
          <p className="text-xs text-muted-foreground">No modo automático, o ERP atribui o entregador mais próximo e menos carregado assim que o pedido entra na fila (respeitando raio e teto). "Ociosidade" é o tempo sem entregas para o motor de missões de campo agir.</p>
        </div>
        <DialogFooter>
          <DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose>
          <Button onClick={onSalvar} disabled={salvar.isPending}>Salvar</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
