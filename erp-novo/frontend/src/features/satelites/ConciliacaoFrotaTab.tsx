import { Link2 } from 'lucide-react'
import { Badge, Button, DataTable, type Column, EmptyState, toast } from '@/components/ui'
import { useConciliacaoFrota, useVincularFrota, type VeiculoSemVinculo, type CandidatoFrota } from './extraApi'

/**
 * Conciliação com a frota (F3-09).
 *
 * O mesmo caminhão existe no cadastro de frota (km, óleo, documentos) e aqui
 * (rastreador, posições). A conversão ligou os pares inequívocos pela placa e
 * deixou sem vínculo o que era ambíguo ou sem par — de propósito: o palpite
 * errado ligaria a manutenção de um caminhão à posição de outro. Esta lista é o
 * que torna essa pendência visível; antes ela só existia na API.
 *
 * Nunca vincula sozinha: mesmo com um único candidato, quem confirma é a pessoa.
 */
export function ConciliacaoFrotaTab() {
  const { data, isLoading, error, refetch } = useConciliacaoFrota()
  const vincular = useVincularFrota()

  async function onVincular(v: VeiculoSemVinculo, c: CandidatoFrota) {
    try {
      await vincular.mutateAsync({ id: v.id, placa: v.placa, veiculo_frota_id: c.id })
      toast.success(`${v.placa} vinculado à frota.`)
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao vincular.') }
  }

  const columns: Column<VeiculoSemVinculo>[] = [
    { key: 'placa', header: 'Rastreado', cell: (v) => <><span className="font-medium">{v.placa}</span>{v.descricao && <span className="text-muted-foreground"> · {v.descricao}</span>}{!v.ativo && <Badge variant="secondary" className="ml-2">Inativo</Badge>}</> },
    {
      key: 'situacao', header: 'Situação',
      cell: (v) => v.candidatos.length === 0 ? <Badge variant="secondary">Sem par na frota</Badge>
        : v.candidatos.length === 1 ? <Badge variant="success">Um candidato</Badge>
          : <Badge variant="warning">Placa repetida na frota</Badge>,
    },
    {
      key: 'candidatos', header: 'Veículo da frota',
      cell: (v) => v.candidatos.length === 0
        ? <span className="text-sm text-muted-foreground">Nenhum veículo da frota com esta placa. Cadastre-o na Frota ou corrija a placa.</span>
        : (
          <ul className="space-y-1">
            {v.candidatos.map((c) => (
              <li key={c.id} className="flex items-center justify-between gap-3">
                <span className="text-sm">{c.placa}{c.descricao && <span className="text-muted-foreground"> · {c.descricao}</span>} <span className="text-muted-foreground">(#{c.id})</span></span>
                <Button variant="outline" size="sm" loading={vincular.isPending} onClick={() => { void onVincular(v, c) }}><Link2 size={14} /> Vincular</Button>
              </li>
            ))}
          </ul>
        ),
    },
  ]
  return (
    <DataTable columns={columns} rows={data} loading={isLoading} error={error} onRetry={() => { void refetch() }} rowKey={(v) => v.id}
      empty={<EmptyState icon={<Link2 />} title="Tudo conciliado" description="Todos os veículos rastreados estão ligados a um veículo da frota." />} />
  )
}
