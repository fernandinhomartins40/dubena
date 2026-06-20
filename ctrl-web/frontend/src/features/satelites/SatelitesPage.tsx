import { FileBarChart, MapPin, Plug } from 'lucide-react'
import {
  Card, CardContent, PageHeader, Badge, EmptyState,
  Tabs, TabsList, TabsTrigger, TabsContent,
} from '@/components/ui'
import { useRelatorios, useMonitoramento, useIntegracoes } from './api'

export function SatelitesPage() {
  return (
    <div>
      <PageHeader title="Relatórios & Integrações" subtitle="Central de relatórios, monitoramento e integrações" />
      <Tabs defaultValue="relatorios">
        <TabsList>
          <TabsTrigger value="relatorios">Relatórios</TabsTrigger>
          <TabsTrigger value="monitoramento">Monitoramento</TabsTrigger>
          <TabsTrigger value="integracoes">Integrações</TabsTrigger>
        </TabsList>
        <TabsContent value="relatorios"><RelatoriosTab /></TabsContent>
        <TabsContent value="monitoramento"><MonitoramentoTab /></TabsContent>
        <TabsContent value="integracoes"><IntegracoesTab /></TabsContent>
      </Tabs>
    </div>
  )
}

function RelatoriosTab() {
  const { data, isLoading } = useRelatorios()
  if (isLoading) return <p className="text-sm text-muted-foreground">Carregando…</p>
  return (
    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
      {(data ?? []).map((cat: any) => (
        <Card key={cat.categoria}><CardContent className="pt-6">
          <div className="flex items-center gap-2 mb-3"><FileBarChart size={18} className="text-primary" /><p className="font-semibold">{cat.categoria}</p></div>
          <ul className="space-y-1.5">
            {cat.relatorios.map((r: string) => (
              <li key={r} className="flex items-center justify-between text-sm border-b border-border/60 pb-1.5">
                <span>{r}</span><Badge variant="secondary">PDF/XLSX</Badge>
              </li>
            ))}
          </ul>
        </CardContent></Card>
      ))}
    </div>
  )
}

function MonitoramentoTab() {
  const { data, isLoading } = useMonitoramento()
  if (isLoading) return <p className="text-sm text-muted-foreground">Carregando…</p>
  return (
    <Card><CardContent className="pt-6">
      <div className="flex items-center gap-3 mb-2"><MapPin size={20} className="text-primary" /><p className="font-semibold">Monitoramento GPS</p>{data?.disponivel ? <Badge variant="success">Disponível</Badge> : <Badge variant="secondary">Módulo dedicado</Badge>}</div>
      <p className="text-sm text-muted-foreground">{data?.observacao}</p>
    </CardContent></Card>
  )
}

function IntegracoesTab() {
  const { data, isLoading } = useIntegracoes()
  if (isLoading) return <p className="text-sm text-muted-foreground">Carregando…</p>
  const itens = [
    { k: 'pix', l: 'PIX (cobrança)' },
    { k: 'email_smtp', l: 'E-mail (SMTP)' },
    { k: 'google_maps', l: 'Google Maps' },
    { k: 'fcm_push', l: 'Notificações push (FCM)' },
  ]
  return (
    <div className="grid gap-4 md:grid-cols-2">
      {itens.map((i) => (
        <Card key={i.k}><CardContent className="pt-6 flex items-center justify-between">
          <div className="flex items-center gap-2"><Plug size={18} className="text-muted-foreground" /><p className="font-medium">{i.l}</p></div>
          {data?.[i.k] ? <Badge variant="success">Configurado</Badge> : <Badge variant="secondary">Não configurado</Badge>}
        </CardContent></Card>
      ))}
      {!data && <EmptyState icon={<Plug />} title="Sem dados de integração" />}
    </div>
  )
}
