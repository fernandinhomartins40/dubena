import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Search, Plus, ChevronLeft, ChevronRight, Pencil, Trash2 } from 'lucide-react'
import { useClientes, useExcluirCliente } from './api'
import { Button, Card, PageHeader } from '@/components/ui'
import { useAuth } from '@/lib/auth'

export function ClientesListPage() {
  const navigate = useNavigate()
  const { can } = useAuth()
  const [busca, setBusca] = useState('')
  const [q, setQ] = useState('')
  const [page, setPage] = useState(1)
  const { data, isLoading, isFetching } = useClientes(q, page)
  const excluir = useExcluirCliente()

  function buscar(e: React.FormEvent) {
    e.preventDefault()
    setPage(1)
    setQ(busca)
  }

  return (
    <div>
      <PageHeader
        title="Clientes"
        subtitle={data ? `${data.meta.total.toLocaleString('pt-BR')} clientes` : 'Carregando…'}
        action={can('cliente.create') && (
          <Button onClick={() => navigate('/clientes/novo')}><Plus size={16} /> Novo cliente</Button>
        )}
      />

      <Card className="p-4 mb-4">
        <form onSubmit={buscar} className="flex gap-2">
          <div className="relative flex-1">
            <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input
              value={busca} onChange={(e) => setBusca(e.target.value)}
              placeholder="Buscar por nome, fantasia, CPF/CNPJ ou código…"
              className="w-full rounded-md border border-slate-300 dark:border-slate-700 bg-transparent pl-9 pr-3 py-2 outline-none focus:ring-2 focus:ring-marca-500"
            />
          </div>
          <Button type="submit">Buscar</Button>
        </form>
      </Card>

      <Card className="overflow-hidden">
        <table className="w-full text-sm">
          <thead className="bg-slate-50 dark:bg-slate-800/50 text-left text-slate-500">
            <tr>
              <th className="px-4 py-3 font-medium">Nome</th>
              <th className="px-4 py-3 font-medium">CPF/CNPJ</th>
              <th className="px-4 py-3 font-medium">E-mail</th>
              <th className="px-4 py-3 font-medium">UF</th>
              <th className="px-4 py-3 font-medium text-right">Ações</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
            {isLoading ? (
              <tr><td colSpan={5} className="px-4 py-8 text-center text-slate-400">Carregando…</td></tr>
            ) : data && data.data.length > 0 ? (
              data.data.map((c) => (
                <tr key={c.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                  <td className="px-4 py-3 cursor-pointer" onClick={() => navigate(`/clientes/${c.id}`)}>
                    <div className="font-medium">{c.nome} {c.ativo === 0 && <span className="text-xs text-red-500">(inativo)</span>}</div>
                    {c.fantasia && <div className="text-xs text-slate-400">{c.fantasia}</div>}
                  </td>
                  <td className="px-4 py-3 text-slate-500">{c.cpf || c.cnpj || '—'}</td>
                  <td className="px-4 py-3 text-slate-500">{c.email || '—'}</td>
                  <td className="px-4 py-3 text-slate-500">{c.uf || '—'}</td>
                  <td className="px-4 py-3">
                    <div className="flex items-center justify-end gap-1">
                      {can('cliente.edit') && (
                        <button onClick={() => navigate(`/clientes/${c.id}`)} title="Editar"
                          className="p-1.5 rounded text-slate-400 hover:text-marca-600 hover:bg-slate-100 dark:hover:bg-slate-800"><Pencil size={16} /></button>
                      )}
                      {can('cliente.delete') && (
                        <button
                          onClick={() => { if (confirm(`Excluir o cliente "${c.nome}"?`)) excluir.mutate(c.id) }}
                          title="Excluir"
                          className="p-1.5 rounded text-slate-400 hover:text-red-600 hover:bg-slate-100 dark:hover:bg-slate-800"><Trash2 size={16} /></button>
                      )}
                    </div>
                  </td>
                </tr>
              ))
            ) : (
              <tr><td colSpan={5} className="px-4 py-8 text-center text-slate-400">Nenhum cliente encontrado.</td></tr>
            )}
          </tbody>
        </table>
      </Card>

      {data && data.meta.last_page > 1 && (
        <div className="flex items-center justify-between mt-4 text-sm text-slate-500">
          <span>Página {data.meta.current_page} de {data.meta.last_page} {isFetching && '· atualizando…'}</span>
          <div className="flex gap-2">
            <Button variant="ghost" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}><ChevronLeft size={16} /> Anterior</Button>
            <Button variant="ghost" disabled={page >= data.meta.last_page} onClick={() => setPage((p) => p + 1)}>Próxima <ChevronRight size={16} /></Button>
          </div>
        </div>
      )}
    </div>
  )
}
