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
    // Fundo grafite (industrial). Cor entra só nos acentos.
    <div className="min-h-full grid place-items-center bg-[hsl(0_0%_10%)] p-4">
      {/* faixa lateral laranja sutil, como detalhe da linguagem TRA */}
      <div className="w-full max-w-sm overflow-hidden rounded-2xl border border-white/10 bg-card shadow-2xl">
        <div className="h-1.5 bg-primary" />
        <div className="p-8">
          <div className="mb-7 flex items-center gap-3">
            <div className="grid size-11 place-items-center rounded-lg bg-primary text-base font-black text-primary-foreground shadow-md">D</div>
            <div>
              <h1 className="text-xl font-bold tracking-tight text-foreground">Dubena</h1>
              <p className="text-xs text-muted-foreground">ERP · nova interface</p>
            </div>
          </div>

          <form onSubmit={onSubmit} className="space-y-4">
            <div>
              <label className="mb-1.5 block text-sm font-medium text-foreground">E-mail / usuário</label>
              <input
                type="text" value={email} onChange={(e) => setEmail(e.target.value)} autoFocus required
                className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none transition-shadow focus:border-primary focus:ring-2 focus:ring-primary/30"
              />
            </div>
            <div>
              <label className="mb-1.5 block text-sm font-medium text-foreground">Senha</label>
              <input
                type="password" value={password} onChange={(e) => setPassword(e.target.value)} required
                className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none transition-shadow focus:border-primary focus:ring-2 focus:ring-primary/30"
              />
            </div>

            {erro && <p className="text-sm text-destructive">{erro}</p>}

            <button
              type="submit" disabled={enviando}
              className="w-full rounded-md bg-primary py-2.5 font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-60"
            >
              {enviando ? 'Entrando…' : 'Entrar'}
            </button>
          </form>
        </div>
      </div>
    </div>
  )
}
