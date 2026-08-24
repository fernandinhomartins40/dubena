import { useState } from 'react'
import { FileSignature, Receipt, Undo2, RotateCcw, CheckCircle2 } from 'lucide-react'
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription,
  Button, Badge, AsyncState, Can, ConfirmDialog, toast,
} from '@/components/ui'
import { data as fmtData } from '@/lib/format'
import { mensagemDeErroBlob } from '@/lib/pdf'
import {
  useComodato, useEstornarDevolucao, useReemitirContrato, useMarcarAssinado,
  abrirContratoComodato, abrirReciboDevolucao,
  type ComodatoMovimento,
} from './api'

const ROTULO_MOTIVO: Record<string, string> = {
  EMISSAO_INICIAL: 'Emissão inicial',
  DEVOLUCAO_PARCIAL: 'Após devolução parcial',
  REEMISSAO: 'Reemissão',
}

/**
 * Detalhe do comodato: extrato de movimentos e versões do contrato.
 *
 * É a tela que faltava. Sem ela o operador via só "quantidade" e "devolvido"
 * somados, sem saber quando cada devolução aconteceu nem qual papel está
 * valendo — e por isso evitava a devolução parcial.
 */
export function ComodatoDetalhe({ id, onOpenChange }: {
  id: number | null
  onOpenChange: (v: boolean) => void
}) {
  const { data, isLoading, error } = useComodato(id)
  const estornar = useEstornarDevolucao()
  const reemitir = useReemitirContrato()
  const assinar = useMarcarAssinado()
  const [aEstornar, setAEstornar] = useState<ComodatoMovimento | null>(null)

  async function abrirPdfSeguro(fn: () => Promise<void>, padrao: string) {
    try { await fn() } catch (e) { toast.error(await mensagemDeErroBlob(e, padrao)) }
  }

  async function confirmarEstorno() {
    if (!aEstornar || id === null) return
    try {
      await estornar.mutateAsync({ id, movimento: aEstornar.id })
      toast.success('Devolução estornada. O saldo voltou para o cliente.')
      setAEstornar(null)
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Erro ao estornar.')
    }
  }

  const c = data?.comodato
  const vigente = data?.contratos[0]

  return (
    <>
      <Dialog open={id !== null} onOpenChange={onOpenChange}>
        <DialogContent className="max-w-3xl max-h-[85vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>
              Comodato nº {id} {c ? `— ${c.cliente?.nome ?? ''}` : ''}
            </DialogTitle>
            <DialogDescription>
              {c ? `${c.produto?.descricao ?? 'Vasilhame'} · emprestado em ${fmtData(c.data_emprestimo)}` : ''}
            </DialogDescription>
          </DialogHeader>

          <AsyncState loading={isLoading} error={error}>
            {data && (
              <div className="space-y-5">
                <div className="grid grid-cols-3 gap-3">
                  <Resumo rotulo="Emprestado" valor={Number(data.comodato.quantidade)} />
                  <Resumo rotulo="Devolvido" valor={Number(data.comodato.quantidade_devolvida)} />
                  <Resumo rotulo="Em poder do cliente" valor={data.em_posse} destaque />
                </div>

                <section>
                  <div className="mb-2 flex items-center justify-between">
                    <h3 className="text-sm font-semibold">Contrato</h3>
                    {data.em_posse > 0 && (
                      <Can permission="comodato.edit">
                        <Button
                          variant="outline" size="sm" disabled={reemitir.isPending}
                          onClick={async () => {
                            try {
                              await reemitir.mutateAsync(data.comodato.id)
                              toast.success('Nova versão emitida.')
                            } catch (e: any) {
                              toast.error(e?.response?.data?.message ?? 'Erro ao reemitir.')
                            }
                          }}
                        >
                          <RotateCcw size={14} /> Reemitir
                        </Button>
                      </Can>
                    )}
                  </div>

                  {data.contratos.length === 0 ? (
                    // Os 975 comodatos vindos do legado não têm versão nenhuma:
                    // o contrato deles sai do estado atual, como sempre saiu.
                    <p className="text-sm text-muted-foreground">
                      Comodato migrado do sistema antigo — sem versões registradas.
                      O contrato é gerado a partir do saldo atual.
                    </p>
                  ) : (
                    <ul className="divide-y rounded-md border">
                      {data.contratos.map((v) => (
                        <li key={v.id} className="flex items-center gap-3 px-3 py-2 text-sm">
                          <Badge variant={v.id === vigente?.id ? 'default' : 'secondary'}>
                            v{v.versao}
                          </Badge>
                          <div className="min-w-0 flex-1">
                            <div className="truncate">
                              {ROTULO_MOTIVO[v.motivo] ?? v.motivo}
                              {' · '}
                              <span className="tabular-nums">{Number(v.quantidade_em_posse)}</span> em posse
                            </div>
                            <div className="text-xs text-muted-foreground">
                              {fmtData(v.created_at)}
                              {v.assinado_em ? ' · assinado' : ' · aguardando assinatura'}
                            </div>
                          </div>
                          {!v.assinado_em && (
                            <Can permission="comodato.edit">
                              <Button
                                variant="ghost" size="sm" title="Marcar como assinado"
                                onClick={() => assinar.mutate({ id: data.comodato.id, contrato: v.id })}
                              >
                                <CheckCircle2 size={15} />
                              </Button>
                            </Can>
                          )}
                          <Button
                            variant="outline" size="sm"
                            onClick={() => abrirPdfSeguro(
                              () => abrirContratoComodato(data.comodato.id, v.versao),
                              'Falha ao gerar o contrato.',
                            )}
                          >
                            <FileSignature size={14} /> Imprimir
                          </Button>
                        </li>
                      ))}
                    </ul>
                  )}
                </section>

                <section>
                  <h3 className="mb-2 text-sm font-semibold">Extrato</h3>
                  <ul className="divide-y rounded-md border">
                    {data.movimentos.map((m) => (
                      <li key={m.id} className="flex items-center gap-3 px-3 py-2 text-sm">
                        <TipoBadge movimento={m} />
                        <div className="min-w-0 flex-1">
                          <div>
                            <span className="tabular-nums font-medium">{Number(m.quantidade)}</span>
                            {' · saldo '}
                            <span className="tabular-nums">{Number(m.saldo_apos)}</span>
                          </div>
                          <div className="truncate text-xs text-muted-foreground">
                            {fmtData(m.data)}
                            {m.usuario ? ` · ${m.usuario.name}` : ''}
                            {m.observacao ? ` · ${m.observacao}` : ''}
                          </div>
                        </div>

                        {m.tipo === 'DEVOLUCAO' && (
                          <>
                            <Button
                              variant="ghost" size="sm" title="Recibo da devolução"
                              onClick={() => abrirPdfSeguro(
                                () => abrirReciboDevolucao(data.comodato.id, m.id),
                                'Falha ao gerar o recibo.',
                              )}
                            >
                              <Receipt size={15} />
                            </Button>
                            {!m.estornado && (
                              <Can permission="comodato.estornar">
                                <Button
                                  variant="ghost" size="sm" title="Estornar esta devolução"
                                  onClick={() => setAEstornar(m)}
                                >
                                  <Undo2 size={15} />
                                </Button>
                              </Can>
                            )}
                          </>
                        )}
                      </li>
                    ))}
                  </ul>
                </section>
              </div>
            )}
          </AsyncState>
        </DialogContent>
      </Dialog>

      <ConfirmDialog
        open={aEstornar !== null}
        onOpenChange={(v) => !v && setAEstornar(null)}
        title="Estornar esta devolução?"
        description={aEstornar
          ? `${Number(aEstornar.quantidade)} unidade(s) voltam a constar em poder do cliente e saem do estoque de novo. `
            + 'A devolução original continua no extrato — o estorno é registrado ao lado dela.'
          : ''}
        confirmLabel="Estornar"
        loading={estornar.isPending}
        onConfirm={confirmarEstorno}
      />
    </>
  )
}

function Resumo({ rotulo, valor, destaque }: { rotulo: string; valor: number; destaque?: boolean }) {
  return (
    <div className={`rounded-md border p-3 ${destaque ? 'border-amber-300 bg-amber-50' : ''}`}>
      <div className="text-xs text-muted-foreground">{rotulo}</div>
      <div className="text-xl font-semibold tabular-nums">{valor}</div>
    </div>
  )
}

function TipoBadge({ movimento }: { movimento: ComodatoMovimento }) {
  if (movimento.tipo === 'EMPRESTIMO') return <Badge variant="secondary">Empréstimo</Badge>
  if (movimento.tipo === 'ESTORNO') return <Badge variant="destructive">Estorno</Badge>
  // Devolução já anulada continua visível: ela aconteceu, e apagar da tela
  // esconderia por que o saldo mudou duas vezes.
  return movimento.estornado
    ? <Badge variant="outline" className="line-through">Devolução</Badge>
    : <Badge variant="success">Devolução</Badge>
}
