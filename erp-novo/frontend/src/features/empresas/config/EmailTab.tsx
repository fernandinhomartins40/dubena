import { Card, CardContent, Field, Input, CheckboxField } from '@/components/ui'
import type { ConfigSubtabProps } from './types'

export function EmailTab({ form, campo }: ConfigSubtabProps) {
  return (
    <Card><CardContent className="pt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
      <Field label="Remetente (e-mail)"><Input value={form.email_from_address ?? ''} onChange={(e) => campo('email_from_address', e.target.value)} /></Field>
      <Field label="Nome do remetente"><Input value={form.email_from_name ?? ''} onChange={(e) => campo('email_from_name', e.target.value)} /></Field>
      <Field label="Usuário SMTP"><Input value={form.email_username ?? ''} onChange={(e) => campo('email_username', e.target.value)} /></Field>
      <Field label="Senha SMTP" hint="Deixe em branco para manter a atual"><Input type="password" value={form.email_password ?? ''} onChange={(e) => campo('email_password', e.target.value)} /></Field>
      <Field label="Servidor SMTP"><Input value={form.email_host ?? ''} onChange={(e) => campo('email_host', e.target.value)} /></Field>
      <Field label="Porta SMTP"><Input type="number" value={form.email_port ?? ''} onChange={(e) => campo('email_port', e.target.value)} /></Field>
      <Field label="Assunto padrão"><Input value={form.email_assunto ?? ''} onChange={(e) => campo('email_assunto', e.target.value)} /></Field>
      <div className="flex items-end gap-4">
        <CheckboxField label="Requer autenticação" checked={!!form.email_requer_autenticacao} onChange={(b) => campo('email_requer_autenticacao', b)} />
        <CheckboxField label="Conexão TLS" checked={!!form.email_requer_tls} onChange={(b) => campo('email_requer_tls', b)} />
      </div>
    </CardContent></Card>
  )
}
