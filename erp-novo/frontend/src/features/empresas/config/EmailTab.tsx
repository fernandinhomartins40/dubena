import { Card, CardContent, Field, Input, CheckboxField } from '@/components/ui'
import type { ConfigSubtabProps } from './types'

export function EmailTab({ form, campo }: ConfigSubtabProps) {
  return (
    <Card><CardContent className="pt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
      <Field label="Remetente (e-mail)"><Input value={form.emailremetente ?? ''} onChange={(e) => campo('emailremetente', e.target.value)} /></Field>
      <Field label="Nome do remetente"><Input value={form.emailnomeremente ?? ''} onChange={(e) => campo('emailnomeremente', e.target.value)} /></Field>
      <Field label="Usuário SMTP"><Input value={form.emailusuario ?? ''} onChange={(e) => campo('emailusuario', e.target.value)} /></Field>
      <Field label="Senha SMTP" hint="Deixe em branco para manter a atual"><Input type="password" value={form.emailsenha ?? ''} onChange={(e) => campo('emailsenha', e.target.value)} /></Field>
      <Field label="Servidor SMTP"><Input value={form.emailservidorsmtp ?? ''} onChange={(e) => campo('emailservidorsmtp', e.target.value)} /></Field>
      <Field label="Porta SMTP"><Input type="number" value={form.emailportasmtp ?? ''} onChange={(e) => campo('emailportasmtp', e.target.value)} /></Field>
      <Field label="Assunto padrão"><Input value={form.emailassunto ?? ''} onChange={(e) => campo('emailassunto', e.target.value)} /></Field>
      <div className="flex items-end gap-4">
        <CheckboxField label="Requer autenticação" checked={!!form.emailrequerautenticacao} onChange={(b) => campo('emailrequerautenticacao', b)} />
        <CheckboxField label="Conexão TLS" checked={!!form.emailrequerconexaotls} onChange={(b) => campo('emailrequerconexaotls', b)} />
      </div>
    </CardContent></Card>
  )
}
