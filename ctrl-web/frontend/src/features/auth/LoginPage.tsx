import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '@/lib/auth'

export function LoginPage() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [erro, setErro] = useState<string | null>(null)
  const [enviando, setEnviando] = useState(false)

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setErro(null)
    setEnviando(true)
    try {
      await login(email, password)
      navigate('/')
    } catch {
      setErro('E-mail e/ou senha inválidos.')
    } finally {
      setEnviando(false)
    }
  }

  return (
    <div className="min-h-full grid place-items-center bg-gradient-to-br from-marca-800 to-accent p-4">
      <div className="w-full max-w-sm bg-white dark:bg-slate-900 rounded-xl shadow-xl p-8">
        <div className="flex items-center gap-3 mb-6">
          <div className="h-10 w-10 rounded-lg bg-destaque text-marca-900 font-black grid place-items-center text-lg">D</div>
          <div>
            <h1 className="text-xl font-bold text-marca-800 dark:text-white">Dubena</h1>
            <p className="text-xs text-slate-500">ERP · nova interface</p>
          </div>
        </div>

        <form onSubmit={onSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium mb-1">E-mail / usuário</label>
            <input
              type="text" value={email} onChange={(e) => setEmail(e.target.value)} autoFocus required
              className="w-full rounded-md border border-slate-300 dark:border-slate-700 bg-transparent px-3 py-2 outline-none focus:ring-2 focus:ring-marca-500"
            />
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Senha</label>
            <input
              type="password" value={password} onChange={(e) => setPassword(e.target.value)} required
              className="w-full rounded-md border border-slate-300 dark:border-slate-700 bg-transparent px-3 py-2 outline-none focus:ring-2 focus:ring-marca-500"
            />
          </div>

          {erro && <p className="text-sm text-red-600">{erro}</p>}

          <button
            type="submit" disabled={enviando}
            className="w-full rounded-md bg-marca-600 hover:bg-marca-700 text-white font-medium py-2 transition-colors disabled:opacity-60"
          >
            {enviando ? 'Entrando…' : 'Entrar'}
          </button>
        </form>
      </div>
    </div>
  )
}
