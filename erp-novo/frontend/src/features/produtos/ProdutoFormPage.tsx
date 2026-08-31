import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { ArrowLeft, Save, AlertCircle, Warehouse } from 'lucide-react'
import {
  Button, Card, CardContent, PageHeader, Field, Input, Textarea, CheckboxField, AsyncSelect,
  Tabs, TabsList, TabsTrigger, TabsContent, EmptyState, Skeleton, toast,
  Select, SelectTrigger, SelectValue, SelectContent, SelectItem,
} from '@/components/ui'
import { OrigensTab } from './OrigensTab'
import { useResourceForm } from '@/lib/useResourceForm'
import { qtd } from '@/lib/format'
import { useProduto, useSalvarProduto, useEstoqueProduto, type ProdutoForm, type TipoProduto } from './api'

const VAZIO: ProdutoForm = {
  descricao: '', tipo: 'INDEFINIDO', capacidade: null, produtoclasse_id: null, unidademedida_id: null, vasilhameretornavel: false,
  produtoretornavel_id: null, ativo: true, enviaappnf: false, diasgiro: null, observacao: '',
  precovenda: '', precovendaminimo: '', customedio: '', custofrete: '', precogasdopovo: '',
  pesoliquido: '', pesobruto: '',
  nfepermite: false, sped: false, nfetipoitem: null, nfgrupofiscal_id: null, nfipi_id: null,
  nfealiqipi: '', nfebcipi: '', nfecodenquadramentoipi: null, nfeextipi: '', nfedescricaofiscal: '',
  nfenatrec: '', ean: '', eantrib: '', ncm: '', nfcest: '', especie: '', marca: '',
  nfecodgen: null, nfecodlst: null,
  tipo_glp: null, ressarcimentoproduto_id: null, nfecprodanp: '', nfedescanp: '',
  nfeqbcprod: '', nfevaliqprod: '', nfevcide: '', pgni: '', pgnn: '', pglp: '',
  origens: [],
}

/** Valor de input controlado: nunca passa null/undefined para o <input> (evita warning). */
function val(v: string | null | undefined) { return v ?? '' }

export function ProdutoFormPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const editId = id && id !== 'novo' ? Number(id) : null
  const { data: existente } = useProduto(editId)
  const salvar = useSalvarProduto()

  const [aba, setAba] = useState('dados')
  const [erroRegra, setErroRegra] = useState<string | null>(null)
  const [labels, setLabels] = useState<Record<string, string | null>>({})
  const [ufLabels, setUfLabels] = useState<Record<number, string | null>>({})

  const BOOL_KEYS: (keyof ProdutoForm)[] = ['vasilhameretornavel', 'ativo', 'enviaappnf', 'nfepermite']
  const { form, campo, erros, submit } = useResourceForm<ProdutoForm>({
    vazio: VAZIO, existente: existente as Partial<ProdutoForm> | undefined,
    hidratar: (ex, vazio) => {
      const f: ProdutoForm = { ...vazio }
      ;(Object.keys(vazio) as (keyof ProdutoForm)[]).forEach((k) => {
        const v = (ex as any)[k]
        if (v !== undefined && v !== null) {
          ;(f as any)[k] = BOOL_KEYS.includes(k) ? Number(v) === 1 : v
        }
      })
      const e = ex as any
      f.pgni = e.pGNi ?? e.pgni ?? ''
      f.pgnn = e.pGNn ?? e.pgnn ?? ''
      f.pglp = e.pGLP ?? e.pglp ?? ''
      f.origens = (e.origens ?? []).map((o: any) => ({
        id: o.id, indimport: Number(o.indimport), cuforig: Number(o.cuforig), porig: Number(o.porig), uf: o.uf,
      }))
      return f
    },
  })

  useEffect(() => {
    if (existente) {
      const origens = form.origens ?? []
      setLabels({
        classe: existente.classe_label ?? null,
        unidade: existente.unidade_label ?? null,
        grupofiscal: existente.grupofiscal_label ?? null,
        vasilhame: existente.vasilhame_label ?? null,
      })
      setUfLabels(Object.fromEntries(origens.map((o, i) => [i, o.uf ? `${o.uf}` : null])))
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [existente])

  async function onSubmit() {
    setErroRegra(null)
    try {
      const salvo = await submit((data) => salvar.mutateAsync({ id: editId, data }))
      toast.success(editId ? 'Produto atualizado.' : 'Produto cadastrado.')
      navigate(`/produtos/${salvo.id}`)
    } catch (e: any) {
      const status = e?.response?.status
      if (status === 422 && e.response.data?.errors) {
        setAba('dados')
        toast.error('Verifique os campos destacados.')
      } else if (status === 422 && e.response.data?.message) {
        setErroRegra(e.response.data.message)
        toast.error(e.response.data.message)
      } else {
        toast.error('Erro ao salvar o produto.')
      }
    }
  }

  return (
    <div>
      <PageHeader
        breadcrumb={<button onClick={() => navigate('/produtos')} className="hover:text-foreground">Produtos</button>}
        title={editId ? (form.descricao || 'Produto') : 'Novo produto'}
        subtitle={editId ? `#${editId}` : 'Preencha os dados do novo produto'}
        action={
          <>
            <Button variant="outline" onClick={() => navigate('/produtos')}><ArrowLeft size={16} /> Voltar</Button>
            <Button loading={salvar.isPending} onClick={onSubmit}><Save size={16} /> Salvar</Button>
          </>
        }
      />

      {erroRegra && (
        <div className="mb-4 flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
          <AlertCircle size={18} className="shrink-0 mt-0.5" /> <span>{erroRegra}</span>
        </div>
      )}

      <Tabs value={aba} onValueChange={setAba}>
        <TabsList className="overflow-x-auto">
          <TabsTrigger value="dados">Dados</TabsTrigger>
          <TabsTrigger value="precos">Preços</TabsTrigger>
          <TabsTrigger value="fiscal">Fiscal / NF-e</TabsTrigger>
          <TabsTrigger value="glp">GLP</TabsTrigger>
          <TabsTrigger value="origens">Origens</TabsTrigger>
          {editId && <TabsTrigger value="estoque">Estoque & Giro</TabsTrigger>}
        </TabsList>

        <TabsContent value="dados">
          <Card><CardContent className="pt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <Field label="Descrição" required error={erros.descricao} className="md:col-span-2">
              <Input value={form.descricao} maxLength={50} error={!!erros.descricao} onChange={(e) => campo('descricao', e.target.value)} />
            </Field>
            <Field label="Classe" required error={erros.produtoclasse_id}>
              <AsyncSelect endpoint="/lookups/produto-classes" value={form.produtoclasse_id} valueLabel={labels.classe} error={!!erros.produtoclasse_id}
                onChange={(id, opt) => { campo('produtoclasse_id', id); setLabels((l) => ({ ...l, classe: opt?.label ?? null })) }} />
            </Field>
            <Field label="Unidade de medida" required error={erros.unidademedida_id}>
              <AsyncSelect endpoint="/lookups/unidades" value={form.unidademedida_id} valueLabel={labels.unidade} error={!!erros.unidademedida_id}
                onChange={(id, opt) => { campo('unidademedida_id', id); setLabels((l) => ({ ...l, unidade: opt?.label ?? null })) }} />
            </Field>
            <Field label="Espécie"><Input value={val(form.especie)} maxLength={60} onChange={(e) => campo('especie', e.target.value)} /></Field>
            <Field label="Marca"><Input value={val(form.marca)} maxLength={60} onChange={(e) => campo('marca', e.target.value)} /></Field>
            {/* F3-02: o que o produto É, para a vigilância de comodato. Antes
                isso era lido da descrição, e um catálogo que dissesse
                "Cilindro 13kg" sumia da vigilância inteira. */}
            <Field label="Tipo" hint="Recipiente é o casco emprestado; conteúdo é o gás vendido dentro dele. Usado pela vigilância de comodato.">
              <Select value={form.tipo ?? 'INDEFINIDO'} onValueChange={(v) => campo('tipo', v as TipoProduto)}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="INDEFINIDO">Não classificado</SelectItem>
                  <SelectItem value="RECIPIENTE">Recipiente (casco/vasilhame)</SelectItem>
                  <SelectItem value="CONTEUDO">Conteúdo (gás)</SelectItem>
                  <SelectItem value="MERCADORIA">Mercadoria comum</SelectItem>
                </SelectContent>
              </Select>
            </Field>
            {/* Rótulo livre: cada revenda tem a sua grade. Uma lista fixa
                aqui gravaria a grade brasileira de GLP na interface. */}
            <Field label="Capacidade" hint="Rótulo da grade (ex.: P13). O casco só pareia com o gás de mesma capacidade.">
              <Input value={val(form.capacidade)} maxLength={20} onChange={(e) => campo('capacidade', e.target.value)} placeholder="P13" />
            </Field>
            <Field label="Dias de giro" hint="Usado na curva de giro do estoque">
              <Input type="number" value={form.diasgiro ?? ''} onChange={(e) => campo('diasgiro', e.target.value ? Number(e.target.value) : null)} />
            </Field>
            <Field label="Observação" className="md:col-span-2"><Textarea value={val(form.observacao)} maxLength={500} onChange={(e) => campo('observacao', e.target.value)} /></Field>
            <div className="md:col-span-2 grid grid-cols-2 sm:grid-cols-3 gap-2 border-t border-border pt-3">
              <CheckboxField label="Ativo" checked={!!form.ativo} onChange={(b) => campo('ativo', b)} />
              <CheckboxField label="Envia no app (NF)" checked={!!form.enviaappnf} onChange={(b) => campo('enviaappnf', b)} />
              <CheckboxField label="Vasilhame retornável" checked={!!form.vasilhameretornavel} onChange={(b) => campo('vasilhameretornavel', b)} />
            </div>
            {form.vasilhameretornavel && (
              <Field label="Produto vasilhame" required error={erros.produtoretornavel_id}>
                <AsyncSelect endpoint="/lookups/produtos-vasilhame" value={form.produtoretornavel_id ?? null} valueLabel={labels.vasilhame} error={!!erros.produtoretornavel_id}
                  onChange={(id, opt) => { campo('produtoretornavel_id', id); setLabels((l) => ({ ...l, vasilhame: opt?.label ?? null })) }} />
              </Field>
            )}
          </CardContent></Card>
        </TabsContent>

        <TabsContent value="precos">
          <Card><CardContent className="pt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <Field label="Preço de venda"><Input type="number" step="0.0001" value={val(form.precovenda as string)} onChange={(e) => campo('precovenda', e.target.value)} /></Field>
            <Field label="Preço mínimo"><Input type="number" step="0.0001" value={val(form.precovendaminimo as string)} onChange={(e) => campo('precovendaminimo', e.target.value)} /></Field>
            <Field label="Custo médio"><Input type="number" step="0.0001" value={val(form.customedio as string)} onChange={(e) => campo('customedio', e.target.value)} /></Field>
            <Field label="Custo de frete"><Input type="number" step="0.0001" value={val(form.custofrete as string)} onChange={(e) => campo('custofrete', e.target.value)} /></Field>
            <Field label="Preço Gás do Povo"><Input type="number" step="0.0001" value={val(form.precogasdopovo as string)} onChange={(e) => campo('precogasdopovo', e.target.value)} /></Field>
            <div className="md:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4 border-t border-border pt-4">
              <Field label="Peso líquido (Kg)"><Input type="number" step="0.0001" value={val(form.pesoliquido as string)} onChange={(e) => campo('pesoliquido', e.target.value)} /></Field>
              <Field label="Peso bruto (Kg)"><Input type="number" step="0.0001" value={val(form.pesobruto as string)} onChange={(e) => campo('pesobruto', e.target.value)} /></Field>
            </div>
          </CardContent></Card>
        </TabsContent>

        <TabsContent value="fiscal">
          <Card><CardContent className="pt-6 space-y-4">
            <div className="flex flex-wrap gap-6 border-b border-border pb-3">
              <CheckboxField label="Permite NF-e" checked={!!form.nfepermite} onChange={(b) => campo('nfepermite', b)} />
              <CheckboxField label="SPED (informar tipo de item)" checked={!!form.sped} onChange={(b) => campo('sped', b)} />
            </div>
            {!form.nfepermite ? (
              <p className="text-sm text-muted-foreground">Marque “Permite NF-e” para preencher os dados fiscais.</p>
            ) : (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Field label="Grupo fiscal" required error={erros.nfgrupofiscal_id}>
                  <AsyncSelect endpoint="/lookups/nf-grupos-fiscais" value={form.nfgrupofiscal_id ?? null} valueLabel={labels.grupofiscal} error={!!erros.nfgrupofiscal_id}
                    onChange={(id, opt) => { campo('nfgrupofiscal_id', id); setLabels((l) => ({ ...l, grupofiscal: opt?.label ?? null })) }} />
                </Field>
                <Field label="Descrição fiscal" required error={erros.nfedescricaofiscal}><Input value={val(form.nfedescricaofiscal)} maxLength={50} error={!!erros.nfedescricaofiscal} onChange={(e) => campo('nfedescricaofiscal', e.target.value)} /></Field>
                {form.sped && <Field label="Tipo de item (SPED)" required error={erros.nfetipoitem}><Input type="number" value={form.nfetipoitem ?? ''} error={!!erros.nfetipoitem} onChange={(e) => campo('nfetipoitem', e.target.value ? Number(e.target.value) : null)} /></Field>}
                <Field label="NCM"><Input value={val(form.ncm)} maxLength={8} onChange={(e) => campo('ncm', e.target.value)} /></Field>
                <Field label="CEST"><Input value={val(form.nfcest)} maxLength={7} onChange={(e) => campo('nfcest', e.target.value)} /></Field>
                <Field label="EAN"><Input value={val(form.ean)} maxLength={14} onChange={(e) => campo('ean', e.target.value)} /></Field>
                <Field label="EAN tributável"><Input value={val(form.eantrib)} maxLength={14} onChange={(e) => campo('eantrib', e.target.value)} /></Field>
                <Field label="Alíquota IPI"><Input type="number" step="0.0001" value={val(form.nfealiqipi as string)} onChange={(e) => campo('nfealiqipi', e.target.value)} /></Field>
                <Field label="BC IPI"><Input type="number" step="0.0001" value={val(form.nfebcipi as string)} onChange={(e) => campo('nfebcipi', e.target.value)} /></Field>
                <Field label="Cód. enquadramento IPI"><Input type="number" value={form.nfecodenquadramentoipi ?? ''} onChange={(e) => campo('nfecodenquadramentoipi', e.target.value ? Number(e.target.value) : null)} /></Field>
              </div>
            )}
          </CardContent></Card>
        </TabsContent>

        <TabsContent value="glp">
          <Card><CardContent className="pt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <Field label="Tipo de GLP" className="md:col-span-3">
              <AsyncSelect endpoint="/lookups/tipo-glp" value={form.tipo_glp ?? null} valueLabel={form.tipo_glp ? `GLP (${form.tipo_glp})` : null} onChange={(id) => campo('tipo_glp', id)} />
            </Field>
            <Field label="% GNi"><Input type="number" step="0.0001" value={val(form.pgni as string)} onChange={(e) => campo('pgni', e.target.value)} /></Field>
            <Field label="% GNn"><Input type="number" step="0.0001" value={val(form.pgnn as string)} onChange={(e) => campo('pgnn', e.target.value)} /></Field>
            <Field label="% GLP" hint="A soma %GNi + %GNn + %GLP deve ser 100 ou 0"><Input type="number" step="0.0001" value={val(form.pglp as string)} onChange={(e) => campo('pglp', e.target.value)} /></Field>
            <div className="md:col-span-3 grid grid-cols-1 md:grid-cols-2 gap-4 border-t border-border pt-4">
              <Field label="Código ANP" error={erros.nfecprodanp}><Input value={val(form.nfecprodanp)} maxLength={9} error={!!erros.nfecprodanp} onChange={(e) => campo('nfecprodanp', e.target.value)} /></Field>
              <Field label="Descrição ANP" error={erros.nfedescanp}><Input value={val(form.nfedescanp)} error={!!erros.nfedescanp} onChange={(e) => campo('nfedescanp', e.target.value)} /></Field>
              <Field label="BC da CIDE" error={erros.nfeqbcprod}><Input type="number" step="0.0001" value={val(form.nfeqbcprod as string)} error={!!erros.nfeqbcprod} onChange={(e) => campo('nfeqbcprod', e.target.value)} /></Field>
              <Field label="Valor alíq. CIDE" error={erros.nfevaliqprod}><Input type="number" step="0.0001" value={val(form.nfevaliqprod as string)} error={!!erros.nfevaliqprod} onChange={(e) => campo('nfevaliqprod', e.target.value)} /></Field>
              <Field label="Valor CIDE" error={erros.nfevcide}><Input type="number" step="0.0001" value={val(form.nfevcide as string)} error={!!erros.nfevcide} onChange={(e) => campo('nfevcide', e.target.value)} /></Field>
            </div>
          </CardContent></Card>
        </TabsContent>

        <TabsContent value="origens">
          <Card><CardContent className="pt-6">
            <OrigensTab origens={form.origens ?? []} onChange={(o) => campo('origens', o)} ufLabels={ufLabels} setUfLabel={(idx, label) => setUfLabels((l) => ({ ...l, [idx]: label }))} />
          </CardContent></Card>
        </TabsContent>

        {editId && (
          <TabsContent value="estoque">
            <EstoqueGiroTab produtoId={editId} />
          </TabsContent>
        )}
      </Tabs>
    </div>
  )
}

/** VISÃO NOVA (IMPL_PRODUTO §6b): saldo por setor + giro na própria ficha. */
function EstoqueGiroTab({ produtoId }: { produtoId: number }) {
  const { data, isLoading } = useEstoqueProduto(produtoId)

  if (isLoading) {
    return <Card><CardContent className="pt-6 space-y-3">{Array.from({ length: 3 }).map((_, i) => <Skeleton key={i} className="h-10 w-full" />)}</CardContent></Card>
  }

  return (
    <div className="grid gap-4 md:grid-cols-3">
      <Card><CardContent className="pt-6">
        <p className="text-sm text-muted-foreground">Saldo total</p>
        <p className="mt-1 text-3xl font-bold tabular-nums">{qtd(data?.total ?? 0)}</p>
      </CardContent></Card>
      <Card><CardContent className="pt-6">
        <p className="text-sm text-muted-foreground">Dias de giro</p>
        <p className="mt-1 text-3xl font-bold tabular-nums">{data?.diasgiro ?? 0}</p>
      </CardContent></Card>
      <Card><CardContent className="pt-6">
        <p className="text-sm text-muted-foreground">Setores com saldo</p>
        <p className="mt-1 text-3xl font-bold tabular-nums">{data?.setores.filter((s) => s.quantidade !== 0).length ?? 0}</p>
      </CardContent></Card>

      <Card className="md:col-span-3"><CardContent className="pt-6">
        <p className="mb-3 text-sm font-medium">Saldo por setor</p>
        {data && data.setores.length > 0 ? (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="text-muted-foreground border-b border-border">
                <tr><th className="py-2 text-left font-medium">Setor</th><th className="py-2 text-right font-medium">Quantidade</th><th className="py-2 text-right font-medium">Mín.</th><th className="py-2 text-right font-medium">Máx.</th></tr>
              </thead>
              <tbody>
                {data.setores.map((s, i) => (
                  <tr key={i} className="border-b border-border/60">
                    <td className="py-2">{s.setor}</td>
                    <td className="py-2 text-right tabular-nums font-medium">{qtd(s.quantidade)}</td>
                    <td className="py-2 text-right tabular-nums text-muted-foreground">{qtd(s.minima)}</td>
                    <td className="py-2 text-right tabular-nums text-muted-foreground">{qtd(s.maxima)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <EmptyState icon={<Warehouse />} title="Sem saldo em estoque" description="Este produto ainda não tem saldo em nenhum setor." />
        )}
      </CardContent></Card>
    </div>
  )
}
