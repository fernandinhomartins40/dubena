import { PageHeader, Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui'
import { SaldosTab } from './tabs/SaldosTab'
import { AcertoTab } from './tabs/AcertoTab'
import { TransferenciaTab } from './tabs/TransferenciaTab'
import { RequisicaoTab } from './tabs/RequisicaoTab'
import { FisicoTab } from './tabs/FisicoTab'
import { InventarioFiscalTab } from './tabs/InventarioFiscalTab'
import { FechamentoTab } from './tabs/FechamentoTab'

/** Estoque (F17.R7) — shell de abas; cada aba vive em ./tabs/*. */
export function EstoquePage() {
  return (
    <div>
      <PageHeader title="Estoque" subtitle="Saldos, movimentações, inventário e fechamento" />
      <Tabs urlKey="tab" defaultValue="saldos">
        <TabsList className="overflow-x-auto">
          <TabsTrigger value="saldos">Saldos</TabsTrigger>
          {/* "Acerto" não diz a quem recebe uma carga que é aqui que ela entra.
              O valor da aba segue 'acerto': links salvos continuam valendo. */}
          <TabsTrigger value="acerto">Entrada e saída</TabsTrigger>
          <TabsTrigger value="transferencia">Transferência</TabsTrigger>
          <TabsTrigger value="requisicao">Requisição</TabsTrigger>
          <TabsTrigger value="fisico">Inventário físico</TabsTrigger>
          <TabsTrigger value="inventario-fiscal">Inventário fiscal</TabsTrigger>
          <TabsTrigger value="fechamento">Fechamento</TabsTrigger>
        </TabsList>
        <TabsContent value="saldos"><SaldosTab /></TabsContent>
        <TabsContent value="acerto"><AcertoTab /></TabsContent>
        <TabsContent value="transferencia"><TransferenciaTab /></TabsContent>
        <TabsContent value="requisicao"><RequisicaoTab /></TabsContent>
        <TabsContent value="fisico"><FisicoTab /></TabsContent>
        <TabsContent value="inventario-fiscal"><InventarioFiscalTab /></TabsContent>
        <TabsContent value="fechamento"><FechamentoTab /></TabsContent>
      </Tabs>
    </div>
  )
}
