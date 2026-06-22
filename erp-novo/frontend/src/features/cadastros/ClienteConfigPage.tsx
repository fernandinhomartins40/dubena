import { useNavigate } from 'react-router-dom'
import { ArrowLeft } from 'lucide-react'
import { Button, PageHeader, Badge, Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui'
import { CadastroApoioTab } from './CadastroApoioTab'

/** Configurações de Clientes — consolida cadastros de apoio do domínio Cliente. */
export function ClienteConfigPage() {
  const navigate = useNavigate()
  return (
    <div>
      <PageHeader
        breadcrumb={<button onClick={() => navigate('/clientes')} className="hover:text-foreground">Clientes</button>}
        title="Configurações de Clientes"
        subtitle="Segmentos, tipos e situações usados no cadastro de clientes"
        action={<Button variant="outline" onClick={() => navigate('/clientes')}><ArrowLeft size={16} /> Voltar</Button>}
      />
      <Tabs defaultValue="segmentos">
        <TabsList className="overflow-x-auto">
          <TabsTrigger value="segmentos">Segmentos</TabsTrigger>
          <TabsTrigger value="tipos-pessoa">Tipos de Pessoa</TabsTrigger>
          <TabsTrigger value="telefone-tipos">Tipos de Telefone</TabsTrigger>
          <TabsTrigger value="contato-tipos">Tipos de Contato</TabsTrigger>
          <TabsTrigger value="contato-situacoes">Situações de Contato</TabsTrigger>
        </TabsList>
        <TabsContent value="segmentos"><CadastroApoioTab tipo="segmentos" titulo="Segmento" /></TabsContent>
        <TabsContent value="tipos-pessoa">
          <CadastroApoioTab tipo="tipos-pessoa" titulo="Tipo de Pessoa" extras={[
            { campo: 'tipopessoacadastro', label: 'Pessoa', tipo: 'select', opcoes: [{ v: 'F', l: 'Física' }, { v: 'J', l: 'Jurídica' }],
              coluna: (r) => r.tipopessoacadastro === 'J' ? <Badge variant="secondary">Jurídica</Badge> : r.tipopessoacadastro === 'F' ? <Badge variant="secondary">Física</Badge> : '—' },
          ]} />
        </TabsContent>
        <TabsContent value="telefone-tipos">
          <CadastroApoioTab tipo="telefone-tipos" titulo="Tipo de Telefone" extras={[
            { campo: 'celular', label: 'Celular', tipo: 'bool', coluna: (r) => Number(r.celular) ? <Badge variant="default">Celular</Badge> : '—' },
          ]} />
        </TabsContent>
        <TabsContent value="contato-tipos"><CadastroApoioTab tipo="contato-tipos" titulo="Tipo de Contato" /></TabsContent>
        <TabsContent value="contato-situacoes"><CadastroApoioTab tipo="contato-situacoes" titulo="Situação de Contato" /></TabsContent>
      </Tabs>
    </div>
  )
}
