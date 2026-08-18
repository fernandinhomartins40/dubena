import { useState } from 'react'
import { LayoutGrid, List } from 'lucide-react'
import { PageHeader, Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui'
import { KanbanView } from './KanbanView'
import { ListaView } from './ListaView'
import { FichaDialog, NovoPedidoDialog } from './PedidoDialogs'
import { PainelChamadas } from './PainelChamadas'

/**
 * Página de pedidos — shell (FE-4): antes era um único arquivo de 455 linhas.
 * As três visões grandes foram extraídas: KanbanView, ListaView e os diálogos
 * (Ficha/Novo). Este arquivo só orquestra as abas e a ficha compartilhada.
 */
export function PedidosPage() {
  const [verFicha, setVerFicha] = useState<number | null>(null)
  return (
    <div>
      <PageHeader title="Pedidos" subtitle="Painel de vendas e jornada do pedido" action={<NovoPedidoDialog />} />
      {/* Bina (T4.4): some sozinho quando nao ha chamada tocando. */}
      <PainelChamadas />
      <Tabs defaultValue="kanban">
        <TabsList><TabsTrigger value="kanban"><LayoutGrid size={15} className="mr-1" /> Kanban</TabsTrigger><TabsTrigger value="lista"><List size={15} className="mr-1" /> Lista</TabsTrigger></TabsList>
        <TabsContent value="kanban"><KanbanView onOpen={setVerFicha} /></TabsContent>
        <TabsContent value="lista"><ListaView onOpen={setVerFicha} /></TabsContent>
      </Tabs>
      <FichaDialog id={verFicha} onClose={() => setVerFicha(null)} />
    </div>
  )
}
