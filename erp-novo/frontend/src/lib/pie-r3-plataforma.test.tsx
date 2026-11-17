import { render, screen } from '@testing-library/react'
import { MemoryRouter, Link } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { Button } from '@/components/ui'
import { api, concluirReautenticacao, registrarDialogoDeSessao, MENSAGEM_SESSAO_EXPIRADA, setToken } from './api'
import { normalizarMe } from './auth'
import { hasFeature } from './rbac'

/** PIE Rodada 3 — Agente C: regressões da SPA que o runtime reproduziu. */

describe('P2-D-11 — Button asChild', () => {
  it('renderiza o link sem derrubar a página (Slot com um filho só)', () => {
    render(<MemoryRouter><Button asChild><Link to="/">Voltar ao início</Link></Button></MemoryRouter>)
    expect(screen.getByRole('link', { name: 'Voltar ao início' })).toBeInTheDocument()
  })
})

describe('P2-A-08 — marca de uma revenda não aparece para as outras', () => {
  it('nenhum arquivo da SPA (fora de testes e comentários) cita "Dubena"', () => {
    const fontes = import.meta.glob(['../**/*.{ts,tsx,css}', '!../**/*.test.*'], { query: '?raw', import: 'default', eager: true }) as Record<string, string>
    expect(Object.keys(fontes).length).toBeGreaterThan(150)
    const comMarca = Object.entries(fontes).filter(([, src]) => src
      .replace(/\/\*[\s\S]*?\*\//g, '').replace(/^\s*\/\/.*$/gm, '')
      .includes('Dubena')).map(([f]) => f)
    expect(comMarca).toEqual([])
  })
})

describe('P2-A-04 — o menu enxerga a licença', () => {
  it('normalizarMe preserva os recursos contratados; licença desligada libera tudo', () => {
    const u = normalizarMe({ user: { id: 1, name: 'A', email: 'a@x', features: ['nfce'] }, tenant: {} })
    expect(hasFeature(u, 'nfce')).toBe(true)
    expect(hasFeature(u, 'crm')).toBe(false)
    const livre = normalizarMe({ user: { id: 1, name: 'A', email: 'a@x', features: [], licenca_ativa: false }, tenant: {} })
    expect(hasFeature(livre, 'crm')).toBe(true)
  })
})

describe('PIE-33 — sessão vencida não perde a tela', () => {
  afterEach(() => { vi.restoreAllMocks(); setToken(null) })

  function adaptador(respostas: number[]) {
    const chamadas: string[] = []
    const fn = vi.fn(async (config: any) => {
      chamadas.push(String(config.headers?.Authorization ?? ''))
      const status = respostas.shift() ?? 200
      if (status === 401) {
        const err: any = new Error('401')
        err.config = config
        err.response = { status: 401, data: { message: 'Unauthenticated.' }, config }
        throw err
      }
      return { data: { ok: true }, status: 200, statusText: 'OK', headers: {}, config }
    })
    return { fn, chamadas }
  }

  it('401 abre o re-login e REENVIA a mesma requisição com o token novo', async () => {
    setToken('velho')
    const { fn, chamadas } = adaptador([401, 200])
    const desregistrar = registrarDialogoDeSessao(() => { setToken('novo'); concluirReautenticacao(true) })
    try {
      const r = await api.post('/pedidos', { itens: [1] }, { adapter: fn as any })
      expect(r.data).toEqual({ ok: true })
      expect(chamadas).toEqual(['Bearer velho', 'Bearer novo'])
    } finally { desregistrar() }
  })

  it('sem re-login (fora da área logada ou desistiu), o erro chega em português', async () => {
    const { fn } = adaptador([401])
    await expect(api.get('/pedidos', { adapter: fn as any })).rejects.toMatchObject({
      response: { data: { message: MENSAGEM_SESSAO_EXPIRADA } },
    })
  })
})
