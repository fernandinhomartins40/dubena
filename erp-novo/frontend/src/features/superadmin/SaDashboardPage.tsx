import { Building2, CheckCircle2, CreditCard, AlertTriangle, Package } from 'lucide-react'
import { PageHeader, StatCard, AsyncState, Card, CardHeader, CardTitle, CardContent } from '@/components/ui'
import { useSaDashboard } from './api'

/** Dashboard cross-tenant da plataforma (P4): KPIs agregados, read-only. */
export function SaDashboardPage() {
  const { data: d, isLoading, error } = useSaDashboard()

  return (
    <>
      <PageHeader title="Visão geral" subtitle="Indicadores agregados de toda a plataforma" />

      <AsyncState loading={isLoading} error={error} empty={!d}>
        {d && (
          <div className="space-y-6">
            <div className="grid grid-cols-2 gap-4 lg:grid-cols-3">
              <StatCard titulo="Empresas" valor={d.empresas_total} icon={Building2} accent="primary" />
              <StatCard titulo="Empresas ativas" valor={d.empresas_ativas} icon={CheckCircle2} accent="success" />
              <StatCard titulo="Assinaturas ativas" valor={d.assinaturas_ativas} icon={CreditCard} accent="lime" />
              <StatCard titulo="Inadimplentes" valor={d.assinaturas_inadimplentes} icon={AlertTriangle} accent="destructive" />
              <StatCard titulo="Planos" valor={d.planos} icon={Package} accent="neutral" />
            </div>

            <Card>
              <CardHeader>
                <CardTitle>Assinaturas por plano</CardTitle>
              </CardHeader>
              <CardContent>
                {Object.keys(d.por_plano ?? {}).length === 0 ? (
                  <p className="text-sm text-muted-foreground">Nenhuma assinatura ativa ainda.</p>
                ) : (
                  <ul className="divide-y divide-border">
                    {Object.entries(d.por_plano).map(([planoId, total]) => (
                      <li key={planoId} className="flex items-center justify-between py-2 text-sm">
                        <span className="text-muted-foreground">Plano #{planoId}</span>
                        <span className="font-semibold tabular-nums">{total}</span>
                      </li>
                    ))}
                  </ul>
                )}
              </CardContent>
            </Card>
          </div>
        )}
      </AsyncState>
    </>
  )
}
