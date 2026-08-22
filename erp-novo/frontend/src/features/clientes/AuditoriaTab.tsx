import { History, ArrowRight, ShieldAlert } from 'lucide-react'
import { useState } from 'react'
import { EmptyState, AsyncState, Badge } from '@/components/ui'
import { dataHora as fmtDataHora } from '@/lib/format'
import { useTrilhaRegistro, type AcaoTrilha } from '@/features/auditoria/api'

/**
 * Auditoria do CADASTRO do cliente — quem alterou, desativou ou reativou.
 *
 * Distinta da aba "Histórico", que lista as COMPRAS dele. Aqui a pergunta é
 * "quem mexeu neste cadastro e por quê" — a que levava o operador a escrever o
 * estado no nome do cliente por não ter onde registrar a decisão.
 */
export function AuditoriaTab({ clienteId }: { clienteId: number }) {
  const { data, isLoading, error } = useTrilhaRegistro('clientes', clienteId)
  const [acaoFoco, setAcaoFoco] = useState<string | null>(null)

  const acoes = data?.data ?? []
  const visiveis = acaoFoco ? acoes.filter((a) => a.acao === acaoFoco) : acoes

  return (
    <AsyncState loading={isLoading} error={error} skeletonRows={4}>
      {!acoes.length ? (
        <EmptyState
          icon={<History />}
          title="Sem alterações registradas"
          description="Nenhuma ação foi feita neste cadastro desde que a auditoria passou a registrar."
        />
      ) : (
        <div className="space-y-4">
          {/* Agrupamento por tipo de ação — clicar filtra a linha do tempo. */}
          <div className="flex flex-wrap gap-2">
            <Filtro ativo={acaoFoco === null} onClick={() => setAcaoFoco(null)} rotulo="Tudo" total={acoes.length} />
            {data?.resumo.map((r) => (
              <Filtro
                key={r.acao}
                ativo={acaoFoco === r.acao}
                onClick={() => setAcaoFoco(acaoFoco === r.acao ? null : r.acao)}
                rotulo={r.rotulo}
                total={r.total}
                sensivel={r.sensivel}
              />
            ))}
          </div>

          <ol className="relative ml-2 space-y-0 border-l pl-6">
            {visiveis.map((a) => <Item key={a.id} acao={a} />)}
          </ol>
        </div>
      )}
    </AsyncState>
  )
}

function Filtro({
  rotulo, total, ativo, sensivel, onClick,
}: { rotulo: string; total: number; ativo: boolean; sensivel?: boolean; onClick: () => void }) {
  return (
    <button
      onClick={onClick}
      className={[
        'flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm transition-colors',
        ativo ? 'border-primary bg-primary/10 font-medium' : 'hover:bg-secondary/60',
      ].join(' ')}
    >
      {sensivel && <ShieldAlert size={14} className="text-destructive" />}
      {rotulo}
      <span className="rounded bg-secondary px-1.5 text-xs tabular-nums text-muted-foreground">{total}</span>
    </button>
  )
}

function Item({ acao }: { acao: AcaoTrilha }) {
  const [aberto, setAberto] = useState(false)

  return (
    <li className="relative py-3">
      <span
        className={[
          'absolute -left-[31px] top-5 size-3 rounded-full ring-4 ring-background',
          acao.sensivel ? 'bg-destructive' : 'bg-muted-foreground/40',
        ].join(' ')}
      />
      <div className="rounded-md border p-3">
        <div className="flex flex-wrap items-baseline gap-x-2 gap-y-1">
          <span className="font-medium">{acao.autor ?? 'Sistema'}</span>
          <span className={acao.sensivel ? 'font-medium text-destructive' : 'text-muted-foreground'}>
            {acao.acao_rotulo.toLowerCase()}
          </span>
          {acao.sensivel && <Badge variant="secondary">decisão</Badge>}
          <span className="ml-auto text-sm text-muted-foreground tabular-nums">{fmtDataHora(acao.criado_em)}</span>
        </div>

        {acao.motivo && (
          <div className="mt-2 rounded bg-secondary/60 px-2 py-1 text-sm">
            <span className="text-muted-foreground">Motivo: </span>{acao.motivo}
          </div>
        )}

        {acao.alteracoes.length > 0 && (
          <>
            <button
              className="mt-2 text-sm text-muted-foreground underline-offset-4 hover:underline"
              onClick={() => setAberto((v) => !v)}
            >
              {aberto ? 'Ocultar' : `Ver o que mudou (${acao.alteracoes.length})`}
            </button>
            {aberto && (
              <div className="mt-2 space-y-1 border-t pt-2">
                {acao.alteracoes.map((m) => (
                  <div key={m.campo} className="flex flex-wrap items-center gap-2 text-sm">
                    <span className="text-muted-foreground">{m.rotulo}:</span>
                    <span className="line-through opacity-60">{m.de}</span>
                    <ArrowRight size={12} className="text-muted-foreground" />
                    <span className="font-medium">{m.para}</span>
                  </div>
                ))}
                {acao.ip && <div className="pt-1 text-xs text-muted-foreground">IP {acao.ip}</div>}
              </div>
            )}
          </>
        )}
      </div>
    </li>
  )
}
