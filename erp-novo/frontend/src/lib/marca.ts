/**
 * PIE P2-A-08 — marca da PLATAFORMA, para as telas em que ainda não se sabe de
 * que revenda é quem está entrando (login do painel e do SuperAdmin).
 *
 * O login trazia "Dubena" fixo: o funcionário da revenda B entrava numa tela
 * com o nome da concorrente. Depois do login a marca é a da revenda (ver
 * `useNomeDoTenant`); antes dele, é a da plataforma — configurável no build
 * (`VITE_APP_NAME`), neutra por padrão, como o `<title>` do `index.html`.
 *
 * O nome comercial do produto é decisão pendente; por isso o default é neutro
 * e não um nome inventado aqui.
 */
export const NOME_DA_PLATAFORMA: string =
  (import.meta.env.VITE_APP_NAME as string | undefined)?.trim() || 'ERP'
