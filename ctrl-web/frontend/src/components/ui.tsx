import { type ReactNode, type InputHTMLAttributes, type ButtonHTMLAttributes, forwardRef } from 'react'
import { twMerge } from 'tailwind-merge'

/** Componentes de UI reaproveitáveis (padrão da nova interface Dubena). */

export function Button({
  className, variant = 'primary', ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & { variant?: 'primary' | 'ghost' | 'danger' }) {
  const base = 'inline-flex items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-colors disabled:opacity-60 disabled:pointer-events-none'
  const variants = {
    primary: 'bg-marca-600 hover:bg-marca-700 text-white',
    ghost: 'bg-transparent hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200',
    danger: 'bg-red-600 hover:bg-red-700 text-white',
  }
  return <button className={twMerge(base, variants[variant], className)} {...props} />
}

export const Input = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement> & { label?: string; error?: string }>(
  function Input({ className, label, error, id, ...props }, ref) {
    return (
      <div className="space-y-1">
        {label && <label htmlFor={id} className="block text-sm font-medium">{label}</label>}
        <input
          ref={ref} id={id}
          className={twMerge(
            'w-full rounded-md border bg-transparent px-3 py-2 outline-none focus:ring-2 focus:ring-marca-500',
            error ? 'border-red-500' : 'border-slate-300 dark:border-slate-700',
            className,
          )}
          {...props}
        />
        {error && <p className="text-xs text-red-600">{error}</p>}
      </div>
    )
  },
)

export function Card({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <div className={twMerge('bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800', className)}>
      {children}
    </div>
  )
}

export function PageHeader({ title, subtitle, action }: { title: string; subtitle?: string; action?: ReactNode }) {
  return (
    <div className="flex items-start justify-between mb-5">
      <div>
        <h1 className="text-2xl font-bold">{title}</h1>
        {subtitle && <p className="text-slate-500">{subtitle}</p>}
      </div>
      {action}
    </div>
  )
}

export function Tabs({ tabs, active, onChange }: { tabs: { id: string; label: string }[]; active: string; onChange: (id: string) => void }) {
  return (
    <div className="border-b border-slate-200 dark:border-slate-800 flex gap-1">
      {tabs.map((t) => (
        <button
          key={t.id}
          onClick={() => onChange(t.id)}
          className={twMerge(
            'px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors',
            active === t.id
              ? 'border-marca-600 text-marca-700 dark:text-marca-300'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300',
          )}
        >
          {t.label}
        </button>
      ))}
    </div>
  )
}
