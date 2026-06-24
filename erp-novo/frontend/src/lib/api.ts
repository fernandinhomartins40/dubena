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

// Anexa o Bearer token quando houver (modo token; cookie funciona sem ele).
api.interceptors.request.use((config) => {
  const token = getToken()
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

/** Garante o cookie CSRF antes de um POST de login/escrita (Sanctum cookie). */
export async function ensureCsrf(): Promise<void> {
  await axios.get(`${PREFIX}/sanctum/csrf-cookie`, { withCredentials: true })
}

/** Base de rota da API (ex.: '/novo') — útil para chamadas fora do baseURL admin. */
export const apiPrefix = PREFIX
