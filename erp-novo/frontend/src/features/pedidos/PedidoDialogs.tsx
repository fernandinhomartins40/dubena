import {
  Button, Badge, AsyncState,
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogClose, toast,
} from '@/components/ui'
import { useAuth } from '@/lib/auth'
import { usePedido, useEmitirNfce } from './api'
import { brl, dataHora as fmtData } from '@/lib/format'
import { situacaoBadge } from './shared'

export function FichaDialog({ id, onClose }: { id: number | null; onClose: () => void }) {
  const { data, isLoading } = usePedido(id)
  const { can } = useAuth()
  const emitir = useEmitirNfce(id)

  async function onEmitir() {
    try {
      await emitir.mutateAsync('65')
      toast.success('Documento fiscal emitido.')
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Erro ao emitir o documento fiscal.')
    }
  }

  // Pode faturar quando concluído e ainda sem NF, com permissão fiscal.
  const podeEmitir = !!data && Number(data.fechadoconcluido) === 1 && !data.tem_nf && can('fiscal.emitir')

  return (
    <Dialog open={id !== null} onOpenChange={(o) => !o && onClose()}>
      <DialogContent className="max-w-2xl">
        <DialogHeader><DialogTitle>Pedido #{id}</DialogTitle></DialogHeader>
        {isLoading || !data ? <AsyncState loading skeletonRows={4}>{null}</AsyncState> : (
          <div className="space-y-4">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
              <div><span className="text-muted-foreground">Cliente:</span> <span className="font-medium">{data.cliente || '—'}</span></div>
              <div><span className="text-muted-foreground">Situação:</span> {situacaoBadge({ situacao: data.situacao })}</div>
              <div><span className="text-muted-foreground">Condição:</span> {data.condicao || '—'}</div>
              <div><span className="text-muted-foreground">Valor:</span> <span className="font-medium tabular-nums">{brl(data.valorvenda)}</span></div>
              <div><span className="text-muted-foreground">Data:</span> {fmtData(data.datahora)}</div>
              <div><span className="text-muted-foreground">NF-e:</span> {data.tem_nf ? <Badge variant="success">Emitida</Badge> : <Badge variant="secondary">—</Badge>}</div>
            </div>
            <div>
              <p className="text-sm font-medium mb-2">Itens</p>
              <div className="rounded-lg border border-border divide-y divide-border">
                {(data.itens ?? []).map((it: any) => (
                  <div key={it.id} className="flex items-center justify-between px-3 py-2 text-sm">
                    <span>{it.produto || '—'} <span className="text-muted-foreground">× {it.quantidade}</span></span>
                    <span className="tabular-nums">{brl(it.precovendatotal)}</span>
                  </div>
                ))}
                {(!data.itens || data.itens.length === 0) && <p className="px-3 py-2 text-sm text-muted-foreground">Sem itens.</p>}
              </div>
            </div>
          </div>
        )}
        <DialogFooter>
          {podeEmitir && <Button loading={emitir.isPending} onClick={onEmitir}>Emitir NFC-e</Button>}
          <DialogClose asChild><Button variant="outline">Fechar</Button></DialogClose>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

// O 'Novo pedido' mora em arquivo próprio (três blocos + sugestão pelo cliente);
// reexportado aqui para quem já importava deste módulo.
export { NovoPedidoDialog } from './NovoPedidoDialog'
