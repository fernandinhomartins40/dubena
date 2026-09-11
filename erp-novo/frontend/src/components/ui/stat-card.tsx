import type { LucideIcon } from 'lucide-react'
import { cn } from '@/lib/cn'

/**
 * StatCard — tile de métrica/KPI do padrão (dashboard e resumos).
 * Cor entra só no TILE do ícone (tint), nunca no card inteiro. As variantes
 * mapeiam para tokens centrais — trocar a marca não exige mexer aqui.
 */
export type StatAccent = 'primary' | 'lime' | 'neutral' | 'success' | 'destructive'

const ACCENTS: Record<StatAccent, string> = {
  primary: 'bg-primary/12 text-primary',
  // O lime só existe de verdade em superfície sólida com grafite por cima
  // (14,26:1). Diluído a 25% sobre o card branco ele virava um tint de
  // luminância 0,95 — indistinguível do fundo, que era o efeito anterior.
  lime: 'bg-destaque text-destaque-foreground',
  neutral: 'bg-foreground/8 text-foreground',
  success: 'bg-success/15 text-success',
  destructive: 'bg-destructive/12 text-destructive',
}

interface Props {
  titulo: string
  valor: number | string
  icon: LucideIcon
  accent?: StatAccent
  hint?: string
  className?: string
  loading?: boolean
  error?: unknown
}

export function StatCard({ titulo, valor, icon: Icon, accent = 'primary', hint, className, loading, error }: Props) {
  return (
    <div aria-busy={loading || undefined} className={cn('rounded-xl border border-border bg-card p-5 transition-shadow hover:shadow-md', className)}>
      <div className={cn('grid size-11 place-items-center rounded-lg', ACCENTS[accent])}>
        <Icon size={20} strokeWidth={2.2} />
      </div>
      {/* Quem opera uma revenda passa o dia com quantidade: botijões em poder
          do cliente, pedidos na fila, parcelas em aberto. O número é o
          conteúdo — não o card que o embrulha. É aqui, e só aqui, que a
          interface levanta a voz. */}
      <p className="mt-4 text-[2.75rem] font-bold leading-none tracking-[-0.03em] tabular-nums text-foreground">
        {loading || error ? '—' : typeof valor === 'number' ? valor.toLocaleString('pt-BR') : valor}
      </p>
      <p className="mt-2 text-sm text-muted-foreground">{titulo}</p>
      {(loading || error || hint) && <p className="mt-1 text-xs text-muted-foreground">{loading ? 'Carregando…' : error ? 'Consulta indisponível' : hint}</p>}
    </div>
  )
}
