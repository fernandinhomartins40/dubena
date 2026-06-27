import { useSearchParams } from 'react-router-dom'
import { PageHeader, Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui'
import { useAuth } from '@/lib/auth'
import { UsuariosTab } from './UsuariosTab'
import { PerfisTab } from './PerfisTab'
import { EstruturaTab } from './EstruturaTab'
import { PoliticaSenhaTab } from './PoliticaSenhaTab'

/**
 * Central de Acessos (A2/A3/A5) — administração de usuários, perfis (papéis),
 * estrutura organizacional e política de senha, por interface, sem deploy. Cada
 * aba exige a permissão correspondente; o backend é a autoridade (Gate central).
 */
export function AcessosPage() {
  const [params, setParams] = useSearchParams()
  const { can } = useAuth()
  const podeUsuarios = can('usuario.view')
  const podePapeis = can('papel.view')
  const podeEstrutura = can('unidade.view')
  const podePolitica = can('usuario.edit')

  const abaParam = params.get('tab')
  const padrao = podeUsuarios ? 'usuarios' : podePapeis ? 'perfis' : podeEstrutura ? 'estrutura' : 'politica'
  const validas = ['usuarios', 'perfis', 'estrutura', 'politica']
  const aba = abaParam && validas.includes(abaParam) ? abaParam : padrao

  return (
    <div>
      <PageHeader title="Acessos" subtitle="Usuários, perfis, estrutura e política de senha" />
      <Tabs value={aba} onValueChange={(v) => setParams(v === padrao ? {} : { tab: v }, { replace: true })}>
        <TabsList>
          {podeUsuarios && <TabsTrigger value="usuarios">Usuários</TabsTrigger>}
          {podePapeis && <TabsTrigger value="perfis">Perfis</TabsTrigger>}
          {podeEstrutura && <TabsTrigger value="estrutura">Estrutura</TabsTrigger>}
          {podePolitica && <TabsTrigger value="politica">Política de senha</TabsTrigger>}
        </TabsList>
        {podeUsuarios && <TabsContent value="usuarios"><UsuariosTab /></TabsContent>}
        {podePapeis && <TabsContent value="perfis"><PerfisTab /></TabsContent>}
        {podeEstrutura && <TabsContent value="estrutura"><EstruturaTab /></TabsContent>}
        {podePolitica && <TabsContent value="politica"><PoliticaSenhaTab /></TabsContent>}
      </Tabs>
    </div>
  )
}
