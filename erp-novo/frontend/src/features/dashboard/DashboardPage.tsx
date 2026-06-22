import { useQuery } from '@tanstack/react-query'
import { Users, Package, ShoppingCart, Banknote } from 'lucide-react'
import { api } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import type { ReactNode } from 'react'

interface Resumo {
  clientes: number
  produtos: number
  pedidos: number
  financeiro: number
}

function Card({ titulo, valor, icon, cor }: { titulo: string; valor: number | string; icon: ReactNode; cor: string }) {
  return (
    <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5 flex items-center gap-4">
      <div className={`h-12 w-12 rounded-lg grid place-items-center text-white ${cor}`}>{icon}</div>
      <div>
        <p className="text-2xl font-bold">{typeof valor === 'number' ? valor.toLocaleString('pt-BR') : valor}</p>
        <p className="text-sm text-slate-500">{titulo}</p>
      </div>
    </div>
  )
}

export function DashboardPage() {
  const { user } = useAuth()
  const { data, isLoading } = useQuery<Resumo>({
    queryKey: ['dashboard-resumo'],
    queryFn: async () => (await api.get<Resumo>('/dashboard/resumo')).data,
  })

  const v = (n?: number) => (isLoading ? '…' : (n ?? 0))

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Olá, {user?.name?.split(' ')[0]} 👋</h1>
        <p className="text-slate-500">Visão geral da operação.</p>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <Card titulo="Clientes" valor={v(data?.clientes)} icon={<Users size={22} />} cor="bg-marca-600" />
        <Card titulo="Produtos" valor={v(data?.produtos)} icon={<Package size={22} />} cor="bg-info" />
        <Card titulo="Pedidos" valor={v(data?.pedidos)} icon={<ShoppingCart size={22} />} cor="bg-accent" />
        <Card titulo="Financeiro" valor={v(data?.financeiro)} icon={<Banknote size={22} />} cor="bg-marca-800" />
      </div>

      <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-6">
        <p className="text-sm text-slate-500">
          Nova interface (S1). Os módulos serão migrados aqui um a um — começando por <strong>Clientes</strong>.
        </p>
      </div>
    </div>
  )
}
