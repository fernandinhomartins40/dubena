import { createContext, useCallback, useContext, useEffect, useId, useMemo, useRef, useState, type ReactNode } from 'react'
import { useBlocker } from 'react-router-dom'
import { ConfirmDialog } from '@/components/ui/confirm-dialog'

interface Guard {
  setDirty: (id: string, dirty: boolean) => void
  requestLeave: (action: () => void) => void
}
const Context = createContext<Guard | null>(null)

export function UnsavedChangesProvider({ children }: { children: ReactNode }) {
  const changed = useRef(new Set<string>())
  const [pending, setPending] = useState<(() => void) | null>(null)
  const setDirty = useCallback((id: string, dirty: boolean) => { if (dirty) changed.current.add(id); else changed.current.delete(id) }, [])
  const requestLeave = useCallback((action: () => void) => {
    if (changed.current.size) setPending(() => action)
    else action()
  }, [])
  const blocker = useBlocker(({ currentLocation, nextLocation }) => changed.current.size > 0 && currentLocation.pathname !== nextLocation.pathname)
  const value = useMemo(() => ({ setDirty, requestLeave }), [setDirty, requestLeave])
  const blocked = blocker.state === 'blocked'
  function cancel() { setPending(null); if (blocker.state === 'blocked') blocker.reset() }
  function discard() {
    const action = pending
    setPending(null)
    // Só a navegação confirmada desmontará o formulário. Uma ação assíncrona
    // pode falhar: nesse caso o rascunho continua protegido até reset/unmount.
    if (blocker.state === 'blocked') { changed.current.clear(); blocker.proceed() }
    else action?.()
  }
  return <Context.Provider value={value}>{children}
    <ConfirmDialog open={blocked || !!pending} onOpenChange={(open) => { if (!open) cancel() }}
      title="Sair sem salvar?" description="Há alterações que ainda não foram salvas. Você pode cancelar para continuar editando ou descartar para sair."
      confirmLabel="Descartar e continuar" onConfirm={discard} />
  </Context.Provider>
}

export function useDirtyRegistration(dirty: boolean) {
  const context = useContext(Context)
  const id = useId()
  const setDirty = context?.setDirty
  useEffect(() => { setDirty?.(id, dirty); return () => setDirty?.(id, false) }, [setDirty, id, dirty])
  return () => setDirty?.(id, false)
}

export function useRequestLeave() {
  const context = useContext(Context)
  return context?.requestLeave ?? ((action: () => void) => action())
}
