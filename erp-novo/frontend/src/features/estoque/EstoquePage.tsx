import { PageHeader, Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui'
import { SaldosTab } from './tabs/SaldosTab'
import { AcertoTab } from './tabs/AcertoTab'
import { TransferenciaTab } from './tabs/TransferenciaTab'
import { RequisicaoTab } from './tabs/RequisicaoTab'
import { InventarioTab } from './tabs/InventarioTab'
import { FisicoTab } from './tabs/FisicoTab'
import { FechamentoTab } from './tabs/FechamentoTab'

/** Estoque (F17.R7) — shell de abas; cada aba vive em ./tabs/*. */
export function EstoquePage() {
  return (
    <div>
      <PageHeader title="Estoque" subtitle="Saldos, movimentações, inventário e fechamento" />
      <Tabs defaultValue="saldos">
        <TabsList className="overflow-x-auto">
          <TabsTrigger value="saldos">Saldos</TabsTrigger>
          <TabsTrigger value="acerto">Acerto</TabsTrigger>
          <TabsTrigger value="transferencia">Transferência</TabsTrigger>
          <TabsTrigger value="requisicao">Requisição</TabsTrigger>
          <TabsTrigger value="inventario">Inventário</TabsTrigger>
          <TabsTrigger value="fisico">Físico</TabsTrigger>
          <TabsTrigger value="fechamento">Fechamento</TabsTrigger>
        </TabsList>
        <TabsContent value="saldos"><SaldosTab /></TabsContent>
        <TabsContent value="acerto"><AcertoTab /></TabsContent>
        <TabsContent value="transferencia"><TransferenciaTab /></TabsContent>
        <TabsContent value="requisicao"><RequisicaoTab /></TabsContent>
        <TabsContent value="inventario"><InventarioTab /></TabsContent>
        <TabsContent value="fisico"><FisicoTab /></TabsContent>
        <TabsContent value="fechamento"><FechamentoTab /></TabsContent>
      </Tabs>
    </div>
  )
}
