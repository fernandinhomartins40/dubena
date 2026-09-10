import { Card, CardContent, EmptyState } from '@/components/ui'
import { brl, num, pct, data as date, dataHora } from '@/lib/format'

const LABELS: Record<string, string> = {
  a_receber: 'A receber', a_pagar: 'A pagar', saldo_previsto: 'Saldo previsto', por_dia: 'Vendas por dia',
  total_receitas: 'Total de receitas', total_despesas: 'Total de despesas', resultado: 'Resultado', periodo: 'Período',
  quantidade_minima: 'Quantidade mínima', situacao: 'Situação', operacao: 'Operação', comissao_percentual: 'Comissão por percentual',
  comissao_repasse: 'Comissão por repasse', comissao_total: 'Comissão total', desconto_percentual: 'Desconto (%)',
  valor_abastecido: 'Valor dos abastecimentos', dias_sem_comprar: 'Dias sem comprar', ultima_compra: 'Última compra',
  cpf: 'CPF', cnpj: 'CNPJ', km_atual: 'Quilometragem atual', emissao: 'Emissão', descricao: 'Descrição', promocao: 'Promoção',
  endereco: 'Endereço', saidas: 'Saídas', inicio: 'Início', numero: 'Número', serie: 'Série', pendente: 'Quantidade pendente',
}
const MONEY = new Set(['total', 'valor', 'valor_total', 'preco_unitario', 'valor_venda', 'valor_desconto', 'a_receber', 'a_pagar', 'recebido', 'pago', 'saldo_previsto', 'saldo_inicial', 'saldo_final', 'saldo', 'saldo_acumulado', 'resultado_dia', 'entradas', 'saidas', 'total_receitas', 'total_despesas', 'resultado', 'comissao_percentual', 'comissao_repasse', 'comissao_total', 'valor_abastecido'])
export const reportLabel = (key: string) => LABELS[key] ?? key.replace(/_/g, ' ').replace(/^./, (letter) => letter.toUpperCase())
export function reportValue(key: string, value: unknown): string {
  if (value == null || value === '') return '—'
  if (typeof value === 'boolean') return value ? 'Sim' : 'Não'
  if (MONEY.has(key) && Number.isFinite(Number(value))) return brl(Number(value))
  if (key === 'desconto_percentual' && Number.isFinite(Number(value))) return pct(Number(value), 2)
  if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}/.test(value)) return value.length > 10 ? dataHora(value) : date(`${value}T12:00:00`)
  if (typeof value === 'number') return num(value, 0, 3)
  return String(value)
}

/** Preserva todas as propriedades retornadas, sem JSON cru ou soma inferida. */
export function ReportResult({ value, title }: { value: unknown; title?: string }) {
  if (value == null) return <div role="alert" className="rounded-lg border p-5">O relatório não retornou um resultado válido. Tente consultar novamente.</div>
  if (Array.isArray(value)) {
    if (!value.length) return <EmptyState title={title ? `${title}: nenhum registro` : 'Nenhum registro no período'} />
    if (value.some((row) => !row || typeof row !== 'object' || Array.isArray(row))) return <div role="alert">O formato deste relatório não permite exibir a tabela.</div>
    const columns = [...new Set(value.flatMap((row) => Object.keys(row)))]
    return <div className="overflow-x-auto rounded-xl border bg-card"><table className="w-full text-sm">
      <caption className="p-4 text-left font-semibold">{title ?? 'Resultado da consulta'} · {num(value.length)} registros</caption>
      <thead className="bg-muted/60 text-left"><tr>{columns.map((key) => <th scope="col" key={key} className="whitespace-nowrap px-4 py-3 font-medium">{reportLabel(key)}</th>)}</tr></thead>
      <tbody>{value.map((row, index) => <tr key={index} className="border-t">{columns.map((key) => <td key={key} className="px-4 py-3 tabular-nums align-top">
        {row[key] != null && typeof row[key] === 'object' ? <ReportResult value={row[key]} title={reportLabel(key)} /> : reportValue(key, row[key])}
      </td>)}</tr>)}</tbody>
    </table></div>
  }
  if (typeof value !== 'object') return <p>{reportValue('', value)}</p>
  const entries = Object.entries(value)
  if (!entries.length) return <div role="alert">O resumo recebido está vazio. Consulte novamente.</div>
  const scalars = entries.filter(([, item]) => item == null || typeof item !== 'object')
  const sections = entries.filter(([, item]) => item != null && typeof item === 'object')
  return <div className="space-y-5">
    {scalars.length > 0 && <dl className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">{scalars.map(([key, item]) => <Card key={key}><CardContent className="p-5">
      <dt className="text-sm text-muted-foreground">{reportLabel(key)}</dt><dd className="mt-2 break-words text-2xl font-semibold tabular-nums">{reportValue(key, item)}</dd>
    </CardContent></Card>)}</dl>}
    {sections.map(([key, item]) => <section key={key}><h3 className="mb-3 text-base font-semibold">{reportLabel(key)}</h3><ReportResult value={item} title={reportLabel(key)} /></section>)}
  </div>
}
