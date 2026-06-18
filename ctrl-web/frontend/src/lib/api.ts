import axios from 'axios'

/**
 * Cliente HTTP do SPA. Sanctum SPA cookie-based:
 *  - withCredentials envia o cookie de sessão;
 *  - o XSRF-TOKEN (cookie) é refletido no header X-XSRF-TOKEN (axios faz auto
 *    quando xsrfCookieName/xsrfHeaderName batem).
 * Mesma origem em produção (/api/admin); em DEV o Vite faz proxy p/ o Laravel.
 */
export const api = axios.create({
  baseURL: '/api/admin',
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: { Accept: 'application/json' },
})

/** Garante o cookie CSRF antes de um POST de login/escrita (Sanctum). */
export async function ensureCsrf(): Promise<void> {
  await axios.get('/sanctum/csrf-cookie', { withCredentials: true })
}
