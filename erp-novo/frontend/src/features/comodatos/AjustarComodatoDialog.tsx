import { useEffect, useState } from 'react'
import { FormDialog, Field, Input, Textarea, toast, Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui'
import { abrirContratoComodato, useAcrescentarComodato, useRenovarComodato, type Comodato } from './api'

type Modo = 'acrescentar' | 'renovar'

/**
 * Ajuste de itens do comodato: acrescentar vasilhames ou renovar o vencimento.
 *
 * **Acrescentar.** O cliente cresceu e pediu mais 5 botijões. Antes disso a
 * única saída era abrir um comodato NOVO — o cliente ficava com dois contratos
 * para a mesma relação, e o total em poder dele só aparecia somando registros na
 * mão. Agora o mesmo comodato cresce e o contrato é reemitido com o total certo.
 *
 * **Renovar.** É a resposta ao alerta de vencimento. Renovar sem reemitir o papel
 * deixaria o cliente com contrato vencido na gaveta e a revenda sem instrumento
 * para reaver o vasilhame — exatamente o risco que o alerta aponta.
 *
 * Nos dois casos o contrato novo abre em seguida, porque o gesto só termina no
 * papel assinado.
 */
export function AjustarComodatoDialog({ comodato, modo, onOpenChange }: {
  comodato: Comodato | null
  modo: Modo
  onOpenChange: (v: boolean) => void
}) {
  const acrescentar = useAcrescentarComodato()
  const renovar = useRenovarComodato()

  const [aba, setAba] = useState<Modo>(modo)
  const [quantidade, setQuantidade] = useState('')
  const [observacao, setObservacao] = useState('')
  const [vencimento, setVencimento] = useState('')
  const [representante, setRepresentante] = useState('')
  const [cpf, setCpf] = useState('')

  const emPosse = comodato
    ? Number(comodato.quantidade) - Number(comodato.quantidade_devolvida)
    : 0

  useEffect(() => {
    if (!comodato) return
    setAba(modo)
    setQuantidade('')
    setObservacao('')
    setRepresentante('')
    setCpf('')
    // Um ano à frente é a renovação típica; a data continua editável.
    const daqui = new Date()
    daqui.setFullYear(daqui.getFullYear() + 1)
    setVencimento(daqui.toISOString().slice(0, 10))
  }, [comodato, modo])

  const qtd = Number(quantidade)
  const qtdInvalida = !quantidade || Number.isNaN(qtd) || qtd <= 0
  const vencInvalido = !vencimento || vencimento <= new Date().toISOString().slice(0, 10)
  const invalido = aba === 'acrescentar' ? qtdInvalida : vencInvalido

  async function confirmar() {
    if (!comodato || invalido) return
    try {
      if (aba === 'acrescentar') {
        await acrescentar.mutateAsync({
          id: comodato.id, quantidade: qtd, observacao: observacao || undefined,
        })
        toast.success(`Comodato passou a ${emPosse + qtd} vasilhame(s). Contrato reemitido.`)
      } else {
        await renovar.mutateAsync({
          id: comodato.id,
          data_vencimento: vencimento,
          nome_representante: representante || undefined,
          cpf_representante: cpf || undefined,
        })
        toast.success('Comodato renovado. Contrato reemitido.')
      }

      onOpenChange(false)
      // O contrato novo é o ponto do gesto: abre para assinatura.
      await abrirContratoComodato(comodato.id)
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Erro ao ajustar o comodato.')
    }
  }

  return (
    <FormDialog
      open={comodato !== null}
      onOpenChange={onOpenChange}
      title="Ajustar comodato"
      description={comodato
        ? `${comodato.cliente?.nome ?? 'Cliente'} — ${emPosse} ${comodato.produto?.descricao ?? 'vasilhame'} em poder dele`
        : undefined}
      confirmLabel={aba === 'acrescentar' ? 'Acrescentar e emitir contrato' : 'Renovar e emitir contrato'}
      loading={acrescentar.isPending || renovar.isPending}
      confirmDisabled={invalido}
      onConfirm={confirmar}
      widthClass="max-w-lg"
    >
      <Tabs value={aba} onValueChange={(v) => setAba(v as Modo)}>
        <TabsList className="w-full">
          <TabsTrigger value="acrescentar" className="flex-1">Acrescentar itens</TabsTrigger>
          <TabsTrigger value="renovar" className="flex-1">Renovar vencimento</TabsTrigger>
        </TabsList>

        <TabsContent value="acrescentar" className="space-y-3 pt-3">
          <Field label="Quantidade a acrescentar" required>
            <Input
              type="number" min={1} step="0.001" value={quantidade}
              onChange={(e) => setQuantidade(e.target.value)}
              placeholder="Ex.: 5"
            />
          </Field>

          {!qtdInvalida && (
            <p className="text-sm text-muted-foreground">
              O comodato passa de <strong className="tabular-nums">{emPosse}</strong> para{' '}
              <strong className="tabular-nums">{emPosse + qtd}</strong> vasilhame(s), e o estoque é
              baixado da diferença. Uma nova versão do contrato será emitida com o total atualizado.
            </p>
          )}

          <Field label="Observação">
            <Textarea
              rows={2} value={observacao} onChange={(e) => setObservacao(e.target.value)}
              placeholder="Ex.: cliente abriu a segunda cozinha"
            />
          </Field>
        </TabsContent>

        <TabsContent value="renovar" className="space-y-3 pt-3">
          <Field label="Novo vencimento" required>
            <Input type="date" value={vencimento} onChange={(e) => setVencimento(e.target.value)} />
          </Field>

          {vencInvalido && vencimento && (
            <p className="text-sm text-destructive">O novo vencimento precisa ser futuro.</p>
          )}

          <p className="text-sm text-muted-foreground">
            A quantidade não muda. É emitida uma versão nova do contrato com o vencimento
            atualizado, e o alerta de vencimento deste comodato se encerra.
          </p>

          <Field label="Representante (se mudou)">
            <Input value={representante} onChange={(e) => setRepresentante(e.target.value)} />
          </Field>
          <Field label="CPF do representante">
            <Input value={cpf} onChange={(e) => setCpf(e.target.value)} />
          </Field>
        </TabsContent>
      </Tabs>
    </FormDialog>
  )
}
