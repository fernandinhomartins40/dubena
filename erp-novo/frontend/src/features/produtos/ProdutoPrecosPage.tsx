import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { ArrowLeft, Eye, Check, ArrowRight } from 'lucide-react'
import {
  Button, Card, CardContent, PageHeader, Field, Input, Badge, DataTable, type Column,
  AsyncSelect, Select, SelectTrigger, SelectValue, SelectContent, SelectItem, toast,
} from '@/components/ui'
import { usePrecosPreview, usePrecosAplicar, type PrecoPreviewItem, type PrecoParams } from './api'
import { brl as moeda } from '@/lib/format'

export function ProdutoPrecosPage() {
  const navigate = useNavigate()
  const [tipo, setTipo] = useState<'percentual' | 'fixo'>('percentual')
  const [valor, setValor] = useState('')
  const [classeId, setClasseId] = useState<number | null>(null)
  const [classeLabel, setClasseLabel] = useState<string | null>(null)
  const [preview, setPreview] = useState<PrecoPreviewItem[] | null>(null)

  const previewMut = usePrecosPreview()
  const aplicar = usePrecosAplicar()

  function params(): PrecoParams { return { tipo, valor: parseFloat(valor) || 0, classe_id: classeId } }

  async function onPreview() {
    if (valor === '') { toast.error('Informe o valor do ajuste.'); return }
    try { setPreview(await previewMut.mutateAsync(params())) }
    catch { toast.error('Erro ao gerar a prévia.') }
  }

  async function onAplicar() {
    try {
      const r = await aplicar.mutateAsync(params())
      toast.success(r.message ?? 'Preços atualizados.')
      setPreview(null); setValor('')
    } catch { toast.error('Erro ao aplicar os preços.') }
  }

  const columns: Column<PrecoPreviewItem>[] = [
    { key: 'descricao', header: 'Produto', cell: (p) => <span className="font-medium">{p.descricao}</span> },
    { key: 'atual', header: 'Preço atual', align: 'right', cell: (p) => <span className="tabular-nums text-muted-foreground">{moeda(p.atual)}</span> },
    {
      key: 'seta', header: '', align: 'center', width: 'w-10',
      cell: () => <ArrowRight size={14} className="mx-auto text-muted-foreground" />,
    },
    {
      key: 'novo', header: 'Novo preço', align: 'right',
      cell: (p) => (
        <span className={`tabular-nums font-medium ${p.novo > p.atual ? 'text-success' : p.novo < p.atual ? 'text-destructive' : ''}`}>{moeda(p.novo)}</span>
      ),
    },
  ]

  return (
    <div>
      <PageHeader
        breadcrumb={<button onClick={() => navigate('/produtos')} className="hover:text-foreground">Produtos</button>}
        title="Atualizar preços em massa"
        subtitle="Gere uma prévia antes de aplicar. Nada é alterado até você confirmar."
        action={<Button variant="outline" onClick={() => navigate('/produtos')}><ArrowLeft size={16} /> Voltar</Button>}
      />

      <Card className="mb-4"><CardContent className="pt-6 grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <Field label="Tipo de ajuste">
          <Select value={tipo} onValueChange={(v) => setTipo(v as 'percentual' | 'fixo')}>
            <SelectTrigger><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="percentual">Percentual (%)</SelectItem>
              <SelectItem value="fixo">Valor fixo (R$)</SelectItem>
            </SelectContent>
          </Select>
        </Field>
        <Field label={tipo === 'percentual' ? 'Ajuste (%)' : 'Novo valor (R$)'} hint={tipo === 'percentual' ? 'Use negativo para reduzir' : undefined}>
          <Input type="number" step="0.01" value={valor} onChange={(e) => setValor(e.target.value)} />
        </Field>
        <Field label="Filtrar por classe (opcional)">
          <AsyncSelect endpoint="/lookups/produto-classes" value={classeId} valueLabel={classeLabel}
            onChange={(id, opt) => { setClasseId(id); setClasseLabel(opt?.label ?? null) }} />
        </Field>
        <Button variant="secondary" loading={previewMut.isPending} onClick={onPreview}><Eye size={16} /> Gerar prévia</Button>
      </CardContent></Card>

      {preview && (
        <>
          <div className="mb-3 flex items-center justify-between">
            <Badge variant="default">{preview.length} produto(s) afetado(s)</Badge>
            <Button loading={aplicar.isPending} onClick={onAplicar} disabled={preview.length === 0}><Check size={16} /> Aplicar alterações</Button>
          </div>
          <DataTable columns={columns} rows={preview} rowKey={(p) => p.id} />
        </>
      )}
    </div>
  )
}
