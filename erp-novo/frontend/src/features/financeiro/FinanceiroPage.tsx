import { PageHeader, Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui'
import { LancamentosTab } from './tabs/LancamentosTab'
import { CaixaTab } from './tabs/CaixaTab'
import { PlanoTab } from './tabs/PlanoTab'
import { CentroTab } from './tabs/CentroTab'
import { ChequesTab, BoletosTab, DRETab, ConciliacaoTab } from './tabs/FinanceiroExtraTabs'

/** Financeiro (F17.R7) — shell de abas; cada aba vive em ./tabs/*. */
export function FinanceiroPage() {
  return (
    <div>
      <PageHeader title="Financeiro" subtitle="Lançamentos, caixa e plano de contas" />
      <Tabs defaultValue="lancamentos">
        <TabsList className="overflow-x-auto">
          <TabsTrigger value="lancamentos">Lançamentos</TabsTrigger>
          <TabsTrigger value="caixa">Caixa</TabsTrigger>
          <TabsTrigger value="cheques">Cheques</TabsTrigger>
          <TabsTrigger value="boletos">Boletos / PIX</TabsTrigger>
          <TabsTrigger value="dre">DRE</TabsTrigger>
          <TabsTrigger value="conciliacao">Conciliação</TabsTrigger>
          <TabsTrigger value="plano">Plano de Contas</TabsTrigger>
          <TabsTrigger value="centro">Centro de Custo</TabsTrigger>
        </TabsList>
        <TabsContent value="lancamentos"><LancamentosTab /></TabsContent>
        <TabsContent value="caixa"><CaixaTab /></TabsContent>
        <TabsContent value="cheques"><ChequesTab /></TabsContent>
        <TabsContent value="boletos"><BoletosTab /></TabsContent>
        <TabsContent value="dre"><DRETab /></TabsContent>
        <TabsContent value="conciliacao"><ConciliacaoTab /></TabsContent>
        <TabsContent value="plano"><PlanoTab /></TabsContent>
        <TabsContent value="centro"><CentroTab /></TabsContent>
      </Tabs>
    </div>
  )
}
