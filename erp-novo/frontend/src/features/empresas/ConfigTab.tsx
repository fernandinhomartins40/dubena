import { useEffect, useState } from 'react'
import { Save } from 'lucide-react'
import {
  Button, Tabs, TabsList, TabsTrigger, TabsContent, AsyncState, toast,
} from '@/components/ui'
import { useEmpresaConfig, useSalvarConfig } from './api'
import type { ConfigSubtabProps } from './config/types'
import { PedidoTab } from './config/PedidoTab'
import { EstoqueTab } from './config/EstoqueTab'
import { ImpressaoTab } from './config/ImpressaoTab'
import { EmailTab } from './config/EmailTab'
import { ContabilTab } from './config/ContabilTab'
import { FreteTab } from './config/FreteTab'
import { PercentuaisTab } from './config/PercentuaisTab'
import { SenhaMestraDialog } from './config/SenhaMestraDialog'
import { TesteEmailDialog } from './config/TesteEmailDialog'

/**
 * Aba Configurações da empresa (F18.5) — shell: as 106 colunas ficam em
 * sub-abas temáticas (./config/*) e os utilitários (senha mestra, teste de
 * e-mail) em diálogos próprios. Aqui só o estado do form + salvar.
 */
export function ConfigTab({ empresaId }: { empresaId: number }) {
  const { data, isLoading } = useEmpresaConfig(empresaId)
  const salvar = useSalvarConfig(empresaId)
  const [form, setForm] = useState<Record<string, any>>({})
  const [labels, setLabels] = useState<Record<string, string | null>>({})

  useEffect(() => { if (data) setForm({ ...data }) }, [data])
  const campo = (k: string, v: any) => setForm((p) => ({ ...p, [k]: v }))
  const lbl = (k: string, v: string | null) => setLabels((l) => ({ ...l, [k]: v }))

  async function onSubmit() {
    try { await salvar.mutateAsync(form); toast.success('Configurações salvas.') }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao salvar configurações.') }
  }

  if (isLoading) return <AsyncState loading skeletonRows={4}>{null}</AsyncState>

  const subtab: ConfigSubtabProps = { form, campo, labels, lbl }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div className="flex gap-2">
          <SenhaMestraDialog empresaId={empresaId} tem={!!data?.tem_senhamestre} />
          <TesteEmailDialog empresaId={empresaId} />
        </div>
        <Button loading={salvar.isPending} onClick={onSubmit}><Save size={16} /> Salvar configurações</Button>
      </div>

      <Tabs defaultValue="pedido">
        <TabsList className="overflow-x-auto">
          <TabsTrigger value="pedido">Pedido/Entrega</TabsTrigger>
          <TabsTrigger value="estoque">Estoque</TabsTrigger>
          <TabsTrigger value="impressao">Impressão</TabsTrigger>
          <TabsTrigger value="email">E-mail</TabsTrigger>
          <TabsTrigger value="contabil">Contábil</TabsTrigger>
          <TabsTrigger value="frete">Frete</TabsTrigger>
          <TabsTrigger value="percentuais">Percentuais</TabsTrigger>
        </TabsList>
        <TabsContent value="pedido"><PedidoTab {...subtab} /></TabsContent>
        <TabsContent value="estoque"><EstoqueTab {...subtab} /></TabsContent>
        <TabsContent value="impressao"><ImpressaoTab {...subtab} /></TabsContent>
        <TabsContent value="email"><EmailTab {...subtab} /></TabsContent>
        <TabsContent value="contabil"><ContabilTab {...subtab} /></TabsContent>
        <TabsContent value="frete"><FreteTab {...subtab} /></TabsContent>
        <TabsContent value="percentuais"><PercentuaisTab {...subtab} /></TabsContent>
      </Tabs>
    </div>
  )
}
