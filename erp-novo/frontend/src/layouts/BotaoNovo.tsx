import { useNavigate } from 'react-router-dom'
import { Package, PackagePlus, Plus, ShoppingCart, UserPlus } from 'lucide-react'
import { useAuth } from '@/lib/auth'
import {
  Button, DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger,
} from '@/components/ui'

/**
 * "Novo" no topo de toda tela: criar algo sem precisar saber em que módulo
 * aquilo mora. Antes, lançar um pedido exigia ir a Pedidos e achar o botão.
 *
 * Só entra destino que abre a criação DIRETO (o pedido abre o diálogo por
 * `?novo=1`); e só o que a pessoa pode criar — sem nenhuma permissão, o botão
 * nem aparece, em vez de oferecer um menu vazio.
 */
export const ACOES_NOVO = [
  { rotulo: 'Pedido', to: '/pedidos?novo=1', permission: 'pedido.create', icon: ShoppingCart },
  { rotulo: 'Cliente', to: '/clientes/novo', permission: 'cliente.create', icon: UserPlus },
  { rotulo: 'Produto', to: '/produtos/novo', permission: 'produto.create', icon: Package },
  { rotulo: 'Entrada no estoque', to: '/estoque?tab=acerto', permission: 'estoque.edit', icon: PackagePlus },
] as const

export function BotaoNovo() {
  const { can } = useAuth()
  const navigate = useNavigate()
  const acoes = ACOES_NOVO.filter((a) => can(a.permission))
  if (acoes.length === 0) return null

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button size="sm" aria-label="Criar novo"><Plus size={16} /><span className="hidden sm:inline">Novo</span></Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-48">
        <DropdownMenuLabel>Criar novo…</DropdownMenuLabel>
        {acoes.map(({ icon: Icon, ...a }) => (
          <DropdownMenuItem key={a.to} onClick={() => navigate(a.to)}><Icon /> {a.rotulo}</DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
