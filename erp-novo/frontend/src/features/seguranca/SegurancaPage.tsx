import { useState } from 'react'
import { ShieldCheck, Smartphone, KeyRound, Monitor, LogOut } from 'lucide-react'
import {
  PageHeader, Card, CardHeader, CardTitle, CardDescription, CardContent,
  Button, Input, Field, Badge, FormDialog, toast,
} from '@/components/ui'
import { data as fmtData } from '@/lib/format'
import {
  useTwoFactorStatus, useTwoFactorSetup, useTwoFactorConfirm, useTwoFactorDisable,
  useSessoes, useRevogarSessao, useRevogarOutras,
} from './api'

/**
 * Segurança da conta (A5): ativar/desativar 2FA (TOTP) e gerir sessões ativas.
 * Opera sobre o próprio usuário — não exige permissão de admin.
 */
export function SegurancaPage() {
  return (
    <div className="space-y-6">
      <PageHeader title="Segurança" subtitle="Verificação em duas etapas e sessões ativas" />
      <DoisFatoresCard />
      <SessoesCard />
    </div>
  )
}

function DoisFatoresCard() {
  const { data: status } = useTwoFactorStatus()
  const setup = useTwoFactorSetup()
  const confirmar = useTwoFactorConfirm()
  const desabilitar = useTwoFactorDisable()

  const [secret, setSecret] = useState<string | null>(null)
  const [otp, setOtp] = useState('')
  const [recovery, setRecovery] = useState<string[] | null>(null)
  const [disableOpen, setDisableOpen] = useState(false)
  const [senha, setSenha] = useState('')

  async function iniciar() {
    try {
      const r = await setup.mutateAsync()
      setSecret(r.secret); setRecovery(null); setOtp('')
    } catch { toast.error('Não foi possível iniciar o 2FA.') }
  }

  async function confirmar2fa() {
    try {
      const r = await confirmar.mutateAsync(otp)
      setRecovery(r.recovery_codes); setSecret(null)
      toast.success('2FA habilitado.')
    } catch { toast.error('Código inválido.') }
  }

  async function desligar() {
    try {
      await desabilitar.mutateAsync(senha)
      setDisableOpen(false); setSenha('')
      toast.success('2FA desabilitado.')
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Senha incorreta.') }
  }

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <div>
            <CardTitle className="flex items-center gap-2"><ShieldCheck size={18} /> Verificação em duas etapas</CardTitle>
            <CardDescription>Um código do seu app autenticador, além da senha.</CardDescription>
          </div>
          {status?.habilitado
            ? <Badge variant="success">Ativada</Badge>
            : <Badge variant="secondary">Desativada</Badge>}
        </div>
      </CardHeader>
      <CardContent className="space-y-4">
        {status?.habilitado && !recovery && (
          <Button variant="outline" onClick={() => setDisableOpen(true)}>Desativar 2FA</Button>
        )}

        {!status?.habilitado && !secret && !recovery && (
          <Button loading={setup.isPending} onClick={iniciar}><Smartphone size={16} /> Ativar 2FA</Button>
        )}

        {secret && (
          <div className="space-y-3 rounded-md border border-border p-4">
            <p className="text-sm">1. No app autenticador (Google Authenticator, Authy…), adicione uma conta e digite esta chave:</p>
            <code className="block rounded bg-secondary px-3 py-2 font-mono text-sm tracking-widest break-all">{secret}</code>
            <p className="text-sm">2. Informe o código gerado para confirmar:</p>
            <div className="flex items-end gap-2">
              <Field label="Código"><Input value={otp} onChange={(e) => setOtp(e.target.value)} placeholder="000000" inputMode="numeric" /></Field>
              <Button loading={confirmar.isPending} onClick={confirmar2fa}>Confirmar</Button>
            </div>
          </div>
        )}

        {recovery && (
          <div className="space-y-2 rounded-md border border-border p-4">
            <p className="flex items-center gap-2 text-sm font-medium"><KeyRound size={16} /> Códigos de recuperação</p>
            <p className="text-sm text-muted-foreground">Guarde em local seguro. Cada código serve uma vez, caso perca o app.</p>
            <div className="grid grid-cols-2 gap-1 font-mono text-sm">
              {recovery.map((c) => <code key={c} className="rounded bg-secondary px-2 py-1">{c}</code>)}
            </div>
          </div>
        )}
      </CardContent>

      <FormDialog
        open={disableOpen} onOpenChange={setDisableOpen}
        title="Desativar 2FA" confirmLabel="Desativar"
        loading={desabilitar.isPending} onConfirm={desligar}
      >
        <p className="text-sm text-muted-foreground">Confirme com sua senha para desativar a verificação em duas etapas.</p>
        <Field label="Senha" required><Input type="password" value={senha} onChange={(e) => setSenha(e.target.value)} /></Field>
      </FormDialog>
    </Card>
  )
}

function SessoesCard() {
  const { data: sessoes, isLoading } = useSessoes()
  const revogar = useRevogarSessao()
  const revogarOutras = useRevogarOutras()

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <div>
            <CardTitle className="flex items-center gap-2"><Monitor size={18} /> Sessões ativas</CardTitle>
            <CardDescription>Dispositivos/aplicativos conectados à sua conta.</CardDescription>
          </div>
          <Button variant="outline" size="sm" onClick={() => revogarOutras.mutate()} loading={revogarOutras.isPending}>
            <LogOut size={15} /> Encerrar outras
          </Button>
        </div>
      </CardHeader>
      <CardContent>
        {isLoading && <p className="text-sm text-muted-foreground">Carregando…</p>}
        <div className="space-y-2">
          {(sessoes ?? []).map((s) => (
            <div key={s.id} className="flex items-center justify-between rounded-md border border-border px-3 py-2 text-sm">
              <div>
                <span className="font-medium">{s.name}</span>
                {s.atual && <Badge variant="success" className="ml-2">Esta sessão</Badge>}
                <p className="text-xs text-muted-foreground">
                  Último uso: {s.last_used_at ? fmtData(s.last_used_at) : '—'} · Criada: {s.created_at ? fmtData(s.created_at) : '—'}
                </p>
              </div>
              {!s.atual && (
                <Button variant="ghost" size="sm" onClick={() => revogar.mutate(s.id)}>Revogar</Button>
              )}
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  )
}
