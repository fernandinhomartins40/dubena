import { useEffect, useState } from 'react'
import { FormDialog, Field, Input, Textarea, toast, Tabs, TabsList, TabsTrigger, TabsContent, AsyncSelect } from '@/components/ui'
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
  // Null = o mesmo vasilhame da linha, que é o caso comum. Escolher outro é o
  // que dispensa fechar a tela e abrir um comodato do zero.
  const [produtoId, setProdutoId] = useState<number | null>(null)
  const [produtoLabel, setProdutoLabel] = useState('')

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
    setProdutoId(null)
    setProdutoLabel('')
    // Um ano à frente é a renovação típica; a data continua editável.
    const daqui = new Date()
    daqui.setFullYear(daqui.getFullYear() + 1)
    setVencimento(daqui.toISOString().slice(0, 10))
  }, [comodato, modo])

  // Só é "outro produto" se de fato diferir da linha — escolher o mesmo no
  // seletor não pode mudar a mensagem nem a conta exibida.
  const outroProduto = produtoId !== null && produtoId !== comodato?.produto_id

  const qtd = Number(quantidade)
  const qtdInvalida = !quantidade || Number.isNaN(qtd) || qtd <= 0
  const vencInvalido = !vencimento || vencimento <= new Date().toISOString().slice(0, 10)
  const invalido = aba === 'acrescentar' ? qtdInvalida : vencInvalido

  async function confirmar() {
    if (!comodato || invalido) return
    try {
      // Acrescentar em outro produto pode ter criado uma linha nova; o contrato
      // a abrir é o DELA. Abrir o da linha de origem mostraria um papel que não
      // contém o item recém-lançado.
      let alvo = comodato.id

      if (aba === 'acrescentar') {
        const salvo = await acrescentar.mutateAsync({
          id: comodato.id, quantidade: qtd, observacao: observacao || undefined,
          produto_id: produtoId,
        })
        if (salvo?.id) alvo = salvo.id
        toast.success(outroProduto
          ? `${qtd} ${produtoLabel} acrescentado(s) ao cliente. Contrato reemitido com todos os itens.`
          : `Comodato passou a ${emPosse + qtd} vasilhame(s). Contrato reemitido.`)
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
      await abrirContratoComodato(alvo)
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
          <Field label="Vasilhame">
            <AsyncSelect
              endpoint="/lookups/produtos-vasilhame"
              value={produtoId ?? comodato?.produto_id ?? null}
              valueLabel={produtoLabel || (comodato?.produto?.descricao ?? '')}
              onChange={(id, opt) => { setProdutoId(id); setProdutoLabel(opt?.label ?? '') }}
            />
          </Field>

          <Field label="Quantidade a acrescentar" required>
            <Input
              type="number" min={1} step="0.001" value={quantidade}
              onChange={(e) => setQuantidade(e.target.value)}
              placeholder="Ex.: 5"
            />
          </Field>

          {!qtdInvalida && (outroProduto ? (
            <p className="text-sm text-muted-foreground">
              O cliente passa a ter também{' '}
              <strong className="tabular-nums">{qtd}</strong> {produtoLabel}, além dos{' '}
              <strong className="tabular-nums">{emPosse}</strong>{' '}
              {comodato?.produto?.descricao ?? 'vasilhame(s)'} que já estão com ele. O contrato
              emitido lista os dois itens, com o total.
            </p>
          ) : (
            <p className="text-sm text-muted-foreground">
              O comodato passa de <strong className="tabular-nums">{emPosse}</strong> para{' '}
              <strong className="tabular-nums">{emPosse + qtd}</strong> vasilhame(s), e o estoque é
              baixado da diferença. Uma nova versão do contrato será emitida com o total atualizado.
            </p>
          ))}

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
