import { useState, useEffect, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { Eye, EyeOff, Wand2 } from 'lucide-react'
import { CheckboxField } from '@/components/ui'
import { useAuth } from '@/lib/auth'

const EMAIL_KEY = 'erpnovo_lembrar_email'

// Credenciais do acesso de demonstração (mesmas exibidas na landing).
const DEMO = { email: 'teste@gasemcasa.com', senha: 'teste1234' }

export function LoginPage() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [verSenha, setVerSenha] = useState(false)
  const [lembrar, setLembrar] = useState(false)
  const [manterConectado, setManterConectado] = useState(true)
  const [erro, setErro] = useState<string | null>(null)
  const [enviando, setEnviando] = useState(false)

  // Recupera o e-mail salvo (se o usuário marcou "lembrar" antes).
  useEffect(() => {
    const salvo = localStorage.getItem(EMAIL_KEY)
    if (salvo) { setEmail(salvo); setLembrar(true) }
  }, [])

  function preencherTeste() {
    setEmail(DEMO.email)
    setPassword(DEMO.senha)
    setErro(null)
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setErro(null)
    setEnviando(true)
    try {
      await login(email, password, manterConectado)
      if (lembrar) localStorage.setItem(EMAIL_KEY, email)
      else localStorage.removeItem(EMAIL_KEY)
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
      <div className="w-full max-w-sm overflow-hidden rounded-2xl border border-white/10 bg-card shadow-2xl">
        <div className="h-1.5 bg-primary" />
        <div className="p-8">
          <div className="mb-7 flex items-center gap-3">
            <div className="grid size-11 place-items-center rounded-lg bg-primary text-base font-black text-primary-foreground shadow-md">D</div>
            <div>
              <h1 className="text-xl font-bold tracking-tight text-foreground">Dubena</h1>
              <p className="text-xs text-muted-foreground">Gás em Casa</p>
            </div>
          </div>

          <form onSubmit={onSubmit} className="space-y-4">
            <div>
              <label htmlFor="login-email" className="mb-1.5 block text-sm font-medium text-foreground">E-mail / usuário</label>
              <input
                id="login-email" type="text" value={email} onChange={(e) => setEmail(e.target.value)}
                autoFocus required autoComplete="username"
                className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none transition-shadow focus:border-primary focus:ring-2 focus:ring-primary/30"
              />
            </div>

            <div>
              <label htmlFor="login-senha" className="mb-1.5 block text-sm font-medium text-foreground">Senha</label>
              <div className="relative">
                <input
                  id="login-senha" type={verSenha ? 'text' : 'password'} value={password}
                  onChange={(e) => setPassword(e.target.value)} required autoComplete="current-password"
                  className="w-full rounded-md border border-input bg-transparent px-3 py-2 pr-10 text-sm outline-none transition-shadow focus:border-primary focus:ring-2 focus:ring-primary/30"
                />
                <button
                  type="button" onClick={() => setVerSenha((v) => !v)}
                  aria-label={verSenha ? 'Ocultar senha' : 'Mostrar senha'}
                  title={verSenha ? 'Ocultar senha' : 'Mostrar senha'}
                  className="absolute right-1 top-1/2 grid size-8 -translate-y-1/2 place-items-center rounded-md text-muted-foreground transition-colors hover:text-foreground"
                >
                  {verSenha ? <EyeOff size={17} /> : <Eye size={17} />}
                </button>
              </div>
            </div>

            <div className="space-y-1 pt-1">
              <CheckboxField label="Lembrar meu e-mail" checked={lembrar} onChange={setLembrar} />
              <CheckboxField label="Manter conectado" checked={manterConectado} onChange={setManterConectado} />
            </div>

            {erro && <p className="text-sm text-destructive">{erro}</p>}

            <button
              type="submit" disabled={enviando}
              className="w-full rounded-md bg-primary py-2.5 font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-60"
            >
              {enviando ? 'Entrando…' : 'Entrar'}
            </button>

            <button
              type="button" onClick={preencherTeste}
              className="flex w-full items-center justify-center gap-2 rounded-md border border-dashed border-input py-2 text-sm font-medium text-muted-foreground transition-colors hover:border-primary hover:text-primary"
            >
              <Wand2 size={15} /> Preencher com acesso de demonstração
            </button>
          </form>
        </div>
      </div>
    </div>
  )
}
