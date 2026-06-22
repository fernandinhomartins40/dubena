import { useNavigate } from 'react-router-dom'
import { ArrowLeft } from 'lucide-react'
import { Button, PageHeader, Badge, Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui'
import { CadastroApoioTab } from './CadastroApoioTab'

/** Configurações Financeiras — consolida cadastros de apoio do domínio Financeiro. */
export function FinanceiroConfigPage() {
  const navigate = useNavigate()
  return (
    <div>
      <PageHeader
        title="Configurações Financeiras"
        subtitle="Bancos e tipos de movimento usados no financeiro"
        action={<Button variant="outline" onClick={() => navigate('/')}><ArrowLeft size={16} /> Voltar</Button>}
      />
      <Tabs defaultValue="bancos">
        <TabsList className="overflow-x-auto">
          <TabsTrigger value="bancos">Bancos</TabsTrigger>
          <TabsTrigger value="tipos-movimento">Tipos de Movimento</TabsTrigger>
        </TabsList>
        <TabsContent value="bancos">
          <CadastroApoioTab tipo="bancos" titulo="Banco" extras={[
            { campo: 'codigo', label: 'Código', tipo: 'texto' },
            { campo: 'site', label: 'Site', tipo: 'texto' },
          ]} />
        </TabsContent>
        <TabsContent value="tipos-movimento">
          <CadastroApoioTab tipo="tipos-movimento" titulo="Tipo de Movimento" extras={[
            { campo: 'pagarreceber', label: 'Pagar/Receber', tipo: 'select', opcoes: [{ v: 'P', l: 'Pagar' }, { v: 'R', l: 'Receber' }],
              coluna: (r) => r.pagarreceber === 'P' ? <Badge variant="destructive">Pagar</Badge> : r.pagarreceber === 'R' ? <Badge variant="success">Receber</Badge> : '—' },
            { campo: 'cheque', label: 'Cheque', tipo: 'bool' },
            { campo: 'cartao', label: 'Cartão', tipo: 'bool' },
            { campo: 'valegas', label: 'Vale-Gás', tipo: 'bool' },
            { campo: 'convenio', label: 'Convênio', tipo: 'bool' },
          ]} />
        </TabsContent>
      </Tabs>
    </div>
  )
}
