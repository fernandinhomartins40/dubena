import { PhoneIncoming, PhoneOff, UserCheck } from 'lucide-react'
import { Button, Badge, Card, CardContent, toast } from '@/components/ui'
import { useFilaChamadas, useAtenderChamada, useRejeitarChamada, type ChamadaEntrante } from './api'

/**
 * Painel de chamadas entrantes — a bina (T4.4).
 *
 * ⚠️ Condicionado à decisão do dono: se o call-center não usa bina, este
 * componente e a chamada dele em PedidosPage saem juntos.
 *
 * Fica na tela de pedidos porque é onde o atendente está quando o telefone
 * toca — o objetivo é ir da chamada ao pedido sem trocar de tela.
 *
 * **Some quando não há chamada.** Um painel vazio permanente vira ruído que o
 * operador aprende a ignorar; quando reaparecer com uma chamada de verdade, ele
 * não vai olhar.
 */
export function PainelChamadas({ onAbrirCliente }: { onAbrirCliente?: (clienteId: number) => void }) {
  // Polling: a SPA ainda não assina canais do Echo (a auditoria registra que
  // `laravel-echo` não aparece em frontend/src). 5s é o intervalo em que o
  // atendente ainda pega a chamada tocando.
  const { data } = useFilaChamadas(5000)
  const atender = useAtenderChamada()
  const rejeitar = useRejeitarChamada()

  const chamadas = data ?? []

  if (chamadas.length === 0) return null

  async function onAtender(c: ChamadaEntrante, clienteId?: number) {
    try {
      await atender.mutateAsync({ id: c.id, cliente_id: clienteId ?? c.cliente_id ?? undefined })
      const alvo = clienteId ?? c.cliente_id
      if (alvo && onAbrirCliente) onAbrirCliente(alvo)
      else if (!alvo) toast.info('Chamada atendida. Cliente não identificado — cadastre ou busque manualmente.')
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Erro ao atender.')
    }
  }

  async function onRejeitar(c: ChamadaEntrante) {
    try { await rejeitar.mutateAsync({ id: c.id, motivo: 'Rejeitada pelo atendente' }) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao rejeitar.') }
  }

  return (
    <Card className="mb-4 border-primary/40">
      <CardContent className="pt-6 space-y-3">
        <div className="flex items-center gap-2 text-sm font-medium">
          <PhoneIncoming size={16} className="text-primary" />
          Chamadas ({chamadas.length})
        </div>

        {chamadas.map((c) => (
          <div key={c.id} className="flex flex-wrap items-center gap-3 rounded-md border p-3">
            <span className="font-medium tabular-nums">{c.telefone_formatado}</span>
            {c.ramal && <Badge variant="secondary">ramal {c.ramal}</Badge>}

            {c.cliente ? (
              <Badge variant="success">{c.cliente}</Badge>
            ) : c.candidatos.length > 0 ? (
              // Telefone de mais de um cliente (comércio, condomínio, cadastro
              // duplicado): quem escolhe é o atendente, depois de perguntar.
              <div className="flex flex-wrap items-center gap-1">
                <span className="text-xs text-muted-foreground">Quem é?</span>
                {c.candidatos.map((cand) => (
                  <Button key={cand.id} variant="outline" size="sm" onClick={() => onAtender(c, cand.id)}>
                    {cand.nome}
                  </Button>
                ))}
              </div>
            ) : (
              <Badge variant="warning">Não identificado</Badge>
            )}

            <div className="ml-auto flex gap-1">
              <Button size="sm" loading={atender.isPending} onClick={() => onAtender(c)}>
                <UserCheck size={14} /> Atender
              </Button>
              <Button variant="ghost" size="sm" loading={rejeitar.isPending} onClick={() => onRejeitar(c)}>
                <PhoneOff size={14} />
              </Button>
            </div>
          </div>
        ))}
      </CardContent>
    </Card>
  )
}
