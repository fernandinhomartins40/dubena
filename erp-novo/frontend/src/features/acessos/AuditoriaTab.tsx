import { useState } from 'react'
import { Badge, Tabs, TabsList, TabsTrigger, TabsContent, type Column, ResourceList, CheckboxField } from '@/components/ui'
import { ShieldAlert, LogIn } from 'lucide-react'
import { dataHora as fmtDataHora } from '@/lib/format'
import { useEventosSeguranca, useLoginLogs, type EventoSeguranca, type LoginLogItem } from './api'

/** Rótulos amigáveis para os tipos de evento de segurança. */
const ROTULO: Record<string, string> = {
  'papel.criado': 'Perfil criado',
  'papel.editado': 'Perfil editado',
  'papel.excluido': 'Perfil excluído',
  'usuario.papeis': 'Papéis do usuário alterados',
  'usuario.senha_resetada': 'Senha redefinida',
  '2fa.habilitado': '2FA habilitado',
  '2fa.desabilitado': '2FA desabilitado',
  'autorizacao.negada': 'Acesso negado (403)',
}

/**
 * Auditoria de segurança (A6) — trilha de eventos sensíveis e histórico de login.
 * Somente leitura; gated por auditoria.view (a aba só aparece com a permissão).
 */
export function AuditoriaTab() {
  return (
    <Tabs defaultValue="eventos">
      <TabsList>
        <TabsTrigger value="eventos">Eventos</TabsTrigger>
        <TabsTrigger value="logins">Logins</TabsTrigger>
      </TabsList>
      <TabsContent value="eventos"><EventosPanel /></TabsContent>
      <TabsContent value="logins"><LoginsPanel /></TabsContent>
    </Tabs>
  )
}

function EventosPanel() {
  const { data, isLoading } = useEventosSeguranca()

  const columns: Column<EventoSeguranca>[] = [
    { key: 'criado_em', header: 'Quando', cell: (v) => fmtDataHora(v.criado_em) },
    {
      key: 'tipo', header: 'Evento',
      cell: (v) => v.tipo === 'autorizacao.negada'
        ? <Badge variant="destructive">{ROTULO[v.tipo] ?? v.tipo}</Badge>
        : <span className="font-medium">{ROTULO[v.tipo] ?? v.tipo}</span>,
    },
    { key: 'alvo', header: 'Alvo', cell: (v) => v.alvo ?? '—' },
    { key: 'autor', header: 'Autor', cell: (v) => v.autor ?? '—' },
    { key: 'ip', header: 'IP', cell: (v) => v.ip ?? '—' },
  ]

  return (
    <ResourceList
      title="Eventos de segurança"
      subtitle="Mudanças de acesso e tentativas negadas"
      columns={columns}
      rows={data}
      loading={isLoading}
      rowKey={(v) => v.id}
      emptyIcon={<ShieldAlert />}
      emptyTitle="Sem eventos"
      emptyDescription="Ações sensíveis de acesso aparecerão aqui."
    />
  )
}

function LoginsPanel() {
  const [apenasFalhas, setApenasFalhas] = useState(false)
  const { data, isLoading } = useLoginLogs(apenasFalhas)

  const columns: Column<LoginLogItem>[] = [
    { key: 'criado_em', header: 'Quando', cell: (v) => fmtDataHora(v.criado_em) },
    { key: 'email', header: 'E-mail', cell: (v) => v.email ?? '—' },
    {
      key: 'sucesso', header: 'Resultado',
      cell: (v) => v.sucesso
        ? <Badge variant="success">Sucesso</Badge>
        : <Badge variant="destructive">Falha</Badge>,
    },
    { key: 'motivo', header: 'Motivo', cell: (v) => v.motivo ?? '—' },
    { key: 'ip', header: 'IP', cell: (v) => v.ip ?? '—' },
  ]

  return (
    <ResourceList
      title="Histórico de login"
      subtitle="Tentativas de acesso à conta"
      action={<CheckboxField label="Apenas falhas" checked={apenasFalhas} onChange={setApenasFalhas} />}
      columns={columns}
      rows={data}
      loading={isLoading}
      rowKey={(v) => v.id}
      emptyIcon={<LogIn />}
      emptyTitle="Sem registros"
      emptyDescription="As tentativas de login aparecerão aqui."
    />
  )
}
