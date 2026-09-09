import { useEffect, useRef, useState } from 'react'
import { Check, Loader2 } from 'lucide-react'
import { cn } from '@/lib/cn'
import type { FormatoExportacao } from './api'

/**
 * Custo por linha, em milissegundos, medido nesta base (55.000 linhas × 13
 * colunas, na VPS de produção):
 *
 *   XLSX ~100s  → o PhpSpreadsheet monta uma árvore de objetos por célula
 *   CSV    ~1s  → escrita direta em stream
 *   PDF    —     tem teto de 5.000 linhas, então nunca chega a demorar
 *
 * A estimativa serve para dizer ao usuário o que esperar, não para ser exata:
 * a barra nunca "completa" sozinha — quem a fecha é a resposta do servidor.
 */
const MS_POR_LINHA: Record<FormatoExportacao, number> = {
  xlsx: 1.9,
  csv: 0.02,
  pdf: 0.6,
}

/** Etapas reais do backend, na ordem em que acontecem. */
const ETAPAS = [
  { rotulo: 'Consultando os clientes', fatia: 0.25 },
  { rotulo: 'Montando as colunas', fatia: 0.20 },
  { rotulo: 'Gerando o arquivo', fatia: 0.50 },
  { rotulo: 'Preparando o download', fatia: 0.05 },
]

interface Props {
  formato: FormatoExportacao
  totalLinhas: number
}

/**
 * Feedback visual durante a geração.
 *
 * Duas decisões que definem este componente:
 *
 * 1. O progresso é ESTIMADO, não medido — e isso está dito na tela. O backend
 *    devolve o arquivo numa resposta só: não há evento de progresso para
 *    ouvir, e o download em si é uma fração do tempo (2,4 MB depois de ~100s
 *    gerando). Uma barra que fingisse saber a porcentagem real mentiria, e
 *    mentiria pior justamente quando travasse perto do fim.
 *
 * 2. Por isso ela DESACELERA e para em 95%. Nunca chega a 100% sozinha: quem
 *    completa é a resposta chegando. Assim a barra jamais fica "cheia" com o
 *    usuário esperando — o pior sinal que uma barra de progresso pode dar.
 */
export function ProgressoExportacao({ formato, totalLinhas }: Props) {
  const [decorrido, setDecorrido] = useState(0)
  const inicio = useRef(Date.now())

  const estimativaMs = Math.max(2000, totalLinhas * MS_POR_LINHA[formato])

  useEffect(() => {
    inicio.current = Date.now()
    const t = setInterval(() => setDecorrido(Date.now() - inicio.current), 250)
    return () => clearInterval(t)
  }, [])

  // Curva que desacelera: chega a ~63% na estimativa e tende a 95% sem nunca
  // alcançá-la. Se a estimativa errar para menos, a barra continua andando
  // devagar em vez de estacionar cheia.
  const fracao = Math.min(0.95, 1 - Math.exp(-decorrido / estimativaMs))
  const restanteMs = Math.max(0, estimativaMs - decorrido)

  // Índice da etapa: soma as fatias até passar da fração atual.
  let acumulado = 0
  let etapaAtual = ETAPAS.length - 1
  for (let i = 0; i < ETAPAS.length; i++) {
    acumulado += ETAPAS[i].fatia
    if (fracao < acumulado) { etapaAtual = i; break }
  }

  return (
    <div className="space-y-3 rounded-md border border-border bg-secondary/40 p-4">
      <div className="flex items-center gap-2 text-sm font-medium">
        <Loader2 className="size-4 shrink-0 animate-spin text-primary" />
        <span>{ETAPAS[etapaAtual].rotulo}…</span>
        <span className="ml-auto tabular-nums text-muted-foreground">
          {formatarTempo(decorrido)}
        </span>
      </div>

      <div
        className="h-2 overflow-hidden rounded-full bg-border"
        role="progressbar"
        aria-valuemin={0}
        aria-valuemax={100}
        aria-valuenow={Math.round(fracao * 100)}
        aria-label="Progresso da exportação"
      >
        <div
          className="h-full rounded-full bg-primary transition-[width] duration-300 ease-out"
          style={{ width: `${fracao * 100}%` }}
        />
      </div>

      <ol className="space-y-1">
        {ETAPAS.map((etapa, i) => (
          <li key={etapa.rotulo}
            className={cn(
              'flex items-center gap-2 text-xs',
              i < etapaAtual ? 'text-muted-foreground'
                : i === etapaAtual ? 'font-medium text-foreground'
                : 'text-muted-foreground/50',
            )}>
            {i < etapaAtual
              ? <Check className="size-3.5 shrink-0 text-primary" />
              : i === etapaAtual
                ? <Loader2 className="size-3.5 shrink-0 animate-spin" />
                : <span className="size-3.5 shrink-0 rounded-full border border-current" />}
            {etapa.rotulo}
          </li>
        ))}
      </ol>

      <p className="text-xs text-muted-foreground">
        {totalLinhas.toLocaleString('pt-BR')} clientes em {formato.toUpperCase()}.
        {restanteMs > 1000 && <> Estimativa: cerca de {formatarTempo(restanteMs)} restantes.</>}
        {' '}Volumes grandes levam mais tempo — pode deixar esta janela aberta,
        o download começa sozinho quando terminar.
      </p>
    </div>
  )
}

function formatarTempo(ms: number): string {
  const s = Math.ceil(ms / 1000)
  if (s < 60) return `${s}s`
  const m = Math.floor(s / 60)
  return `${m}min ${String(s % 60).padStart(2, '0')}s`
}
