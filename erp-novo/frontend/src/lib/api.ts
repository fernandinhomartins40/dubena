import axios from 'axios'

/**
 * Cliente HTTP do SPA do erp-novo. Suporta os DOIS modos de auth do Sanctum:
 *  - COOKIE (SPA stateful): withCredentials + XSRF-TOKEN refletido no header.
 *  - TOKEN (Bearer): se houver token salvo (login), é enviado no Authorization.
 *
 * O prefixo de rota deriva do base do Vite (/novo/app/ → /novo), para a API ser
 * chamada em /novo/api/admin sem hardcode. Em DEV (base /) cai em /api/admin.
 */

// '/novo/app/' → '/novo' ; '/' → '' (dev)
const PREFIX = import.meta.env.BASE_URL.replace(/\/app\/?$/, '').replace(/\/$/, '')

const TOKEN_KEY = 'erpnovo_token'

/**
 * Persistência do token:
 *  - persist=true  → localStorage (sobrevive ao fechar o navegador → "manter conectado")
 *  - persist=false → sessionStorage (cai ao fechar a aba/navegador)
 * Ao salvar num store, o outro é limpo para não haver token "fantasma".
 */
export function setToken(token: string | null, persist = true): void {
  if (token) {
    const store = persist ? localStorage : sessionStorage
    const other = persist ? sessionStorage : localStorage
    store.setItem(TOKEN_KEY, token)
    other.removeItem(TOKEN_KEY)
  } else {
    localStorage.removeItem(TOKEN_KEY)
    sessionStorage.removeItem(TOKEN_KEY)
  }
}

export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY) ?? sessionStorage.getItem(TOKEN_KEY)
}

export const api = axios.create({
  baseURL: `${PREFIX}/api/admin`,
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: { Accept: 'application/json' },
})

const FILTRO_EMPRESA_KEY = 'erpnovo_filtro_empresa'

/**
 * Filtro "empresa" das listagens — o combo do cabeçalho.
 *
 * Numa rede com filiais, as telas mostram a operação INTEIRA por padrão (é o
 * comportamento do ERP antigo). Este filtro refina para uma unidade; `null`
 * volta a mostrar a rede. Não confundir com a EMPRESA ATIVA (`X-Empresa-Id`),
 * que define config, caixa e numeração fiscal.
 *
 * Fica em sessionStorage: é uma escolha de visualização, não deve sobreviver
 * ao fechamento do navegador nem contaminar outra aba.
 */
export function setFiltroEmpresa(empresaId: number | null): void {
  if (empresaId && empresaId > 0) sessionStorage.setItem(FILTRO_EMPRESA_KEY, String(empresaId))
  else sessionStorage.removeItem(FILTRO_EMPRESA_KEY)
}

export function getFiltroEmpresa(): number | null {
  const v = Number(sessionStorage.getItem(FILTRO_EMPRESA_KEY))
  return Number.isFinite(v) && v > 0 ? v : null
}

// Anexa o Bearer token quando houver (modo token; cookie funciona sem ele).
api.interceptors.request.use((config) => {
  const token = getToken()
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  // O filtro vai em TODA requisição: o backend o aplica no escopo de tenant, o
  // que evita ter de tocar nas ~40 telas de listagem uma a uma. Uma chamada que
  // já defina `empresa_id` explicitamente tem prioridade.
  const filtro = getFiltroEmpresa()
  if (filtro && config.params?.empresa_id === undefined) {
    config.params = { ...(config.params ?? {}), empresa_id: filtro }
  }

  return config
})

/** Garante o cookie CSRF antes de um POST de login/escrita (Sanctum cookie). */
export async function ensureCsrf(): Promise<void> {
  await axios.get(`${PREFIX}/sanctum/csrf-cookie`, { withCredentials: true })
}

/** Base de rota da API (ex.: '/novo') — útil para chamadas fora do baseURL admin. */
export const apiPrefix = PREFIX
