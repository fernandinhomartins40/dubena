import { Badge } from '@/components/ui'

/** Badge de situação do pedido (compartilhado por Lista e Ficha). FE-4. */
export function situacaoBadge(p: { fechadoconcluido?: number; fechadocancelado?: number; situacao?: string | null; descricao?: string }) {
  const label = p.situacao ?? p.descricao ?? '—'
  if (Number(p.fechadocancelado)) return <Badge variant="destructive">{label}</Badge>
  if (Number(p.fechadoconcluido)) return <Badge variant="success">{label}</Badge>
  return <Badge variant="warning">{label}</Badge>
}
