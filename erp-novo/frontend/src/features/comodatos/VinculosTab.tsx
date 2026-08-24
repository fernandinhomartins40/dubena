import { Link2, Link2Off, AlertTriangle, PackageCheck } from 'lucide-react'
import {
  Badge, Card, CardContent, StatCard, AsyncState, Can, toast,
  Select, SelectTrigger, SelectValue, SelectContent, SelectItem,
  type Column, DataTable,
} from '@/components/ui'
import { useVinculos, useSalvarVinculo, type Vinculo, type ConteudoDisponivel } from './api'

const SEM_VINCULO = 'nenhum'

/**
 * Conferência do par casco ↔ gás.
 *
 * **Por que precisa de gente.** O comodato é de "Vasilha P13" e a venda é de
 * "Glp P13" — produtos distintos no cadastro. A vigilância inteira depende de
 * saber que um enche o outro; sem o par, a pergunta "este cliente com 13
 * vasilhames comprou quanto de P13?" não tem resposta.
 *
 * A heurística acerta o caso comum, mas errar aqui não é um detalhe de cadastro:
 * um par errado faz o consumo do cliente ser medido contra um produto que ele
 * nunca compra, o giro dá zero, e o sistema acusa de desvio patrimonial alguém
 * que compra normalmente. Por isso a inferência apenas SUGERE, e esta tela existe
 * para alguém confirmar.
 *
 * A coluna "em comodato" ordena o trabalho: conferir primeiro o vasilhame que
 * tem 60 unidades na rua importa mais que o que tem 2.
 */
export function VinculosTab() {
  const { data, isLoading, error } = useVinculos()
  const salvar = useSalvarVinculo()

  const linhas = data?.data ?? []
  const conteudos = data?.conteudos ?? []

  const semVinculo = linhas.filter((v) => v.produto_retornavel_id === null)
  const emRisco = semVinculo.reduce((s, v) => s + Number(v.em_comodato), 0)

  async function mudar(vinculo: Vinculo, valor: string) {
    try {
      await salvar.mutateAsync({
        id: vinculo.id,
        produto_retornavel_id: valor === SEM_VINCULO ? null : Number(valor),
      })
      toast.success(valor === SEM_VINCULO
        ? `${vinculo.descricao} ficou sem vínculo.`
        : `${vinculo.descricao} vinculado.`)
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Erro ao salvar o vínculo.')
    }
  }

  const columns: Column<Vinculo>[] = [
    {
      key: 'vasilhame', header: 'Vasilhame',
      cell: (v) => (
        <div className="flex items-center gap-2">
          <span className="font-medium">{v.descricao}</span>
          {v.capacidade && <Badge variant="outline">{v.capacidade}</Badge>}
        </div>
      ),
    },
    {
      key: 'posse', header: 'Em comodato', align: 'right',
      cell: (v) => <span className="tabular-nums">{Number(v.em_comodato)}</span>,
    },
    {
      key: 'conteudo', header: 'Enche com',
      cell: (v) => (
        <Can permission="comodato.config" fallback={<RotuloConteudo vinculo={v} conteudos={conteudos} />}>
          <Select
            value={v.produto_retornavel_id === null ? SEM_VINCULO : String(v.produto_retornavel_id)}
            onValueChange={(valor) => mudar(v, valor)}
          >
            <SelectTrigger className="w-64"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value={SEM_VINCULO}>— sem vínculo —</SelectItem>
              {conteudos.map((c) => (
                <SelectItem key={c.id} value={String(c.id)}>
                  {c.descricao}
                  {/* A sugestão da heurística fica marcada: ajuda a conferir
                      sem obrigar a aceitar. */}
                  {v.sugeridos.includes(c.id) ? ' ·  sugerido' : ''}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </Can>
      ),
    },
    {
      key: 'situacao', header: 'Situação',
      cell: (v) => v.produto_retornavel_id === null
        ? <Badge variant="warning"><Link2Off size={12} /> Sem vínculo</Badge>
        : <Badge variant="success"><Link2 size={12} /> Vinculado</Badge>,
    },
  ]

  return (
    <div className="space-y-4">
      <div className="grid gap-3 sm:grid-cols-3">
        <StatCard titulo="Vasilhames no catálogo" valor={linhas.length} icon={PackageCheck} accent="neutral" />
        <StatCard titulo="Sem vínculo" valor={semVinculo.length} icon={Link2Off} accent="primary" />
        <StatCard titulo="Unidades sem cobertura" valor={emRisco} icon={AlertTriangle} accent="destructive" />
      </div>

      <Card>
        <CardContent className="p-3 text-xs text-muted-foreground">
          O vínculo diz qual produto conta como recarga de cada vasilhame. Sem ele o cliente
          aparece com giro zero mesmo comprando normalmente — e vira alerta falso. O par tem que
          ser da mesma empresa.
        </CardContent>
      </Card>

      {semVinculo.length > 0 && emRisco > 0 && (
        <Card className="border-destructive/40">
          <CardContent className="flex items-start gap-2 p-3 text-sm">
            <AlertTriangle size={16} className="mt-0.5 shrink-0 text-destructive" />
            <span>
              <strong className="tabular-nums">{emRisco}</strong> vasilhame(s) estão na rua sob um
              produto sem vínculo. Esses comodatos não entram na conta do giro até o par ser definido.
            </span>
          </CardContent>
        </Card>
      )}

      <AsyncState
        loading={isLoading} error={error} empty={linhas.length === 0}
        emptyIcon={<Link2 />} emptyTitle="Nenhum vasilhame no catálogo"
        emptyDescription="Produtos com 'Vasilha', 'Casco' ou 'Botijão' na descrição aparecem aqui."
      >
        <DataTable columns={columns} rows={linhas} rowKey={(v) => v.id} />
      </AsyncState>
    </div>
  )
}

/** Sem permissão de config, o par é só leitura. */
function RotuloConteudo({ vinculo, conteudos }: {
  vinculo: Vinculo
  conteudos: ConteudoDisponivel[]
}) {
  if (vinculo.produto_retornavel_id === null) {
    return <span className="text-sm text-muted-foreground">—</span>
  }

  const c = conteudos.find((x) => x.id === vinculo.produto_retornavel_id)

  return <span className="text-sm">{c?.descricao ?? `#${vinculo.produto_retornavel_id}`}</span>
}
