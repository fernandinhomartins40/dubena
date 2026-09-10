import { useEffect, useState, type ReactNode } from 'react'
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription,
} from './dialog'
import { Button } from './button'
import { ConfirmDialog } from './confirm-dialog'

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
  dirty?: boolean
}

export function FormDialog({
  open, onOpenChange, title, description, confirmLabel = 'Salvar',
  onConfirm, loading, confirmDisabled, widthClass, children, dirty,
}: Props) {
  const [changed, setChanged] = useState(false)
  const [discard, setDiscard] = useState(false)
  useEffect(() => { if (!open) { setChanged(false); setDiscard(false) } }, [open])
  function changeOpen(next: boolean) {
    if (loading) return
    if (!next && (dirty ?? changed)) { setDiscard(true); return }
    onOpenChange(next)
  }
  return (
    <>
    <Dialog open={open} onOpenChange={changeOpen}>
      <DialogContent className={widthClass}>
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          <DialogDescription>{description ?? 'Preencha os campos e confirme para salvar.'}</DialogDescription>
        </DialogHeader>
        <form className="space-y-5" noValidate onChangeCapture={() => setChanged(true)}
          onSubmit={(event) => { event.preventDefault(); if (!loading && !confirmDisabled) onConfirm() }}>
        <div className="space-y-4">{children}</div>
        <DialogFooter className="sticky bottom-0 gap-2 border-t bg-card pt-4">
          <Button variant="outline" disabled={loading} onClick={() => changeOpen(false)}>Cancelar</Button>
          <Button type="submit" loading={loading} disabled={confirmDisabled}>{confirmLabel}</Button>
        </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
    <ConfirmDialog open={discard} onOpenChange={setDiscard} title="Descartar alterações?"
      description="As alterações deste formulário ainda não foram salvas. Fechar descarta o que foi preenchido."
      confirmLabel="Descartar alterações" onConfirm={() => { setDiscard(false); setChanged(false); onOpenChange(false) }} />
    </>
  )
}
