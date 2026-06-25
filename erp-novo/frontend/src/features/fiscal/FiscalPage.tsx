import { PageHeader, Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui'
import { MalhaTab } from './tabs/MalhaTab'
import { NfeTab } from './tabs/NfeTab'
import { NfEntradaTab } from './tabs/NfEntradaTab'
import { SpedTab } from './tabs/SpedTab'

/** Fiscal (F17.R7) — shell de abas; cada aba vive em ./tabs/*. */
export function FiscalPage() {
  return (
    <div>
      <PageHeader title="Fiscal" subtitle="Malha fiscal, NF-e/NFC-e e SPED" />
      <Tabs defaultValue="malha">
        <TabsList className="overflow-x-auto">
          <TabsTrigger value="malha">Malha Fiscal</TabsTrigger>
          <TabsTrigger value="nfe">NF-e</TabsTrigger>
          <TabsTrigger value="nf-entrada">NF de Entrada</TabsTrigger>
          <TabsTrigger value="sped">SPED</TabsTrigger>
        </TabsList>
        <TabsContent value="malha"><MalhaTab /></TabsContent>
        <TabsContent value="nfe"><NfeTab /></TabsContent>
        <TabsContent value="nf-entrada"><NfEntradaTab /></TabsContent>
        <TabsContent value="sped"><SpedTab /></TabsContent>
      </Tabs>
    </div>
  )
}
