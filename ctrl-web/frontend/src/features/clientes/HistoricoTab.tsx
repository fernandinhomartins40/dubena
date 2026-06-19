import { useHistorico } from './api'

export function HistoricoTab({ clienteId }: { clienteId: number }) {
  const { data, isLoading } = useHistorico(clienteId)
  return (
    <div className="col-span-full">
      <table className="w-full text-sm border border-slate-200 dark:border-slate-800 rounded-lg overflow-hidden">
        <thead className="bg-slate-50 dark:bg-slate-800/50 text-left text-slate-500">
          <tr><th className="px-4 py-2">Pedido</th><th className="px-4 py-2">Data</th><th className="px-4 py-2">Valor</th></tr>
        </thead>
        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
          {isLoading ? (
            <tr><td colSpan={3} className="px-4 py-6 text-center text-slate-400">Carregando…</td></tr>
          ) : data && data.length > 0 ? (
            data.map((h: any) => (
              <tr key={h.id}>
                <td className="px-4 py-2">#{h.id}</td>
                <td className="px-4 py-2">{h.datahora ? new Date(h.datahora).toLocaleString('pt-BR') : '—'}</td>
                <td className="px-4 py-2">{Number(h.valorvenda ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}</td>
              </tr>
            ))
          ) : (
            <tr><td colSpan={3} className="px-4 py-6 text-center text-slate-400">Sem pedidos.</td></tr>
          )}
        </tbody>
      </table>
    </div>
  )
}
