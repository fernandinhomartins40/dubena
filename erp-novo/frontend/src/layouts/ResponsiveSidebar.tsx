import { useEffect, useState, type ReactNode } from 'react'
import * as Dialog from '@radix-ui/react-dialog'
import { X } from 'lucide-react'
import { cn } from '@/lib/cn'

export function ResponsiveSidebar({ open, onOpenChange, expanded, children }: {
  open: boolean; onOpenChange: (value: boolean) => void; expanded: boolean; children: ReactNode
}) {
  const [mobile, setMobile] = useState(() => window.matchMedia('(max-width: 767px)').matches)
  useEffect(() => {
    const query = window.matchMedia('(max-width: 767px)')
    const update = () => { setMobile(query.matches); if (!query.matches) onOpenChange(false) }
    query.addEventListener('change', update)
    return () => query.removeEventListener('change', update)
  }, [onOpenChange])
  if (!mobile) return <aside className={cn('flex shrink-0 flex-col bg-sidebar text-sidebar-foreground transition-[width] duration-200', expanded ? 'w-64' : 'w-16')}>{children}</aside>
  return <Dialog.Root open={open} onOpenChange={onOpenChange}>
    <Dialog.Portal>
      <Dialog.Overlay className="fixed inset-0 z-40 bg-black/50" />
      <Dialog.Content aria-describedby={undefined} className="fixed inset-y-0 left-0 z-50 flex w-72 max-w-[90vw] flex-col bg-sidebar text-sidebar-foreground shadow-xl"
        onCloseAutoFocus={(event) => { event.preventDefault(); document.getElementById('abrir-menu')?.focus() }}>
        <Dialog.Title className="sr-only">Menu principal</Dialog.Title>
        <Dialog.Close aria-label="Fechar menu" className="absolute right-2 top-3 grid size-10 place-items-center rounded text-white focus-visible:outline focus-visible:outline-2"><X size={18} /></Dialog.Close>
        {children}
      </Dialog.Content>
    </Dialog.Portal>
  </Dialog.Root>
}
