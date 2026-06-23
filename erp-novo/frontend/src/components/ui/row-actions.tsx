import { Pencil, Trash2 } from 'lucide-react'
import { Button } from './button'

/**
 * RowActions — cluster padrão de ações de linha em tabelas (editar + excluir).
 * Centraliza o confirm de exclusão e o alinhamento à direita, eliminando o
 * boilerplate repetido em cada DataTable.
 */
interface Props {
  onEdit?: () => void
  onDelete?: () => void
  /** mensagem do confirm de exclusão */
  confirmMsg?: string
  /** ações extras à esquerda dos ícones (ex.: botões específicos do módulo) */
  extra?: React.ReactNode
}

export function RowActions({ onEdit, onDelete, confirmMsg = 'Excluir este registro?', extra }: Props) {
  return (
    <div className="flex items-center justify-end gap-1">
      {extra}
      {onEdit && (
        <Button variant="ghost" size="icon" onClick={onEdit} aria-label="Editar">
          <Pencil size={15} />
        </Button>
      )}
      {onDelete && (
        <Button
          variant="ghost" size="icon" aria-label="Excluir"
          onClick={() => { if (confirm(confirmMsg)) onDelete() }}
        >
          <Trash2 size={15} />
        </Button>
      )}
    </div>
  )
}
