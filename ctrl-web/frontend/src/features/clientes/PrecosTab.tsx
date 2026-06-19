import { usePrecos } from './api'

export function PrecosTab({ clienteId }: { clienteId: number }) {
  const { data, isLoading } = usePrecos(clienteId)
  return (
    <div className="col-span-full">
      <p className="text-sm text-slate-500 mb-3">Preços especiais por produto deste cliente.</p>
      <table className="w-full text-sm border border-slate-200 dark:border-slate-800 rounded-lg overflow-hidden">
        <thead className="bg-slate-50 dark:bg-slate-800/50 text-left text-slate-500">
          <tr><th className="px-4 py-2">Produto</th><th className="px-4 py-2">Preço</th></tr>
        </thead>
        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
          {isLoading ? (
            <tr><td colSpan={2} className="px-4 py-6 text-center text-slate-400">Carregando…</td></tr>
          ) : data && data.length > 0 ? (
            data.map((p: any) => (
              <tr key={p.id}>
                <td className="px-4 py-2">{p.produto ?? `#${p.produto_id}`}</td>
                <td className="px-4 py-2">{Number(p.preco ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}</td>
              </tr>
            ))
          ) : (
            <tr><td colSpan={2} className="px-4 py-6 text-center text-slate-400">Nenhum preço especial.</td></tr>
          )}
        </tbody>
      </table>
    </div>
  )
}
