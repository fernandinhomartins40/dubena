import { useMemo, useState } from 'react'
import { ScrollText, RefreshCw } from 'lucide-react'
import { type Column, Badge, Button, ResourceList, SearchBar } from '@/components/ui'
import { dataHora } from '@/lib/format'
import { useSaAuditoria, type SaAuditoria } from './api'
import { useQueryClient } from '@tanstack/react-query'

/** Cor do badge conforme a natureza da ação (suspender=destrutiva etc.). */
function acaoBadge(acao: string) {
  const a = acao.toLowerCase()
  if (/suspend|remov|delet|cancel/.test(a)) return <Badge variant="destructive">{acao}</Badge>
  if (/reativ|criar|habilit/.test(a)) return <Badge variant="success">{acao}</Badge>
  return <Badge variant="secondary">{acao}</Badge>
}

/** Trilha de auditoria cross-tenant (platform_audit_logs) — P4. Read-only. */
export function SaAuditoriaPage() {
  const { data, isLoading, isFetching } = useSaAuditoria()
  const qc = useQueryClient()
  const [q, setQ] = useState('')

  // Filtro client-side (a trilha já vem limitada às 200 mais recentes).
  const linhas = useMemo(() => {
    if (!q.trim()) return data
    const t = q.trim().toLowerCase()
    return (data ?? []).filter((v) =>
      [v.acao, v.admin, v.entidade, v.ip, String(v.empresa_id ?? '')]
        .some((campo) => (campo ?? '').toLowerCase().includes(t)),
    )
  }, [data, q])

  const columns: Column<SaAuditoria>[] = [
    { key: 'criado_em', header: 'Quando', cell: (v) => <span className="tabular-nums text-sm">{dataHora(v.criado_em)}</span> },
    { key: 'admin', header: 'Operador', cell: (v) => v.admin ?? '—' },
    { key: 'acao', header: 'Ação', cell: (v) => acaoBadge(v.acao) },
    { key: 'empresa', header: 'Empresa', cell: (v) => v.empresa_id ? `#${v.empresa_id}` : '—' },
    { key: 'entidade', header: 'Entidade', cell: (v) => v.entidade ? `${v.entidade}${v.entidade_id ? ` #${v.entidade_id}` : ''}` : '—' },
    { key: 'ip', header: 'IP', cell: (v) => <span className="text-sm text-muted-foreground">{v.ip ?? '—'}</span> },
  ]

  return (
    <ResourceList
      title="Auditoria"
      subtitle="Toda ação cross-tenant do SuperAdmin (append-only, 200 mais recentes)"
      filtros={
        <div className="flex items-center gap-2">
          <SearchBar value={q} onChange={setQ} onSearch={() => {}} placeholder="Filtrar por operador, ação, entidade, IP…" />
          <Button
            variant="outline"
            size="icon"
            aria-label="Atualizar"
            onClick={() => qc.invalidateQueries({ queryKey: ['sa', 'auditoria'] })}
          >
            <RefreshCw size={15} className={isFetching ? 'animate-spin' : undefined} />
          </Button>
        </div>
      }
      columns={columns}
      rows={linhas}
      loading={isLoading}
      rowKey={(v) => v.id}
      emptyIcon={<ScrollText />}
      emptyTitle="Sem registros"
      emptyDescription={q ? 'Nada corresponde ao filtro.' : 'As ações da plataforma aparecerão aqui.'}
    />
  )
}
