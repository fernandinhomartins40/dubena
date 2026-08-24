import { useEffect, useState } from 'react'
import { FormDialog, Field, Input, Switch, toast } from '@/components/ui'
import { useSalvarConfigVigilancia, type VigilanciaConfig } from './api'

/**
 * A régua da vigilância.
 *
 * Cada número aqui decide quem entra na fila de averiguação. Afrouxar demais
 * esvazia a fila e o desvio passa; apertar demais enche a fila de cliente
 * honesto e a equipe para de olhar — que é o mesmo que não ter vigilância.
 *
 * Os padrões foram calibrados contra a base real (2026-08-24): 49 clientes
 * vigiados, 24 na fila, e os 25 saudáveis fora dela.
 */
export function ConfigVigilanciaDialog({ config, open, onOpenChange }: {
  config: VigilanciaConfig | undefined
  open: boolean
  onOpenChange: (v: boolean) => void
}) {
  const salvar = useSalvarConfigVigilancia()
  const [form, setForm] = useState<VigilanciaConfig | null>(null)

  useEffect(() => {
    if (open && config) setForm({ ...config })
  }, [open, config])

  function campo<K extends keyof VigilanciaConfig>(k: K, v: VigilanciaConfig[K]) {
    setForm((f) => (f === null ? f : { ...f, [k]: v }))
  }

  // O crítico é mais severo que o de atenção — inverter faria a régua de
  // atenção nunca disparar, porque a crítica venceria antes.
  const ordemInvalida = form !== null && (
    Number(form.giro_critico) > Number(form.giro_minimo)
    || Number(form.queda_critica) < Number(form.queda_atencao)
  )

  async function confirmar() {
    if (form === null || ordemInvalida) return
    try {
      await salvar.mutateAsync(form)
      toast.success('Régua salva. Vale a partir da próxima rodada.')
      onOpenChange(false)
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Erro ao salvar a régua.')
    }
  }

  return (
    <FormDialog
      open={open}
      onOpenChange={onOpenChange}
      title="Régua da vigilância"
      description="Define quem entra na fila de averiguação."
      confirmLabel="Salvar régua"
      loading={salvar.isPending}
      confirmDisabled={form === null || ordemInvalida}
      onConfirm={confirmar}
      widthClass="max-w-2xl"
    >
      {form !== null && (
        <div className="space-y-4">
          <div className="flex items-center justify-between rounded-md border p-3">
            <div>
              <div className="text-sm font-medium">Vigilância ativa</div>
              <p className="text-xs text-muted-foreground">
                Desligada, a rodada semanal não gera avaliações nem alertas.
              </p>
            </div>
            <Switch checked={form.ativo} onCheckedChange={(v) => campo('ativo', v)} />
          </div>

          <Secao titulo="Janela de medição">
            <Numero
              label="Dias da janela" valor={form.dias_janela} min={30} max={730}
              ajuda="Período em que a compra é somada. 180 dias absorve sazonalidade sem esconder uma parada recente."
              onChange={(v) => campo('dias_janela', v)}
            />
            <Numero
              label="Posse mínima vigiada" valor={form.posse_minima_vigiada} min={1}
              ajuda="Abaixo disso o comodato não é vigiado — cliente com 1 ou 2 vasilhames vira ruído."
              onChange={(v) => campo('posse_minima_vigiada', v)}
            />
          </Secao>

          <Secao titulo="Giro (compras ÷ vasilhames em posse)">
            <Numero
              label="Giro mínimo" valor={form.giro_minimo} min={0} step={0.1}
              ajuda="Abaixo disso é ATENÇÃO."
              onChange={(v) => campo('giro_minimo', v)}
            />
            <Numero
              label="Giro crítico" valor={form.giro_critico} min={0} step={0.1}
              ajuda="Igual ou abaixo disso é CRÍTICO. Precisa ser menor que o mínimo."
              onChange={(v) => campo('giro_critico', v)}
            />
          </Secao>

          <Secao titulo="Queda contra o próprio histórico">
            <Numero
              label="Queda para atenção (%)" valor={form.queda_atencao} min={1} max={100}
              ajuda="O sinal mais forte: o cliente é comparado consigo mesmo, não com a média."
              onChange={(v) => campo('queda_atencao', v)}
            />
            <Numero
              label="Queda crítica (%)" valor={form.queda_critica} min={1} max={100}
              ajuda="Precisa ser maior que a de atenção."
              onChange={(v) => campo('queda_critica', v)}
            />
          </Secao>

          <Secao titulo="Tempo e vencimento">
            <Numero
              label="Dias sem comprar para alertar" valor={form.dias_sem_compra_alerta} min={7} max={730}
              ajuda="O dobro disso vira CRÍTICO."
              onChange={(v) => campo('dias_sem_compra_alerta', v)}
            />
            <Numero
              label="Aviso de vencimento (dias)" valor={form.dias_aviso_vencimento} min={1} max={365}
              ajuda="Antecedência do alerta de contrato a vencer."
              onChange={(v) => campo('dias_aviso_vencimento', v)}
            />
          </Secao>

          {ordemInvalida && (
            <p className="text-sm text-destructive">
              O corte crítico precisa ser mais severo que o de atenção: giro crítico menor que
              o mínimo, e queda crítica maior que a de atenção.
            </p>
          )}
        </div>
      )}
    </FormDialog>
  )
}

function Secao({ titulo, children }: { titulo: string; children: React.ReactNode }) {
  return (
    <div className="space-y-2">
      <div className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{titulo}</div>
      <div className="grid gap-3 sm:grid-cols-2">{children}</div>
    </div>
  )
}

function Numero({ label, valor, ajuda, min, max, step = 1, onChange }: {
  label: string; valor: number; ajuda: string
  min?: number; max?: number; step?: number
  onChange: (v: number) => void
}) {
  return (
    <Field label={label}>
      <Input
        type="number" value={valor} min={min} max={max} step={step}
        onChange={(e) => onChange(Number(e.target.value))}
      />
      <p className="mt-1 text-xs text-muted-foreground">{ajuda}</p>
    </Field>
  )
}
