import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { ShieldCheck } from 'lucide-react'
import { Button, Input, Field, toast } from '@/components/ui'
import { useSaAuth } from './auth'

/** Credencial de teste (atalho de preenchimento rápido). */
const LOGIN_TESTE = { email: 'superadmin@gasemcasa.com', senha: 'superadmin123' }
// Mostra o atalho fora de produção (em prod o painel real não traz seed de teste).
const MOSTRAR_TESTE = !import.meta.env.PROD || import.meta.env.VITE_APP_ENV !== 'prod'

/**
 * Login do SuperAdmin (P4). Isolado do login do tenant. Suporta 2FA: o backend
 * responde 423 (two_factor_required) → revelamos o campo de código.
 */
export function SaLoginPage() {
  const { login } = useSaAuth()
  const navigate = useNavigate()
  const [email, setEmail] = useState('')
  const [senha, setSenha] = useState('')
  const [otp, setOtp] = useState('')
  const [precisa2fa, setPrecisa2fa] = useState(false)
  const [carregando, setCarregando] = useState(false)

  async function entrar(emailArg: string, senhaArg: string, otpArg?: string) {
    setCarregando(true)
    try {
      await login(emailArg.trim(), senhaArg, otpArg?.trim() || undefined)
      navigate('/superadmin', { replace: true })
    } catch (err: any) {
      if (err?.response?.status === 423) {
        setPrecisa2fa(true)
        toast.info('Informe o código de verificação (2FA).')
      } else {
        toast.error(err?.response?.data?.message ?? 'Credenciais inválidas.')
      }
    } finally {
      setCarregando(false)
    }
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    await entrar(email, senha, precisa2fa ? otp : undefined)
  }

  function preencherTeste() {
    setEmail(LOGIN_TESTE.email)
    setSenha(LOGIN_TESTE.senha)
    entrar(LOGIN_TESTE.email, LOGIN_TESTE.senha)
  }

  return (
    <div className="grid min-h-screen place-items-center bg-background px-4">
      <div className="w-full max-w-sm">
        <div className="mb-6 flex flex-col items-center gap-2">
          <div className="grid size-12 place-items-center rounded-xl bg-primary/12 text-primary">
            <ShieldCheck size={26} strokeWidth={2.2} />
          </div>
          <h1 className="text-xl font-bold">SuperAdmin</h1>
          <p className="text-sm text-muted-foreground">Administração da plataforma</p>
        </div>

        <form onSubmit={onSubmit} className="space-y-4 rounded-xl border border-border bg-card p-6">
          <Field label="E-mail" required>
            <Input type="email" autoComplete="username" value={email} onChange={(e) => setEmail(e.target.value)} disabled={carregando} />
          </Field>
          <Field label="Senha" required>
            <Input type="password" autoComplete="current-password" value={senha} onChange={(e) => setSenha(e.target.value)} disabled={carregando} />
          </Field>
          {precisa2fa && (
            <Field label="Código de verificação (2FA)" required>
              <Input inputMode="numeric" value={otp} onChange={(e) => setOtp(e.target.value)} placeholder="000000" disabled={carregando} />
            </Field>
          )}
          <Button type="submit" className="w-full" disabled={carregando}>
            {carregando ? 'Entrando…' : 'Entrar'}
          </Button>

          {MOSTRAR_TESTE && (
            <div className="border-t border-border pt-3">
              <p className="mb-2 text-center text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                Modo teste
              </p>
              <Button type="button" variant="outline" className="w-full" onClick={preencherTeste} disabled={carregando}>
                Preencher login de teste
              </Button>
              <p className="mt-2 text-center text-xs text-muted-foreground">
                {LOGIN_TESTE.email} · {LOGIN_TESTE.senha}
              </p>
            </div>
          )}
        </form>
      </div>
    </div>
  )
}
