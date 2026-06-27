import { useSearchParams } from 'react-router-dom'
import { PageHeader, Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui'
import { useAuth } from '@/lib/auth'
import { UsuariosTab } from './UsuariosTab'
import { PerfisTab } from './PerfisTab'
import { EstruturaTab } from './EstruturaTab'

/**
 * Central de Acessos (A2) — administração de usuários e perfis (papéis) por
 * interface, sem deploy. Substitui o RbacSeeder/código como única via de
 * administração de acesso. Cada aba exige a permissão correspondente; o backend
 * é a autoridade (Gate central — A1).
 */
export function AcessosPage() {
  const [params, setParams] = useSearchParams()
  const { can } = useAuth()
  const podeUsuarios = can('usuario.view')
  const podePapeis = can('papel.view')
  const podeEstrutura = can('unidade.view')

  const abaParam = params.get('tab')
  const padrao = podeUsuarios ? 'usuarios' : podePapeis ? 'perfis' : 'estrutura'
  const validas = ['usuarios', 'perfis', 'estrutura']
  const aba = abaParam && validas.includes(abaParam) ? abaParam : padrao

  return (
    <div>
      <PageHeader title="Acessos" subtitle="Usuários e perfis de permissão" />
      <Tabs value={aba} onValueChange={(v) => setParams(v === padrao ? {} : { tab: v }, { replace: true })}>
        <TabsList>
          {podeUsuarios && <TabsTrigger value="usuarios">Usuários</TabsTrigger>}
          {podePapeis && <TabsTrigger value="perfis">Perfis</TabsTrigger>}
          {podeEstrutura && <TabsTrigger value="estrutura">Estrutura</TabsTrigger>}
        </TabsList>
        {podeUsuarios && <TabsContent value="usuarios"><UsuariosTab /></TabsContent>}
        {podePapeis && <TabsContent value="perfis"><PerfisTab /></TabsContent>}
        {podeEstrutura && <TabsContent value="estrutura"><EstruturaTab /></TabsContent>}
      </Tabs>
    </div>
  )
}
