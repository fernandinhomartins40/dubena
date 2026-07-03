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
    <div className="grid min-h-screen lg:grid-cols-2 bg-background">
      {/* Painel de marca — mesma linguagem do ERP (sidebar grafite + acento laranja) */}
      <div className="hidden lg:flex flex-col justify-between bg-sidebar p-10 text-sidebar-foreground">
        <div className="flex items-center gap-2.5">
          <div className="grid size-10 place-items-center rounded-lg bg-sidebar-accent text-white shadow-md shadow-black/30">
            <ShieldCheck size={22} strokeWidth={2.2} />
          </div>
          <div className="leading-tight">
            <span className="text-lg font-bold tracking-wide text-white">Dubena</span>
            <p className="text-[11px] uppercase tracking-wider text-sidebar-foreground/50">SuperAdmin</p>
          </div>
        </div>
        <div className="max-w-md space-y-3">
          <h2 className="text-3xl font-bold leading-tight text-white">
            Administração da <span className="text-sidebar-accent">plataforma</span>
          </h2>
          <p className="text-sm leading-relaxed text-sidebar-foreground/70">
            Gestão cross-tenant de empresas, planos, recursos e cidades — com toda
            ação registrada em trilha de auditoria imutável.
          </p>
        </div>
        <p className="text-xs text-sidebar-foreground/40">
          Acesso restrito · guard isolado da operação dos tenants
        </p>
      </div>

      {/* Formulário */}
      <div className="grid place-items-center px-4 py-10">
        <div className="w-full max-w-sm">
          <div className="mb-6 flex flex-col items-center gap-2 lg:hidden">
            <div className="grid size-12 place-items-center rounded-xl bg-primary/12 text-primary">
              <ShieldCheck size={26} strokeWidth={2.2} />
            </div>
            <h1 className="text-xl font-bold">SuperAdmin</h1>
            <p className="text-sm text-muted-foreground">Administração da plataforma</p>
          </div>

          <div className="mb-6 hidden lg:block">
            <h1 className="text-2xl font-bold">Entrar</h1>
            <p className="text-sm text-muted-foreground">Use sua credencial de administrador da plataforma.</p>
          </div>

          <form onSubmit={onSubmit} className="space-y-4 rounded-xl border border-border bg-card p-6 shadow-sm">
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
    </div>
  )
}
