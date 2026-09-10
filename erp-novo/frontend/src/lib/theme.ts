import { useState } from 'react'

const KEY = 'erpnovo.ui.theme'
export function initializeTheme() {
  let dark = window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? false
  try { const saved = localStorage.getItem(KEY); if (saved === 'dark' || saved === 'light') dark = saved === 'dark' } catch { /* sistema como fallback */ }
  document.documentElement.classList.toggle('dark', dark)
}
export function useTheme() {
  const [dark, setDark] = useState(() => document.documentElement.classList.contains('dark'))
  function toggleDark() {
    const next = !dark
    document.documentElement.classList.toggle('dark', next)
    try { localStorage.setItem(KEY, next ? 'dark' : 'light') } catch { /* preferência em memória */ }
    setDark(next)
  }
  return { dark, toggleDark }
}
