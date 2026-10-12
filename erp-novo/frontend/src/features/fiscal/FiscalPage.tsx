import { PageHeader, Tabs, TabsList, TabsTrigger, TabsContent, TabsGroup } from '@/components/ui'
import { MalhaTab } from './tabs/MalhaTab'
import { NfeTab } from './tabs/NfeTab'
import { NfEntradaTab } from './tabs/NfEntradaTab'
import { SpedTab } from './tabs/SpedTab'

/** Fiscal (F17.R7) — shell de abas; cada aba vive em ./tabs/*. */
export function FiscalPage() {
  return (
    <div>
      <PageHeader title="Notas fiscais" subtitle="NF-e e NFC-e emitidas, notas de entrada, SPED e as regras de imposto" />
      {/* Abria na "Malha Fiscal" — a tabela de regras de imposto, que se
          configura uma vez — e quem vinha conferir uma nota tinha de achar a
          aba certa. Abre nas notas; a malha fica no grupo "Configurar". Os
          valores das abas não mudaram: "?tab=malha" continua valendo. */}
      <Tabs urlKey="tab" defaultValue="nfe">
        <TabsList className="overflow-x-auto">
          <TabsGroup label="Notas">
            <TabsTrigger value="nfe">Notas emitidas (NF-e / NFC-e)</TabsTrigger>
            <TabsTrigger value="nf-entrada">Notas de entrada</TabsTrigger>
            <TabsTrigger value="sped">SPED</TabsTrigger>
          </TabsGroup>
          <TabsGroup label="Configurar">
            <TabsTrigger value="malha">Regras de imposto (malha fiscal)</TabsTrigger>
          </TabsGroup>
        </TabsList>
        <TabsContent value="nfe"><NfeTab /></TabsContent>
        <TabsContent value="nf-entrada"><NfEntradaTab /></TabsContent>
        <TabsContent value="sped"><SpedTab /></TabsContent>
        <TabsContent value="malha"><MalhaTab /></TabsContent>
      </Tabs>
    </div>
  )
}
