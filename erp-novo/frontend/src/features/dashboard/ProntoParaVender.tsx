import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { CheckCircle2, CircleAlert } from 'lucide-react'
import { api } from '@/lib/api'
import { AsyncState } from '@/components/ui'

interface Item { chave: string; titulo: string; ok: boolean; porque: string; link: string }
interface Prontidao { pronto: boolean; itens: Item[] }

/**
 * PIE R3-A-11 / R3-G-22 — "pronto para vender?" no Início.
 *
 * A revenda nova descobria a ordem errando. Aqui cada pendência diz o porquê e
 * leva à tela que resolve. Some quando tudo está pronto: um checklist
 * permanente vira papel de parede e deixa de ser lido.
 */
export function ProntoParaVender() {
  const query = useQuery<Prontidao>({
    queryKey: ['dashboard-pronto-para-vender'],
    queryFn: async () => (await api.get<{ data: Prontidao }>('/dashboard/pronto-para-vender')).data.data,
  })
  const d = query.data
  if (d?.pronto) return null
  const pendentes = d?.itens.filter((i) => !i.ok) ?? []
  const feitos = d?.itens.filter((i) => i.ok) ?? []

  return <section aria-labelledby="pronto-titulo" className="rounded-xl border bg-card p-5">
    <h2 id="pronto-titulo" className="text-lg font-semibold">Pronto para vender?</h2>
    <p className="mb-3 text-sm text-muted-foreground">
      {d ? `${feitos.length} de ${d.itens.length} itens prontos. Resolva o que falta para registrar e entregar o primeiro pedido.` : 'Conferindo a configuração da revenda…'}
    </p>
    <AsyncState loading={query.isLoading} error={query.error} onRetry={() => { void query.refetch() }}>
      <ul className="divide-y">
        {pendentes.map((i) => <li key={i.chave} className="flex flex-wrap items-start justify-between gap-3 py-3 text-sm">
          <div className="flex gap-2">
            <CircleAlert size={18} className="mt-0.5 shrink-0 text-destructive" aria-hidden="true" />
            <div><p className="font-medium">{i.titulo} <span className="sr-only">(pendente)</span></p><p className="text-muted-foreground">{i.porque}</p></div>
          </div>
          <Link to={i.link} className="font-medium text-accent-foreground underline underline-offset-4">Resolver<span className="sr-only">: {i.titulo}</span></Link>
        </li>)}
        {feitos.map((i) => <li key={i.chave} className="flex items-center gap-2 py-3 text-sm text-muted-foreground">
          <CheckCircle2 size={18} className="shrink-0 text-success" aria-hidden="true" />{i.titulo} <span className="sr-only">(pronto)</span>
        </li>)}
      </ul>
    </AsyncState>
  </section>
}
