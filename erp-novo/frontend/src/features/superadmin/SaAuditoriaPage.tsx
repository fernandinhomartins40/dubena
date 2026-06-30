import { ScrollText } from 'lucide-react'
import { type Column, Badge, ResourceList } from '@/components/ui'
import { dataHora } from '@/lib/format'
import { useSaAuditoria, type SaAuditoria } from './api'

/** Trilha de auditoria cross-tenant (platform_audit_logs) — P4. Read-only. */
export function SaAuditoriaPage() {
  const { data, isLoading } = useSaAuditoria()

  const columns: Column<SaAuditoria>[] = [
    { key: 'criado_em', header: 'Quando', cell: (v) => <span className="tabular-nums text-sm">{dataHora(v.criado_em)}</span> },
    { key: 'admin', header: 'Operador', cell: (v) => v.admin ?? '—' },
    { key: 'acao', header: 'Ação', cell: (v) => <Badge variant="secondary">{v.acao}</Badge> },
    { key: 'empresa', header: 'Empresa', cell: (v) => v.empresa_id ? `#${v.empresa_id}` : '—' },
    { key: 'entidade', header: 'Entidade', cell: (v) => v.entidade ? `${v.entidade}${v.entidade_id ? ` #${v.entidade_id}` : ''}` : '—' },
    { key: 'ip', header: 'IP', cell: (v) => <span className="text-sm text-muted-foreground">{v.ip ?? '—'}</span> },
  ]

  return (
    <ResourceList
      title="Auditoria"
      subtitle="Toda ação cross-tenant do SuperAdmin (append-only, 200 mais recentes)"
      columns={columns}
      rows={data}
      loading={isLoading}
      rowKey={(v) => v.id}
      emptyIcon={<ScrollText />}
      emptyTitle="Sem registros"
      emptyDescription="As ações da plataforma aparecerão aqui."
    />
  )
}
