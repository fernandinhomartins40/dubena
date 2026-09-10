import { renderHook, act } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { vi, it, expect, beforeEach } from 'vitest'
import type { ReactNode } from 'react'
import { useBusca } from './useBusca'
const auth = vi.hoisted(() => ({ user: { id: 1, empresa_id: 10 } }))
vi.mock('./auth', () => ({ useAuth: () => auth }))
beforeEach(() => { sessionStorage.clear(); auth.user.id = 1 })
const wrapper = ({ children }: { children: ReactNode }) => <MemoryRouter initialEntries={['/clientes']}>{children}</MemoryRouter>
it('retoma termo/página na sessão e não herda pesquisa de outra pessoa', () => {
  const first = renderHook(() => useBusca(), { wrapper })
  act(() => first.result.current.setBusca('Ana'))
  act(() => first.result.current.submit())
  act(() => first.result.current.setPage(3))
  first.unmount()
  const next = renderHook(() => useBusca(), { wrapper })
  expect(next.result.current.q).toBe('Ana')
  expect(next.result.current.page).toBe(3)
  auth.user.id = 2
  next.rerender()
  expect(next.result.current.q).toBe('')
  expect(next.result.current.page).toBe(1)
})
it('mantém termo digitado separado do aplicado e reseta página ao buscar', () => {
  const { result } = renderHook(() => useBusca(), { wrapper })
  act(() => { result.current.setBusca('Maria'); result.current.setPage(9) })
  expect(result.current.q).toBe('')
  act(() => result.current.submit())
  expect(result.current.q).toBe('Maria')
  expect(result.current.page).toBe(1)
})
