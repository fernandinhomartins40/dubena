import { useEffect, useState } from 'react'
import {
  Button, Card, CardContent, CardHeader, CardTitle, Field, Input, Switch, AsyncState, toast,
} from '@/components/ui'
import { useConfigGlobal, useSalvarConfigGlobal } from './api'

type Form = Record<string, string | number | boolean | null>

/**
 * Aba "Geral" do hub de Configurações (F01): config global do grupo —
 * Responsável Técnico (RT/CSRT, obrigatório p/ NF-e), SMTP global, SAT e Google Maps.
 * Segredos voltam como flag "*_definido"; o campo fica vazio com placeholder e só
 * é enviado se preenchido (não apaga o valor salvo).
 */
export function ConfigGlobalTab() {
  const { data, isLoading } = useConfigGlobal()
  const salvar = useSalvarConfigGlobal()
  const [form, setForm] = useState<Form>({})

  useEffect(() => {
    if (!data) return
    setForm({
      rt_cnpj: data.rt_cnpj ?? '', rt_contato: data.rt_contato ?? '', rt_email: data.rt_email ?? '',
      rt_telefone: data.rt_telefone ?? '', rt_id_csrt: data.rt_id_csrt ?? '', rt_csrt: '',
      email_remetente: data.email_remetente ?? '', email_nome_remetente: data.email_nome_remetente ?? '',
      email_host: data.email_host ?? '', email_porta: data.email_porta ?? '', email_usuario: data.email_usuario ?? '',
      email_senha: '', email_tls: data.email_tls,
      sat_cnpj_prod: data.sat_cnpj_prod ?? '', sat_cnpj_homolog: data.sat_cnpj_homolog ?? '',
      google_maps_key: data.google_maps_key ?? '', link_monitoramento: data.link_monitoramento ?? '',
    })
  }, [data])

  const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement>) => setForm((f) => ({ ...f, [k]: e.target.value }))

  async function onSalvar() {
    // Não envia segredos vazios (preserva o valor já salvo).
    const payload: Form = { ...form }
    for (const k of ['rt_csrt', 'email_senha']) if (!payload[k]) delete payload[k]
    try {
      await salvar.mutateAsync(payload)
      toast.success('Configuração global salva.')
    } catch {
      toast.error('Erro ao salvar a configuração.')
    }
  }

  if (isLoading) return <AsyncState loading skeletonRows={4}>{null}</AsyncState>

  const segredo = (def: boolean) => (def ? '•••••• (definido — deixe vazio p/ manter)' : '')

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader><CardTitle>Responsável Técnico (NF-e)</CardTitle></CardHeader>
        <CardContent className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <Field label="CNPJ do RT"><Input value={String(form.rt_cnpj ?? '')} onChange={set('rt_cnpj')} /></Field>
          <Field label="Contato"><Input value={String(form.rt_contato ?? '')} onChange={set('rt_contato')} /></Field>
          <Field label="E-mail"><Input value={String(form.rt_email ?? '')} onChange={set('rt_email')} /></Field>
          <Field label="Telefone"><Input value={String(form.rt_telefone ?? '')} onChange={set('rt_telefone')} /></Field>
          <Field label="ID CSRT"><Input value={String(form.rt_id_csrt ?? '')} onChange={set('rt_id_csrt')} /></Field>
          <Field label="CSRT"><Input value={String(form.rt_csrt ?? '')} onChange={set('rt_csrt')} placeholder={segredo(data?.rt_csrt_definido ?? false)} /></Field>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>E-mail (SMTP global)</CardTitle></CardHeader>
        <CardContent className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <Field label="Remetente"><Input value={String(form.email_remetente ?? '')} onChange={set('email_remetente')} /></Field>
          <Field label="Nome do remetente"><Input value={String(form.email_nome_remetente ?? '')} onChange={set('email_nome_remetente')} /></Field>
          <Field label="Host"><Input value={String(form.email_host ?? '')} onChange={set('email_host')} /></Field>
          <Field label="Porta"><Input type="number" value={String(form.email_porta ?? '')} onChange={set('email_porta')} /></Field>
          <Field label="Usuário"><Input value={String(form.email_usuario ?? '')} onChange={set('email_usuario')} /></Field>
          <Field label="Senha"><Input type="password" value={String(form.email_senha ?? '')} onChange={set('email_senha')} placeholder={segredo(data?.email_senha_definida ?? false)} /></Field>
          <Field label="TLS"><Switch checked={Boolean(form.email_tls)} onCheckedChange={(v) => setForm((f) => ({ ...f, email_tls: v }))} /></Field>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>SAT (CF-e) e Mapas</CardTitle></CardHeader>
        <CardContent className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <Field label="SAT CNPJ produção"><Input value={String(form.sat_cnpj_prod ?? '')} onChange={set('sat_cnpj_prod')} /></Field>
          <Field label="SAT CNPJ homologação"><Input value={String(form.sat_cnpj_homolog ?? '')} onChange={set('sat_cnpj_homolog')} /></Field>
          <Field label="Google Maps API key"><Input value={String(form.google_maps_key ?? '')} onChange={set('google_maps_key')} /></Field>
          <Field label="Link de monitoramento"><Input value={String(form.link_monitoramento ?? '')} onChange={set('link_monitoramento')} /></Field>
        </CardContent>
      </Card>

      <div className="flex justify-end">
        <Button onClick={onSalvar} loading={salvar.isPending}>Salvar configuração</Button>
      </div>
    </div>
  )
}
