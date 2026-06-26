import type { ReactNode } from 'react'
import { Search } from 'lucide-react'
import { Card } from './card'
import { Input } from './input'
import { Button } from './button'

/**
 * SearchBar — barra de busca padrão das telas de lista. Encapsula o Card +
 * input com ícone + botão "Buscar", submetendo por Enter OU clique (substitui
 * as duas variações ad-hoc: <form onSubmit> e onKeyDown Enter + botão).
 * `children` permite filtros extras (selects) à esquerda do input.
 */
interface Props {
  value: string
  onChange: (v: string) => void
  onSearch: () => void
  placeholder?: string
  /** filtros adicionais (ex.: AsyncSelect de setor) renderizados antes do input */
  children?: ReactNode
}

export function SearchBar({ value, onChange, onSearch, placeholder = 'Buscar…', children }: Props) {
  return (
    <Card className="mb-4 p-3">
      <form
        className="flex flex-wrap gap-2 items-center"
        onSubmit={(e) => { e.preventDefault(); onSearch() }}
      >
        {children}
        <div className="relative flex-1 min-w-[200px]">
          <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
          <Input value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} className="pl-9" />
        </div>
        <Button type="submit" variant="secondary">Buscar</Button>
      </form>
    </Card>
  )
}
