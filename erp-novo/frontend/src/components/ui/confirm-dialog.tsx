import type { ReactNode } from 'react'
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogClose,
} from './dialog'
import { Button } from './button'

/**
 * ConfirmDialog — modal de confirmação do padrão (excluir/ação destrutiva).
 * Encapsula Dialog + título + mensagem + rodapé Cancelar/Confirmar com loading.
 * Diferente do FormDialog (que é "Salvar" de formulário): aqui o confirmar é
 * destructive por padrão. Use `open`/`onOpenChange` controlados pela página.
 */
interface Props {
  open: boolean
  onOpenChange: (v: boolean) => void
  title: string
  /** corpo: texto da pergunta ou nós customizados */
  description?: ReactNode
  /** rótulo do botão de confirmação (default "Excluir") */
  confirmLabel?: string
  onConfirm: () => void
  loading?: boolean
  /** variante do botão de confirmação (default "destructive") */
  variant?: 'destructive' | 'default'
  children?: ReactNode
}

export function ConfirmDialog({
  open, onOpenChange, title, description, confirmLabel = 'Excluir',
  onConfirm, loading, variant = 'destructive', children,
}: Props) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          {description && <p className="text-sm text-muted-foreground">{description}</p>}
        </DialogHeader>
        {children && <div className="space-y-4">{children}</div>}
        <DialogFooter>
          <DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose>
          <Button variant={variant} loading={loading} onClick={onConfirm}>{confirmLabel}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
