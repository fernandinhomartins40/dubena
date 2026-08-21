import { useState } from 'react'
import { PackagePlus, Undo2, Boxes } from 'lucide-react'
import {
  Button, Badge, AsyncState, Field, Input, toast,
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogClose,
} from '@/components/ui'
import {
  useEstoqueFranqueado, useCarregar, useDevolver,
} from './estoqueApi'

/**
 * Carga e devolução de mercadoria do franqueado (F5).
 *
 * O que está em poder dele é a base do acerto: no fim do turno, o que saiu menos
 * o que voltou menos o que virou venda tem de fechar.
 *
 * **Devolver só aparece na consignação.** No modo compra a mercadoria é do
 * franqueado — o backend recusa a devolução (422), e mostrar o botão convidaria
 * ao erro.
 */
export function EstoqueFranqueadoDialog({
  colaboradorId,
  setorDepositoId,
  onClose,
}: {
  colaboradorId: number | null
  setorDepositoId: number | null
  onClose: () => void
}) {
  const estoque = useEstoqueFranqueado(colaboradorId)
  const carregar = useCarregar()
  const devolver = useDevolver()

  const [produtoId, setProdutoId] = useState('')
  const [quantidade, setQuantidade] = useState('')

  const dados = estoque.data
  const consignado = dados?.modo_estoque === 'consignacao'

  const limpar = () => { setProdutoId(''); setQuantidade('') }

  const mover = async (acao: 'carga' | 'devolucao') => {
    if (colaboradorId === null || setorDepositoId === null) return

    const pid = Number(produtoId)
    const qtd = Number(quantidade.replace(',', '.'))
    if (!pid || !qtd || qtd <= 0) {
      toast.error('Informe produto e quantidade.')
      return
    }

    const payload = {
      colaboradorId,
      setor_origem_id: setorDepositoId,
      itens: [{ produto_id: pid, quantidade: qtd }],
    }

    try {
      if (acao === 'carga') {
        await carregar.mutateAsync(payload)
        toast.success('Carga registrada.')
      } else {
        await devolver.mutateAsync(payload)
        toast.success('Devolução registrada.')
      }
      limpar()
    } catch (e: any) {
      // 422 do DomainException — ex.: devolução no modo compra, ou saldo
      // insuficiente no depósito.
      toast.error(e?.response?.data?.message ?? 'Não foi possível movimentar.')
    }
  }

  return (
    <Dialog open={colaboradorId !== null} onOpenChange={(o) => !o && (limpar(), onClose())}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Boxes size={17} /> Mercadoria em poder
          </DialogTitle>
        </DialogHeader>

        <AsyncState loading={estoque.isLoading} error={estoque.error}>
          {dados && (
            <div className="space-y-3">
              <div className="flex items-center justify-between">
                <span className="font-medium">{dados.colaborador.nome}</span>
                {dados.modo_estoque ? (
                  <Badge variant={consignado ? 'default' : 'secondary'}>
                    {consignado ? 'Consignação' : 'Compra'}
                  </Badge>
                ) : (
                  // Sem modo definido o backend recusa qualquer movimento
                  // (fail-closed): avisar aqui evita o operador descobrir no erro.
                  <Badge variant="destructive">Modo não definido</Badge>
                )}
              </div>

              {dados.itens.length === 0 ? (
                <div className="text-sm text-muted-foreground">Nada em poder deste franqueado.</div>
              ) : (
                <div className="space-y-1">
                  {dados.itens.map((i) => (
                    <div key={i.produto_id} className="flex justify-between text-sm border-b py-1">
                      <span>{i.produto}</span>
                      <span className="font-medium">{i.quantidade}</span>
                    </div>
                  ))}
                </div>
              )}

              {dados.modo_estoque && (
                <div className="grid grid-cols-2 gap-2 pt-2">
                  <Field label="Produto (id)">
                    <Input value={produtoId} onChange={(e) => setProdutoId(e.target.value)} inputMode="numeric" />
                  </Field>
                  <Field label="Quantidade">
                    <Input value={quantidade} onChange={(e) => setQuantidade(e.target.value)} inputMode="decimal" />
                  </Field>
                </div>
              )}
            </div>
          )}
        </AsyncState>

        <DialogFooter>
          <DialogClose asChild><Button variant="outline">Fechar</Button></DialogClose>

          {consignado && (
            <Button variant="outline" onClick={() => mover('devolucao')} disabled={devolver.isPending}>
              <Undo2 size={15} className="mr-1" /> Devolver
            </Button>
          )}

          {dados?.modo_estoque && (
            <Button onClick={() => mover('carga')} disabled={carregar.isPending}>
              <PackagePlus size={15} className="mr-1" /> Carregar
            </Button>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
