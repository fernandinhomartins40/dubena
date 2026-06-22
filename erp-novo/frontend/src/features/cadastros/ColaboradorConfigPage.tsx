import { useNavigate } from 'react-router-dom'
import { ArrowLeft } from 'lucide-react'
import { Button, PageHeader, Badge, Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui'
import { CadastroApoioTab } from './CadastroApoioTab'

/** Configurações de Colaboradores — cadastros de apoio do RH (menu legado Colaboradores). */
export function ColaboradorConfigPage() {
  const navigate = useNavigate()
  return (
    <div>
      <PageHeader
        breadcrumb={<button onClick={() => navigate('/colaboradores')} className="hover:text-foreground">Colaboradores</button>}
        title="Configurações de Colaboradores"
        subtitle="Cargos, estados civis, parentescos e tipos de exame usados no cadastro de colaboradores"
        action={<Button variant="outline" onClick={() => navigate('/colaboradores')}><ArrowLeft size={16} /> Voltar</Button>}
      />
      <Tabs defaultValue="cargos">
        <TabsList className="overflow-x-auto">
          <TabsTrigger value="cargos">Cargos</TabsTrigger>
          <TabsTrigger value="estados-civis">Estados Civis</TabsTrigger>
          <TabsTrigger value="parentescos">Parentescos</TabsTrigger>
          <TabsTrigger value="tipos-exame">Tipos de Exame</TabsTrigger>
        </TabsList>
        <TabsContent value="cargos"><CadastroApoioTab tipo="cargos" titulo="Cargo" /></TabsContent>
        <TabsContent value="estados-civis"><CadastroApoioTab tipo="estados-civis" titulo="Estado Civil" /></TabsContent>
        <TabsContent value="parentescos"><CadastroApoioTab tipo="parentescos" titulo="Parentesco" /></TabsContent>
        <TabsContent value="tipos-exame">
          <CadastroApoioTab tipo="tipos-exame" titulo="Tipo de Exame" extras={[
            { campo: 'admissional', label: 'Admissional', tipo: 'bool', coluna: (r) => Number(r.admissional) ? <Badge variant="default">Admissional</Badge> : '—' },
          ]} />
        </TabsContent>
      </Tabs>
    </div>
  )
}
