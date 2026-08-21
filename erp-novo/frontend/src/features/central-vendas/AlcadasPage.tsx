import { useState } from 'react'
import { BadgePercent, Plus, Trash2, TriangleAlert } from 'lucide-react'
import {
  Button, Card, CardContent, PageHeader, Badge, AsyncState, Field, Input, toast, Can,
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogClose,
  Select, SelectTrigger, SelectValue, SelectContent, SelectItem,
} from '@/components/ui'
import { brl } from '@/lib/format'
import { useAlcadas, useSalvarAlcada, useExcluirAlcada, type Alcada } from './alcadaApi'

/**
 * Alçadas de desconto (F2) — quanto cada perfil pode tirar do preço.
 *
 * O aviso de "nenhuma regra" não é decorativo: sem cadastro, a verificação
 * fail-closed impede QUALQUER desconto. Quem chega aqui precisa entender isso
 * antes de sair achando que o sistema está com defeito.
 */
export function AlcadasPage() {
  const [editando, setEditando] = useState<Alcada | 'nova' | null>(null)
  const alcadas = useAlcadas()
  const excluir = useExcluirAlcada()

  const lista = alcadas.data ?? []

  const remover = async (a: Alcada) => {
    try {
      await excluir.mutateAsync(a.id)
      toast.success('Regra removida.')
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Não foi possível remover.')
    }
  }

  return (
    <div>
      <PageHeader
        title="Alçadas de desconto"
        subtitle="Quanto cada perfil concede sem passar pela Central"
        action={
          <Can permission="venda.alcada">
            <Button onClick={() => setEditando('nova')}>
              <Plus size={15} className="mr-1" /> Nova regra
            </Button>
          </Can>
        }
      />

      {lista.length === 0 && !alcadas.isLoading && (
        <Card className="mb-4 border-destructive/40">
          <CardContent className="p-3 flex items-start gap-2 text-sm">
            <TriangleAlert size={17} className="text-destructive shrink-0 mt-0.5" />
            <div>
              <div className="font-medium">Nenhuma alçada cadastrada</div>
              <div className="text-muted-foreground">
                Sem regra, o teto é zero e <strong>ninguém concede desconto</strong> — nem em campo,
                nem no balcão. Cadastre ao menos a regra geral da empresa.
              </div>
            </div>
          </CardContent>
        </Card>
      )}

      <AsyncState
        loading={alcadas.isLoading}
        error={alcadas.error}
        empty={false}
      >
        <div className="space-y-2">
          {lista.map((a) => (
            <Card key={a.id}>
              <CardContent className="p-3 flex items-center justify-between gap-3">
                <div className="min-w-0">
                  <div className="font-medium flex items-center gap-2">
                    {a.produto ?? 'Todos os produtos'}
                    {!a.ativo && <Badge variant="secondary">Inativa</Badge>}
                  </div>
                  <div className="text-xs text-muted-foreground">
                    até {a.percentual_max}%
                    {a.valor_max !== null && <> · máx. {brl(a.valor_max)}</>}
                    {' · '}sobre o preço {a.base_calculo === 'tabela' ? 'de tabela' : 'praticado'}
                    {!a.permite_solicitar && ' · sem envio à Central'}
                  </div>
                </div>
                <div className="flex items-center gap-2 shrink-0">
                  <Can permission="venda.alcada">
                    <Button size="sm" variant="outline" onClick={() => setEditando(a)}>Editar</Button>
                    <Button size="sm" variant="ghost" onClick={() => remover(a)}>
                      <Trash2 size={15} />
                    </Button>
                  </Can>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      </AsyncState>

      <AlcadaDialog alvo={editando} onClose={() => setEditando(null)} />
    </div>
  )
}

function AlcadaDialog({ alvo, onClose }: { alvo: Alcada | 'nova' | null; onClose: () => void }) {
  const salvar = useSalvarAlcada()
  const existente = alvo !== 'nova' && alvo !== null ? alvo : null

  const [percentual, setPercentual] = useState('')
  const [valorMax, setValorMax] = useState('')
  const [base, setBase] = useState<'tabela' | 'praticado'>('tabela')
  const [produtoId, setProdutoId] = useState('')

  // Preenche ao abrir uma regra existente (e limpa ao abrir "nova").
  const [ultimoAlvo, setUltimoAlvo] = useState<number | 'nova' | null>(null)
  const chaveAtual = existente?.id ?? (alvo === 'nova' ? 'nova' : null)
  if (chaveAtual !== ultimoAlvo) {
    setUltimoAlvo(chaveAtual)
    setPercentual(existente ? String(existente.percentual_max) : '')
    setValorMax(existente?.valor_max != null ? String(existente.valor_max) : '')
    setBase(existente?.base_calculo ?? 'tabela')
    setProdutoId(existente?.produto_id != null ? String(existente.produto_id) : '')
  }

  const gravar = async () => {
    const pct = Number(percentual.replace(',', '.'))
    if (Number.isNaN(pct) || pct < 0 || pct > 100) {
      toast.error('Percentual deve ficar entre 0 e 100.')
      return
    }

    try {
      await salvar.mutateAsync({
        id: existente?.id,
        percentual_max: pct,
        valor_max: valorMax === '' ? null : Number(valorMax.replace(',', '.')),
        base_calculo: base,
        produto_id: produtoId === '' ? null : Number(produtoId),
      })
      toast.success('Alçada salva.')
      onClose()
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Não foi possível salvar.')
    }
  }

  return (
    <Dialog open={alvo !== null} onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <BadgePercent size={17} /> {existente ? 'Editar alçada' : 'Nova alçada'}
          </DialogTitle>
        </DialogHeader>

        <div className="space-y-3">
          <Field label="Percentual máximo (%)">
            <Input value={percentual} onChange={(e) => setPercentual(e.target.value)} inputMode="decimal" placeholder="5" />
          </Field>

          <Field label="Teto em R$ (opcional — vence o menor)">
            <Input value={valorMax} onChange={(e) => setValorMax(e.target.value)} inputMode="decimal" placeholder="sem teto" />
          </Field>

          <Field label="Base de cálculo">
            <Select value={base} onValueChange={(v) => setBase(v as 'tabela' | 'praticado')}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="tabela">Preço de tabela</SelectItem>
                <SelectItem value="praticado">Preço praticado (já negociado)</SelectItem>
              </SelectContent>
            </Select>
          </Field>
          <p className="text-xs text-muted-foreground -mt-1">
            "Tabela" evita desconto em cascata em cliente que já tem preço especial.
          </p>

          <Field label="Produto (id) — vazio vale para todos">
            <Input value={produtoId} onChange={(e) => setProdutoId(e.target.value)} inputMode="numeric" placeholder="todos" />
          </Field>
        </div>

        <DialogFooter>
          <DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose>
          <Button onClick={gravar} disabled={salvar.isPending}>Salvar</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
