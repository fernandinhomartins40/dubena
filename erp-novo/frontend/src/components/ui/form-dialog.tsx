import type { ReactNode } from 'react'
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogClose,
} from './dialog'
import { Button } from './button'

/**
 * FormDialog — modal de formulário do padrão (criar/editar).
 * Encapsula Dialog + título + corpo (campos) + rodapé Cancelar/Salvar com
 * estado de loading. As páginas só passam os campos como children.
 */
interface Props {
  open: boolean
  onOpenChange: (v: boolean) => void
  title: string
  description?: string
  /** rótulo do botão de confirmação (default "Salvar") */
  confirmLabel?: string
  onConfirm: () => void
  loading?: boolean
  /** desabilita o confirmar (ex.: validação client-side) */
  confirmDisabled?: boolean
  /** classe de largura do modal (ex.: 'max-w-2xl' p/ formulários largos) */
  widthClass?: string
  children: ReactNode
}

export function FormDialog({
  open, onOpenChange, title, description, confirmLabel = 'Salvar',
  onConfirm, loading, confirmDisabled, widthClass, children,
}: Props) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className={widthClass}>
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          {description && <p className="text-sm text-muted-foreground">{description}</p>}
        </DialogHeader>
        <div className="space-y-4">{children}</div>
        <DialogFooter>
          <DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose>
          <Button loading={loading} disabled={confirmDisabled} onClick={onConfirm}>{confirmLabel}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
