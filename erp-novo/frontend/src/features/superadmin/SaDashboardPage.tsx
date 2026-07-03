import { Link } from 'react-router-dom'
import { Building2, CheckCircle2, CreditCard, AlertTriangle, Package, ScrollText, Ban, ArrowRight } from 'lucide-react'
import {
  PageHeader, StatCard, AsyncState, Badge,
  Card, CardHeader, CardTitle, CardContent,
} from '@/components/ui'
import { dataHora } from '@/lib/format'
import { useSaDashboard, useSaPlanos, useSaAuditoria, useSaEmpresas } from './api'

/**
 * Dashboard cross-tenant da plataforma (P4) — visão executiva: KPIs, distribuição
 * de assinaturas por plano (com NOME do plano e barra de proporção), empresas
 * suspensas e a atividade recente da auditoria. Mesmo nível do painel do ERP.
 */
export function SaDashboardPage() {
  const { data: d, isLoading, error } = useSaDashboard()
  const { data: planosData } = useSaPlanos()
  const { data: auditoria } = useSaAuditoria()
  const { data: empresas } = useSaEmpresas()

  const nomePlano = (id: string) =>
    planosData?.planos.find((p) => String(p.id) === String(id))?.nome ?? `Plano #${id}`

  const suspensas = (empresas ?? []).filter((e) => !e.ativo)
  const atividade = (auditoria ?? []).slice(0, 8)
  const totalAssinaturas = Object.values(d?.por_plano ?? {}).reduce((a, b) => a + Number(b), 0)

  return (
    <>
      <PageHeader title="Visão geral" subtitle="Indicadores agregados de toda a plataforma" />

      <AsyncState loading={isLoading} error={error} empty={!d}>
        {d && (
          <div className="space-y-6">
            <div className="grid grid-cols-2 gap-4 lg:grid-cols-5">
              <StatCard titulo="Empresas" valor={d.empresas_total} icon={Building2} accent="primary" />
              <StatCard titulo="Ativas" valor={d.empresas_ativas} icon={CheckCircle2} accent="success" />
              <StatCard titulo="Assinaturas" valor={d.assinaturas_ativas} icon={CreditCard} accent="lime" />
              <StatCard titulo="Inadimplentes" valor={d.assinaturas_inadimplentes} icon={AlertTriangle} accent="destructive" />
              <StatCard titulo="Planos" valor={d.planos} icon={Package} accent="neutral" />
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
              {/* Distribuição por plano */}
              <Card>
                <CardHeader className="flex-row items-center justify-between">
                  <CardTitle>Assinaturas por plano</CardTitle>
                  <Link to="/superadmin/planos" className="text-xs font-medium text-primary hover:underline inline-flex items-center gap-1">
                    Gerir planos <ArrowRight size={12} />
                  </Link>
                </CardHeader>
                <CardContent>
                  {totalAssinaturas === 0 ? (
                    <p className="text-sm text-muted-foreground">Nenhuma assinatura ativa ainda.</p>
                  ) : (
                    <ul className="space-y-3">
                      {Object.entries(d.por_plano).map(([planoId, total]) => {
                        const pct = Math.round((Number(total) / totalAssinaturas) * 100)
                        return (
                          <li key={planoId}>
                            <div className="mb-1 flex items-center justify-between text-sm">
                              <span className="font-medium">{nomePlano(planoId)}</span>
                              <span className="tabular-nums text-muted-foreground">{total} · {pct}%</span>
                            </div>
                            <div className="h-2 rounded-full bg-secondary overflow-hidden">
                              <div className="h-full rounded-full bg-primary transition-all" style={{ width: `${pct}%` }} />
                            </div>
                          </li>
                        )
                      })}
                    </ul>
                  )}
                </CardContent>
              </Card>

              {/* Empresas suspensas */}
              <Card>
                <CardHeader className="flex-row items-center justify-between">
                  <CardTitle>Empresas suspensas</CardTitle>
                  <Link to="/superadmin/empresas" className="text-xs font-medium text-primary hover:underline inline-flex items-center gap-1">
                    Ver todas <ArrowRight size={12} />
                  </Link>
                </CardHeader>
                <CardContent>
                  {suspensas.length === 0 ? (
                    <p className="text-sm text-muted-foreground">Nenhuma empresa suspensa. 🎉</p>
                  ) : (
                    <ul className="divide-y divide-border">
                      {suspensas.slice(0, 6).map((e) => (
                        <li key={e.id} className="flex items-center justify-between py-2">
                          <div className="min-w-0">
                            <p className="truncate text-sm font-medium">{e.nome_fantasia || e.razao_social}</p>
                            <p className="truncate text-xs text-muted-foreground">{e.cnpj ?? '—'}</p>
                          </div>
                          <Badge variant="destructive" className="gap-1 shrink-0"><Ban size={11} /> Suspensa</Badge>
                        </li>
                      ))}
                    </ul>
                  )}
                </CardContent>
              </Card>
            </div>

            {/* Atividade recente (auditoria) */}
            <Card>
              <CardHeader className="flex-row items-center justify-between">
                <CardTitle>Atividade recente</CardTitle>
                <Link to="/superadmin/auditoria" className="text-xs font-medium text-primary hover:underline inline-flex items-center gap-1">
                  Trilha completa <ArrowRight size={12} />
                </Link>
              </CardHeader>
              <CardContent>
                {atividade.length === 0 ? (
                  <p className="text-sm text-muted-foreground">Sem ações registradas ainda.</p>
                ) : (
                  <ul className="divide-y divide-border">
                    {atividade.map((a) => (
                      <li key={a.id} className="flex items-center gap-3 py-2">
                        <div className="grid size-8 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
                          <ScrollText size={14} />
                        </div>
                        <div className="min-w-0 flex-1">
                          <p className="truncate text-sm">
                            <span className="font-medium">{a.admin ?? 'Sistema'}</span>{' '}
                            <Badge variant="secondary" className="mx-1">{a.acao}</Badge>
                            {a.entidade ? <span className="text-muted-foreground">{a.entidade}{a.entidade_id ? ` #${a.entidade_id}` : ''}</span> : null}
                          </p>
                        </div>
                        <span className="shrink-0 tabular-nums text-xs text-muted-foreground">{dataHora(a.criado_em)}</span>
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
