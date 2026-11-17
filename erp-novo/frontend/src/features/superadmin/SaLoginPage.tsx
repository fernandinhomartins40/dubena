import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { NOME_DA_PLATAFORMA } from '@/lib/marca'
import { ShieldCheck } from 'lucide-react'
import { Button, Input, Field } from '@/components/ui'
import { loginError } from '@/lib/loginError'
import { useSaAuth } from './auth'
import { saConfirmar2fa, saIniciar2fa } from './api'

// Atalho de preenchimento: credencial vem SÓ de env var de build, e só em dev.
// O gate anterior (`!PROD || VITE_APP_ENV !== 'prod'`) era verdadeiro em
// qualquer build sem VITE_APP_ENV=prod — expunha a senha cross-tenant em
// produção. Sem as envs, o bloco não renderiza.
const SA_EMAIL = import.meta.env.VITE_SA_DEMO_EMAIL as string | undefined
const SA_SENHA = import.meta.env.VITE_SA_DEMO_PASSWORD as string | undefined
const MOSTRAR_TESTE = import.meta.env.DEV && !!SA_EMAIL && !!SA_SENHA

/**
 * Login do SuperAdmin (P4). Isolado do login do tenant. Suporta 2FA: o backend
 * responde 423 (two_factor_required) → revelamos o campo de código.
 */
export function SaLoginPage() {
  const { login, concluir } = useSaAuth()
  const navigate = useNavigate()
  const [email, setEmail] = useState('')
  const [senha, setSenha] = useState('')
  const [otp, setOtp] = useState('')
  const [precisa2fa, setPrecisa2fa] = useState(false)
  const [erro, setErro] = useState<string | null>(null)
  const [carregando, setCarregando] = useState(false)
  // PIE P2-E-06 — cadastro do 2FA obrigatório no primeiro acesso.
  const [cadastro, setCadastro] = useState<{ token: string; secret: string; uri: string } | null>(null)
  const [codigos, setCodigos] = useState<{ token: string; admin: Parameters<typeof concluir>[1]; recovery: string[] } | null>(null)

  async function entrar(emailArg: string, senhaArg: string, otpArg?: string) {
    setErro(null)
    setCarregando(true)
    try {
      await login(emailArg.trim(), senhaArg, otpArg?.trim() || undefined)
      navigate('/superadmin', { replace: true })
    } catch (err: any) {
      const corpo = err?.response?.data
      if (err?.response?.status === 423 && corpo?.two_factor_setup_required && corpo?.setup_token) {
        try {
          const r = await saIniciar2fa(corpo.setup_token)
          setCadastro({ token: corpo.setup_token, secret: r.secret, uri: r.otpauth_uri })
          setOtp('')
        } catch (e2) {
          setErro(loginError(e2))
        }
      } else if (err?.response?.status === 423) {
        setPrecisa2fa(true)
        setErro(precisa2fa ? 'Código inválido. Tente novamente.' : null)
      } else {
        setErro(loginError(err))
      }
    } finally {
      setCarregando(false)
    }
  }

  async function confirmarCadastro(e: FormEvent) {
    e.preventDefault()
    if (!cadastro) return
    setErro(null)
    setCarregando(true)
    try {
      const r = await saConfirmar2fa(cadastro.token, otp.trim())
      setCodigos({ token: r.token, admin: r.admin, recovery: r.recovery_codes })
      setCadastro(null)
    } catch (err: any) {
      setErro(err?.response?.data?.message ?? 'Não foi possível confirmar o código.')
    } finally {
      setCarregando(false)
    }
  }

  function seguir() {
    if (!codigos) return
    concluir(codigos.token, codigos.admin)
    navigate('/superadmin', { replace: true })
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    await entrar(email, senha, precisa2fa ? otp : undefined)
  }

  function preencherTeste() {
    if (!SA_EMAIL || !SA_SENHA) return
    setEmail(SA_EMAIL)
    setSenha(SA_SENHA)
    entrar(SA_EMAIL, SA_SENHA)
  }

  return (
    <div className="grid min-h-screen lg:grid-cols-2 bg-background">
      {/* Painel de marca — mesma linguagem do ERP (sidebar grafite + acento laranja) */}
      <div className="hidden lg:flex flex-col justify-between bg-sidebar p-10 text-sidebar-foreground">
        <div className="flex items-center gap-2.5">
          <div className="grid size-10 place-items-center rounded-lg bg-sidebar-accent text-primary-foreground shadow-md shadow-black/30">
            <ShieldCheck size={22} strokeWidth={2.2} />
          </div>
          <div className="leading-tight">
            <span className="text-lg font-bold tracking-wide text-white">{NOME_DA_PLATAFORMA}</span>
            <p className="text-[11px] uppercase tracking-wider text-sidebar-foreground">SuperAdmin</p>
          </div>
        </div>
        <div className="max-w-md space-y-3">
          <h2 className="text-3xl font-bold leading-tight text-white">
            Administração da <span className="text-sidebar-accent">plataforma</span>
          </h2>
          <p className="text-sm leading-relaxed text-sidebar-foreground">
            Gestão de empresas, planos, recursos e cidades, com histórico das ações administrativas.
          </p>
        </div>
        <p className="text-xs text-sidebar-foreground">
          Acesso restrito à administração da plataforma
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

          {codigos ? (
            <div className="space-y-4 rounded-xl border border-border bg-card p-6 shadow-sm">
              <h2 className="text-lg font-semibold">Guarde os códigos de recuperação</h2>
              <p className="text-sm text-muted-foreground">
                Cada código entra uma vez, se você perder o celular. Eles não serão mostrados de novo.
              </p>
              <ul aria-label="Códigos de recuperação" className="grid grid-cols-2 gap-2 font-mono text-sm">
                {codigos.recovery.map((c) => <li key={c} className="rounded bg-secondary px-2 py-1 text-center">{c}</li>)}
              </ul>
              <Button type="button" className="w-full" onClick={seguir}>Já guardei, continuar</Button>
            </div>
          ) : cadastro ? (
            <form aria-busy={carregando} onSubmit={confirmarCadastro} className="space-y-4 rounded-xl border border-border bg-card p-6 shadow-sm">
              <h2 className="text-lg font-semibold">Ative a verificação em duas etapas</h2>
              <p className="text-sm text-muted-foreground">
                Este acesso enxerga todas as revendas, por isso exige um segundo fator. No app autenticador
                (Google Authenticator, Microsoft Authenticator…), adicione uma conta com a chave abaixo.
              </p>
              <code aria-label="Chave do autenticador" className="block break-all rounded bg-secondary px-3 py-2 font-mono text-sm tracking-widest">{cadastro.secret}</code>
              <a href={cadastro.uri} className="block text-sm text-primary underline">Abrir no app autenticador deste aparelho</a>
              <Field label="Código de 6 dígitos do app" required>
                <Input autoFocus required inputMode="numeric" autoComplete="one-time-code" value={otp} onChange={(e) => setOtp(e.target.value)} placeholder="000000" disabled={carregando} />
              </Field>
              {erro && <p role="alert" className="text-sm text-destructive">{erro}</p>}
              <Button type="submit" className="w-full" disabled={carregando}>
                {carregando ? 'Confirmando…' : 'Confirmar e ativar'}
              </Button>
            </form>
          ) : (
          <form aria-busy={carregando} onSubmit={onSubmit} className="space-y-4 rounded-xl border border-border bg-card p-6 shadow-sm">
          <Field label="E-mail" required>
            <Input type="email" required autoComplete="username" value={email} onChange={(e) => setEmail(e.target.value)} disabled={carregando} />
          </Field>
          <Field label="Senha" required>
            <Input type="password" required autoComplete="current-password" value={senha} onChange={(e) => setSenha(e.target.value)} disabled={carregando} />
          </Field>
          {precisa2fa && (
            <Field label="Código de verificação (2FA)" required hint="Abra seu app autenticador ou use um código de recuperação.">
              <Input autoFocus required autoComplete="one-time-code" value={otp} onChange={(e) => setOtp(e.target.value)} placeholder="000000" disabled={carregando} />
            </Field>
          )}
          {erro && <p role="alert" className="text-sm text-destructive">{erro}</p>}
          <Button type="submit" className="w-full" disabled={carregando}>
            {carregando ? 'Entrando…' : precisa2fa ? 'Verificar e entrar' : 'Entrar'}
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
                {SA_EMAIL}
              </p>
            </div>
          )}
          </form>
          )}
        </div>
      </div>
    </div>
  )
}
