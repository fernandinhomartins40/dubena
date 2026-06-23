import { useQuery } from '@tanstack/react-query'
import { Users, Package, ShoppingCart, Banknote } from 'lucide-react'
import { api } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { StatCard, type StatAccent } from '@/components/ui'

interface Resumo {
  clientes: number
  produtos: number
  pedidos: number
  financeiro: number
}

export function DashboardPage() {
  const { user } = useAuth()
  const { data, isLoading } = useQuery<Resumo>({
    queryKey: ['dashboard-resumo'],
    queryFn: async () => (await api.get<Resumo>('/dashboard/resumo')).data,
  })

  const v = (n?: number) => (isLoading ? '…' : (n ?? 0))

  // Acentos com propósito (tokens centrais): relacionamento, catálogo, operação, dinheiro.
  const cards: { titulo: string; valor: number | string; icon: typeof Users; accent: StatAccent }[] = [
    { titulo: 'Clientes', valor: v(data?.clientes), icon: Users, accent: 'primary' },
    { titulo: 'Produtos', valor: v(data?.produtos), icon: Package, accent: 'neutral' },
    { titulo: 'Pedidos', valor: v(data?.pedidos), icon: ShoppingCart, accent: 'lime' },
    { titulo: 'Financeiro', valor: v(data?.financeiro), icon: Banknote, accent: 'primary' },
  ]

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">Olá, {user?.name?.split(' ')[0]} 👋</h1>
        <p className="text-muted-foreground">Visão geral da operação.</p>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {cards.map((c) => <StatCard key={c.titulo} {...c} />)}
      </div>

      <div className="rounded-xl border border-border bg-card p-6">
        <p className="text-sm text-muted-foreground">
          Bem-vindo ao novo painel. Navegue pelos módulos no menu lateral, organizado por seção.
        </p>
      </div>
    </div>
  )
}
