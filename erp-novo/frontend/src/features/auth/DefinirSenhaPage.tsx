import { useState, type FormEvent } from 'react'
import axios from 'axios'
import { Link, useSearchParams } from 'react-router-dom'
import { Button, Field, Input } from '@/components/ui'
import { apiPrefix, ensureCsrf } from '@/lib/api'
import { NOME_DA_PLATAFORMA } from '@/lib/marca'

/**
 * PIE P2-A-01 — o dono da revenda nova define a própria senha pelo convite.
 *
 * O link (com e-mail e token de uso único) é entregue pelo operador da
 * plataforma. Não existe senha provisória: quem cria a revenda nunca conhece a
 * senha do dono.
 */
export function DefinirSenhaPage() {
  const [params] = useSearchParams()
  const email = params.get('email') ?? ''
  const token = params.get('token') ?? ''
  const [senha, setSenha] = useState('')
  const [confirmacao, setConfirmacao] = useState('')
  const [erro, setErro] = useState<string | null>(null)
  const [enviando, setEnviando] = useState(false)
  const [pronto, setPronto] = useState(false)

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    if (senha !== confirmacao) { setErro('As senhas não conferem.'); return }
    setErro(null)
    setEnviando(true)
    try {
      await ensureCsrf()
      await axios.post(`${apiPrefix}/api/definir-senha`, { email, token, password: senha, password_confirmation: confirmacao },
        { withCredentials: true, withXSRFToken: true, headers: { Accept: 'application/json' } })
      setPronto(true)
    } catch (err: any) {
      const d = err?.response?.data
      setErro((d?.errors && (Object.values(d.errors)[0] as string[] | undefined)?.[0]) ?? d?.message ?? 'Não foi possível definir a senha.')
    } finally {
      setEnviando(false)
    }
  }

  return (
    <div className="min-h-full grid place-items-center bg-[hsl(0_0%_10%)] p-4">
      <div className="w-full max-w-sm overflow-hidden rounded-2xl border border-white/10 bg-card shadow-2xl">
        <div className="h-1.5 bg-primary" />
        <div className="space-y-5 p-8">
          <div>
            <h1 className="text-xl font-bold tracking-tight text-foreground">{NOME_DA_PLATAFORMA}</h1>
            <p className="text-sm text-muted-foreground">Defina a sua senha de acesso</p>
          </div>
          {!email || !token ? (
            <p role="alert" className="text-sm text-destructive">Link de convite incompleto. Peça um novo link a quem criou a sua revenda.</p>
          ) : pronto ? (
            <div className="space-y-4">
              <p role="status" className="text-sm">Senha definida. Entre com <strong>{email}</strong> e a senha que você escolheu.</p>
              <Link to="/login" className="font-medium text-accent-foreground underline underline-offset-4">Ir para o login</Link>
            </div>
          ) : (
            <form aria-busy={enviando} onSubmit={onSubmit} className="space-y-4">
              <Field label="E-mail"><Input value={email} readOnly /></Field>
              <Field label="Nova senha" required>
                <Input type="password" autoComplete="new-password" value={senha} onChange={(e) => setSenha(e.target.value)} required />
              </Field>
              <Field label="Repita a senha" required>
                <Input type="password" autoComplete="new-password" value={confirmacao} onChange={(e) => setConfirmacao(e.target.value)} required />
              </Field>
              {erro && <p role="alert" className="text-sm text-destructive">{erro}</p>}
              <Button type="submit" className="w-full" loading={enviando}>Definir senha</Button>
            </form>
          )}
        </div>
      </div>
    </div>
  )
}
