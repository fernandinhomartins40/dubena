import { useEffect, useState, type FormEvent } from 'react'
import axios from 'axios'
import {
  Button, Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, Field, Input,
} from '@/components/ui'
import { apiPrefix, concluirReautenticacao, getToken, registrarDialogoDeSessao, setToken, TOKEN_KEY } from './api'
import { loginError } from './loginError'

/**
 * PIE-33 — diálogo de re-login quando o token vence no meio do trabalho.
 *
 * Fica montado no AppShell. Não navega: a tela atrás continua com o que foi
 * digitado, e a requisição que recebeu 401 é reenviada sozinha (ver
 * `lib/api.ts`). O e-mail é o da sessão e não se edita — entrar como OUTRA
 * pessoa aqui reenviaria o rascunho de um usuário com a credencial de outro.
 */
export function SessaoExpiradaDialog({ email }: { email: string }) {
  const [aberto, setAberto] = useState(false)
  const [senha, setSenha] = useState('')
  const [otp, setOtp] = useState('')
  const [precisa2fa, setPrecisa2fa] = useState(false)
  const [erro, setErro] = useState<string | null>(null)
  const [enviando, setEnviando] = useState(false)

  useEffect(() => registrarDialogoDeSessao(() => {
    setSenha(''); setOtp(''); setErro(null); setPrecisa2fa(false); setAberto(true)
  }), [])

  async function entrar(e: FormEvent) {
    e.preventDefault()
    setErro(null)
    setEnviando(true)
    try {
      const { data } = await axios.post(`${apiPrefix}/api/login`, { email, password: senha, ...(precisa2fa && otp ? { otp } : {}) }, {
        headers: { Accept: 'application/json' },
      })
      // Mantém o token no mesmo lugar do anterior ("manter conectado" ou não).
      const persistente = localStorage.getItem(TOKEN_KEY) !== null || getToken() === null
      setToken(data.token, persistente)
      setAberto(false)
      concluirReautenticacao(true)
    } catch (err: any) {
      if (err?.response?.status === 423 && err?.response?.data?.two_factor_required) {
        setErro(precisa2fa ? 'Código inválido. Tente novamente.' : null)
        setPrecisa2fa(true)
      } else {
        setErro(loginError(err))
      }
    } finally {
      setEnviando(false)
    }
  }

  function desistir() {
    setAberto(false)
    concluirReautenticacao(false)
  }

  return (
    <Dialog open={aberto} onOpenChange={(v) => { if (!v) desistir() }}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Sua sessão expirou</DialogTitle>
          <DialogDescription>
            Digite a senha de novo para continuar. O que você estava fazendo nesta tela não foi perdido.
          </DialogDescription>
        </DialogHeader>
        <form onSubmit={entrar} aria-busy={enviando} className="space-y-4">
          <Field label="E-mail">
            <Input value={email} readOnly autoComplete="username" />
          </Field>
          <Field label="Senha" required>
            <Input type="password" autoFocus required autoComplete="current-password" value={senha} onChange={(e) => setSenha(e.target.value)} disabled={enviando} />
          </Field>
          {precisa2fa && (
            <Field label="Código de verificação (2FA)" required>
              <Input required inputMode="numeric" autoComplete="one-time-code" value={otp} onChange={(e) => setOtp(e.target.value)} disabled={enviando} />
            </Field>
          )}
          {erro && <p role="alert" className="text-sm text-destructive">{erro}</p>}
          <DialogFooter>
            <Button type="button" variant="outline" onClick={desistir} disabled={enviando}>Agora não</Button>
            <Button type="submit" disabled={enviando}>{enviando ? 'Entrando…' : 'Entrar e continuar'}</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
