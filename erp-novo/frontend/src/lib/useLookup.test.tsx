import { renderHook, waitFor, act } from '@testing-library/react'
import { describe, it, expect, vi, afterEach } from 'vitest'
import { api } from './api'
import { parseLookup, useLookup } from './useLookup'

afterEach(() => vi.restoreAllMocks())

describe('lookup recuperável', () => {
  it('aceita vazio real e recusa formato inválido', () => {
    expect(parseLookup([])).toEqual([])
    expect(parseLookup({ data: [{ id: 1, label: 'Centro' }] })).toHaveLength(1)
    expect(() => parseLookup({})).toThrow()
    expect(() => parseLookup([{ id: 1 }])).toThrow()
    expect(parseLookup({ data: [{ id: 1, descricao: 'Banco' }] }, 'descricao')).toEqual([{ id: 1, descricao: 'Banco', label: 'Banco' }])
  })

  it('expõe falha e permite tentar novamente', async () => {
    vi.spyOn(api, 'get').mockRejectedValueOnce(new Error('offline')).mockResolvedValueOnce({ data: [] })
    const { result } = renderHook(() => useLookup(true, '/lookups/cidades', ''))
    await waitFor(() => expect(result.current.error).toBeTruthy())
    act(() => result.current.retry())
    await waitFor(() => expect(result.current.loading).toBe(false))
    expect(result.current.error).toBeNull()
    expect(result.current.options).toEqual([])
  })

  it('ignora resposta atrasada da busca anterior', async () => {
    let resolveOld!: (value: unknown) => void
    vi.spyOn(api, 'get').mockImplementationOnce(() => new Promise((resolve) => { resolveOld = resolve }))
      .mockResolvedValueOnce({ data: [{ id: 2, label: 'Novo' }] })
    const { result, rerender } = renderHook(({ q }) => useLookup(true, '/lookup', q), { initialProps: { q: 'antigo' } })
    await waitFor(() => expect(resolveOld).toBeDefined())
    rerender({ q: 'novo' })
    await waitFor(() => expect(result.current.options[0]?.id).toBe(2))
    await act(async () => resolveOld({ data: [{ id: 1, label: 'Antigo' }] }))
    expect(result.current.options[0]?.id).toBe(2)
  })
})
