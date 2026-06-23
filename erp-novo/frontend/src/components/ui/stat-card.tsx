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
  lime: 'bg-lime/25 text-[hsl(84_70%_30%)] dark:bg-lime/15 dark:text-lime',
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
}

export function StatCard({ titulo, valor, icon: Icon, accent = 'primary', hint, className }: Props) {
  return (
    <div className={cn('rounded-xl border border-border bg-card p-5 transition-shadow hover:shadow-md', className)}>
      <div className={cn('grid size-11 place-items-center rounded-lg', ACCENTS[accent])}>
        <Icon size={20} strokeWidth={2.2} />
      </div>
      <p className="mt-4 text-3xl font-bold tracking-tight tabular-nums text-foreground">
        {typeof valor === 'number' ? valor.toLocaleString('pt-BR') : valor}
      </p>
      <p className="text-sm text-muted-foreground">{titulo}</p>
      {hint && <p className="mt-1 text-xs text-muted-foreground/70">{hint}</p>}
    </div>
  )
}
