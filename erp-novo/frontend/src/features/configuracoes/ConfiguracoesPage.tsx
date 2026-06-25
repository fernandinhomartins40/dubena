import { useSearchParams } from 'react-router-dom'
import { Badge, PageHeader, Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui'
import { CadastroApoioTab } from '@/features/cadastros/CadastroApoioTab'
import { ConfigGlobalTab } from './ConfigGlobalTab'

const ABAS = ['geral', 'clientes', 'financeiro', 'colaboradores'] as const

/**
 * Hub central de Configurações (F01) — ÚNICO ponto de administração: unifica a
 * config global do grupo (Geral) e os cadastros de apoio por domínio
 * (Clientes/Financeiro/Colaboradores). As antigas telas `*ConfigPage` foram
 * removidas em F17.R6 e redirecionam para cá via `?tab=`.
 */
export function ConfiguracoesPage() {
  const [params, setParams] = useSearchParams()
  const tabParam = params.get('tab')
  const aba = ABAS.includes(tabParam as any) ? (tabParam as string) : 'geral'
  return (
    <div>
      <PageHeader title="Configurações" subtitle="Configuração global do grupo e cadastros de apoio" />
      <Tabs value={aba} onValueChange={(v) => setParams(v === 'geral' ? {} : { tab: v }, { replace: true })}>
        <TabsList className="overflow-x-auto">
          <TabsTrigger value="geral">Geral</TabsTrigger>
          <TabsTrigger value="clientes">Clientes</TabsTrigger>
          <TabsTrigger value="financeiro">Financeiro</TabsTrigger>
          <TabsTrigger value="colaboradores">Colaboradores</TabsTrigger>
        </TabsList>

        <TabsContent value="geral"><ConfigGlobalTab /></TabsContent>

        <TabsContent value="clientes">
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
        </TabsContent>

        <TabsContent value="financeiro">
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
        </TabsContent>

        <TabsContent value="colaboradores">
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
        </TabsContent>
      </Tabs>
    </div>
  )
}
