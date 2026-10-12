import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { AlertTriangle, ArrowRight, Banknote, CalendarClock, ClipboardList, PackageCheck, PackageMinus, Plus, ShoppingCart, UserPlus, Wallet } from 'lucide-react'
import type { ReactNode } from 'react'
import { api } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { AsyncState } from '@/components/ui'
import { brl } from '@/lib/format'

/** Cada bloco só existe se a pessoa tem o módulo — ausente não é zero. */
interface Hoje {
  pedidos?: { em_aberto: number; mais_antigo: string | null; hoje: number; hoje_valor: number }
  financeiro?: Record<'receber_hoje' | 'receber_vencido' | 'pagar_hoje', { quantidade: number; valor: number }>
  estoque_abaixo_minimo?: number
  comodatos_vencidos?: number
  cadastros_a_revisar?: number
}

/** "há 2 h", "há 3 dias" — leigo entende tempo, não carimbo de data. */
export function haQuanto(iso: string | null, agora = Date.now()): string | null {
  if (!iso) return null
  const min = Math.max(0, Math.round((agora - new Date(iso).getTime()) / 60000))
  if (min < 60) return `há ${min} min`
  const h = Math.round(min / 60)
  if (h < 24) return `há ${h} h`
  const d = Math.round(h / 24)
  return `há ${d} ${d === 1 ? 'dia' : 'dias'}`
}

/**
 * As três tarefas que mais acontecem, a um clique de qualquer começo de dia.
 * "Novo pedido" abre o diálogo direto (`?novo=1`), sem passar pela lista.
 */
export function AcoesRapidas() {
  const { can } = useAuth()
  const acoes = [
    { rotulo: 'Novo pedido', detalhe: 'Cliente liga ou chega no balcão', to: '/pedidos?novo=1', permission: 'pedido.create', icon: ShoppingCart },
    { rotulo: 'Novo cliente', detalhe: 'Cadastrar quem ainda não compra', to: '/clientes/novo', permission: 'cliente.create', icon: UserPlus },
    { rotulo: 'Contas a receber e pagar', detalhe: 'Dar baixa, conferir vencimentos', to: '/financeiro', permission: 'financeiro.view', icon: Wallet },
  ].filter((a) => can(a.permission))
  if (acoes.length === 0) return null
  return (
    <section aria-labelledby="acoes-titulo">
      <h2 id="acoes-titulo" className="mb-3 text-lg font-semibold">O que você quer fazer?</h2>
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {acoes.map(({ icon: Icon, ...a }) => (
          <Link key={a.to} to={a.to}
            className="group flex items-center gap-4 rounded-xl border-2 border-transparent bg-primary/10 p-4 transition-colors hover:border-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
            <span className="grid size-11 shrink-0 place-items-center rounded-lg bg-primary text-primary-foreground"><Icon size={20} /></span>
            <span className="flex-1">
              <span className="flex items-center gap-1 font-semibold"><Plus size={14} aria-hidden className={a.rotulo.startsWith('Novo') ? '' : 'hidden'} />{a.rotulo}</span>
              <span className="block text-sm text-muted-foreground">{a.detalhe}</span>
            </span>
            <ArrowRight size={16} className="text-muted-foreground transition-transform group-hover:translate-x-0.5" aria-hidden />
          </Link>
        ))}
      </div>
    </section>
  )
}

/**
 * "Para hoje": o que pede atenção agora. Cada cartão diz o fato e leva ao
 * lugar onde se resolve. Cartão em destaque = há algo a fazer; tranquilo =
 * zerado, mas visível (saber que está tudo em dia também é informação).
 */
export function ParaHoje() {
  const q = useQuery<Hoje>({
    queryKey: ['dashboard-hoje'],
    queryFn: async () => (await api.get('/dashboard/hoje')).data.data,
    // A atendente deixa a tela aberta: atualiza sozinha, sem piscar.
    refetchInterval: 60_000,
  })
  const d = q.data
  const cartoes: { chave: string; titulo: string; valor: string; detalhe?: string | null; atencao: boolean; to: string; icon: ReactNode }[] = []

  if (d?.pedidos) {
    cartoes.push({
      chave: 'abertos', titulo: 'Pedidos esperando', valor: String(d.pedidos.em_aberto), to: '/pedidos',
      detalhe: d.pedidos.em_aberto > 0 ? `O mais antigo chegou ${haQuanto(d.pedidos.mais_antigo)}` : 'Nenhum pedido parado',
      atencao: d.pedidos.em_aberto > 0, icon: <ShoppingCart size={18} />,
    })
    cartoes.push({
      chave: 'vendas', titulo: 'Vendas de hoje', valor: brl(d.pedidos.hoje_valor), to: '/pedidos?tab=lista',
      detalhe: `${d.pedidos.hoje} ${d.pedidos.hoje === 1 ? 'pedido' : 'pedidos'}`, atencao: false, icon: <Banknote size={18} />,
    })
  }
  if (d?.financeiro) {
    const f = d.financeiro
    cartoes.push({ chave: 'rhoje', titulo: 'A receber hoje', valor: brl(f.receber_hoje.valor), detalhe: `${f.receber_hoje.quantidade} ${f.receber_hoje.quantidade === 1 ? 'parcela' : 'parcelas'}`, atencao: f.receber_hoje.quantidade > 0, to: '/financeiro', icon: <CalendarClock size={18} /> })
    cartoes.push({ chave: 'rvenc', titulo: 'Recebimentos atrasados', valor: brl(f.receber_vencido.valor), detalhe: `${f.receber_vencido.quantidade} ${f.receber_vencido.quantidade === 1 ? 'parcela vencida' : 'parcelas vencidas'}`, atencao: f.receber_vencido.quantidade > 0, to: '/financeiro', icon: <AlertTriangle size={18} /> })
    cartoes.push({ chave: 'phoje', titulo: 'A pagar hoje', valor: brl(f.pagar_hoje.valor), detalhe: `${f.pagar_hoje.quantidade} ${f.pagar_hoje.quantidade === 1 ? 'conta' : 'contas'}`, atencao: f.pagar_hoje.quantidade > 0, to: '/financeiro', icon: <Wallet size={18} /> })
  }
  if (d?.estoque_abaixo_minimo !== undefined) {
    cartoes.push({ chave: 'estoque', titulo: 'Estoque abaixo do mínimo', valor: String(d.estoque_abaixo_minimo), detalhe: d.estoque_abaixo_minimo > 0 ? 'Hora de repor' : 'Tudo acima do mínimo cadastrado', atencao: d.estoque_abaixo_minimo > 0, to: '/estoque', icon: <PackageMinus size={18} /> })
  }
  if (d?.comodatos_vencidos !== undefined) {
    cartoes.push({ chave: 'comodato', titulo: 'Comodatos vencidos', valor: String(d.comodatos_vencidos), detalhe: d.comodatos_vencidos > 0 ? 'Prazo de devolução passou' : 'Nenhum contrato vencido', atencao: d.comodatos_vencidos > 0, to: '/comodatos', icon: <PackageCheck size={18} /> })
  }
  if (d?.cadastros_a_revisar !== undefined) {
    cartoes.push({ chave: 'revisar', titulo: 'Clientes duplicados', valor: String(d.cadastros_a_revisar), detalhe: d.cadastros_a_revisar > 0 ? 'Possíveis clientes duplicados' : 'Nada a revisar', atencao: d.cadastros_a_revisar > 0, to: '/clientes/revisoes', icon: <ClipboardList size={18} /> })
  }

  return (
    <section aria-labelledby="hoje-titulo">
      <h2 id="hoje-titulo" className="mb-3 text-lg font-semibold">Para hoje</h2>
      <AsyncState loading={q.isLoading} error={q.error} onRetry={() => { void q.refetch() }} empty={!!d && cartoes.length === 0} emptyTitle="Nada para acompanhar com o seu acesso">
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {cartoes.map((c) => (
            <Link key={c.chave} to={c.to}
              className={`flex items-start gap-3 rounded-xl border p-4 transition-colors hover:border-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring ${c.atencao ? 'border-primary/50 bg-card' : 'bg-card/60'}`}>
              <span className={`grid size-9 shrink-0 place-items-center rounded-lg ${c.atencao ? 'bg-primary/15 text-marcaTexto' : 'bg-secondary text-muted-foreground'}`}>{c.icon}</span>
              <span className="min-w-0 flex-1">
                <span className="block text-sm text-muted-foreground">{c.titulo}</span>
                <span className="block text-2xl font-bold tabular-nums">{c.valor}</span>
                {c.detalhe && <span className={`block text-xs ${c.atencao ? 'font-medium text-foreground' : 'text-muted-foreground'}`}>{c.detalhe}</span>}
              </span>
            </Link>
          ))}
        </div>
      </AsyncState>
    </section>
  )
}
