import { useSearchParams } from 'react-router-dom'
import { Badge, PageHeader, Tabs, TabsList, TabsTrigger, TabsContent, TabsGroup } from '@/components/ui'
import { CadastroApoioTab } from '@/features/cadastros/CadastroApoioTab'
import { ConfigGlobalTab } from './ConfigGlobalTab'
import { TaxasEntregaTab } from './taxas-entrega/TaxasEntregaTab'

/**
 * Valores aceitos em `?tab=`. "entrega" faltava aqui: o clique na aba gravava
 * `?tab=entrega`, a validação recusava e a página voltava para "Geral" — as
 * taxas de entrega nunca abriam. Os valores antigos seguem valendo ("geral" é
 * a aba técnica), porque Clientes e Colaboradores apontam para cá por eles.
 */
const ABAS_CONFIG = ['entrega', 'clientes', 'financeiro', 'colaboradores', 'geral'] as const
const PADRAO = 'entrega'

/** Uma linha dizendo o que há na área: a aba sozinha ("Geral") não dizia. */
function Explica({ children }: { children: string }) {
  return <p className="mb-4 text-sm text-muted-foreground">{children}</p>
}

/**
 * Hub central de Configurações (F01) — ÚNICO ponto de administração: unifica a
 * config global do grupo e os cadastros de apoio por domínio. As antigas telas
 * `*ConfigPage` foram removidas em F17.R6 e redirecionam para cá via `?tab=`.
 *
 * A porta de entrada era "Geral": responsável técnico da NF-e, CSRT e servidor
 * de e-mail — o mais técnico do sistema, na frente de quem só queria mudar a
 * taxa de entrega. Agora abre no que o dono ajusta, e o técnico fica por último.
 */
export function ConfiguracoesPage() {
  const [params, setParams] = useSearchParams()
  const tabParam = params.get('tab')
  const aba = ABAS_CONFIG.includes(tabParam as never) ? (tabParam as string) : PADRAO
  // Sub-aba na URL (`?sub=`): dá para mandar o link direto de "Cargos".
  // Valor que não é desta área (link velho, digitado) cai na primeira sub-aba
  // em vez de deixar a área em branco.
  const sub = (validos: string[]) => ({
    value: validos.includes(params.get('sub') ?? '') ? (params.get('sub') as string) : validos[0],
    onValueChange: (v: string) => setParams({ tab: aba, sub: v }, { replace: true }),
  })
  return (
    <div>
      <PageHeader title="Configurações" subtitle="Taxas de entrega, listas usadas nos cadastros e os ajustes técnicos do sistema" />
      <Tabs value={aba} onValueChange={(v) => setParams(v === PADRAO ? {} : { tab: v }, { replace: true })}>
        <TabsList className="overflow-x-auto">
          <TabsGroup label="Negócio">
            <TabsTrigger value="entrega">Taxas de entrega</TabsTrigger>
          </TabsGroup>
          <TabsGroup label="Listas de apoio">
            <TabsTrigger value="clientes">Clientes</TabsTrigger>
            <TabsTrigger value="financeiro">Financeiro</TabsTrigger>
            <TabsTrigger value="colaboradores">Equipe</TabsTrigger>
          </TabsGroup>
          <TabsGroup label="Técnico">
            <TabsTrigger value="geral">Sistema (NF-e, e-mail, SAT)</TabsTrigger>
          </TabsGroup>
        </TabsList>

        {/* Taxas de entrega: quanto cobrar e quanto custa por bairro/distancia. */}
        <TabsContent value="entrega">
          <TaxasEntregaTab />
        </TabsContent>

        <TabsContent value="clientes">
          <Explica>As opções que aparecem nas listas do cadastro de clientes.</Explica>
          <Tabs {...sub(['segmentos', 'tipos-pessoa', 'telefone-tipos', 'contato-tipos', 'contato-situacoes'])}>
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
          <Explica>Os bancos e os tipos de movimento usados nos lançamentos.</Explica>
          <Tabs {...sub(['bancos', 'tipos-movimento'])}>
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
          <Explica>Cargos e as listas usadas na ficha do colaborador.</Explica>
          <Tabs {...sub(['cargos', 'estados-civis', 'parentescos', 'tipos-exame'])}>
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

        <TabsContent value="geral">
          <Explica>Dados que a nota fiscal e o envio de e-mail exigem. Normalmente preenchidos uma vez, com o contador ou o suporte.</Explica>
          <ConfigGlobalTab />
        </TabsContent>
      </Tabs>
    </div>
  )
}
