import { PageHeader, Tabs, TabsList, TabsTrigger, TabsContent, TabsGroup } from '@/components/ui'
import { LancamentosTab } from './tabs/LancamentosTab'
import { CaixaTab } from './tabs/CaixaTab'
import { PlanoTab } from './tabs/PlanoTab'
import { CentroTab } from './tabs/CentroTab'
import { ChequesTab, BoletosTab, DRETab, ConciliacaoTab } from './tabs/FinanceiroExtraTabs'
import { ExtratoRegrasTab } from './tabs/ExtratoRegrasTab'
import { MaloteTab } from './tabs/MaloteTab'

/** Financeiro (F17.R7) — shell de abas; cada aba vive em ./tabs/*. */
export function FinanceiroPage() {
  return (
    <div>
      <PageHeader title="Financeiro" subtitle="Contas a pagar e receber, caixa e boletos — e a conferência do mês" />
      <Tabs urlKey="tab" defaultValue="lancamentos">
        {/* Por frequência de uso: o que se faz todo dia, o que se confere no
            fim do período e o que se configura uma vez. Os valores das abas
            não mudaram — links salvos continuam valendo. */}
        <TabsList className="overflow-x-auto">
          <TabsGroup label="Dia a dia">
            <TabsTrigger value="lancamentos">A pagar e receber</TabsTrigger>
            <TabsTrigger value="caixa">Caixa</TabsTrigger>
            <TabsTrigger value="boletos">Boletos / PIX</TabsTrigger>
            <TabsTrigger value="cheques">Cheques</TabsTrigger>
            <TabsTrigger value="malote">Malote</TabsTrigger>
          </TabsGroup>
          <TabsGroup label="Conferir">
            <TabsTrigger value="conciliacao">Conciliação</TabsTrigger>
            <TabsTrigger value="dre">Resultado (DRE)</TabsTrigger>
          </TabsGroup>
          <TabsGroup label="Configurar">
            <TabsTrigger value="extrato-regras">Regras do extrato</TabsTrigger>
            <TabsTrigger value="plano">Plano de contas</TabsTrigger>
            <TabsTrigger value="centro">Centros de custo</TabsTrigger>
          </TabsGroup>
        </TabsList>
        <TabsContent value="lancamentos"><LancamentosTab /></TabsContent>
        <TabsContent value="caixa"><CaixaTab /></TabsContent>
        <TabsContent value="cheques"><ChequesTab /></TabsContent>
        <TabsContent value="boletos"><BoletosTab /></TabsContent>
        <TabsContent value="dre"><DRETab /></TabsContent>
        <TabsContent value="conciliacao"><ConciliacaoTab /></TabsContent>
        <TabsContent value="malote"><MaloteTab /></TabsContent>
        <TabsContent value="extrato-regras"><ExtratoRegrasTab /></TabsContent>
        <TabsContent value="plano"><PlanoTab /></TabsContent>
        <TabsContent value="centro"><CentroTab /></TabsContent>
      </Tabs>
    </div>
  )
}
