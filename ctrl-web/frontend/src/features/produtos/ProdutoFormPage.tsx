import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { ArrowLeft, Save } from 'lucide-react'
import { Button, Card, Input, PageHeader, Tabs } from '@/components/ui'
import { AsyncSelect } from '@/components/AsyncSelect'
import { OrigensTab } from './OrigensTab'
import { useProduto, useSalvarProduto, type ProdutoForm, type OrigemCombustivel } from './api'

const VAZIO: ProdutoForm = {
  descricao: '', produtoclasse_id: null, unidademedida_id: null, vasilhameretornavel: false,
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

function Check({ label, checked, onChange }: { label: string; checked: boolean; onChange: (b: boolean) => void }) {
  return (
    <label className="flex items-center gap-2 text-sm py-2 cursor-pointer">
      <input type="checkbox" checked={checked} onChange={(e) => onChange(e.target.checked)} className="rounded" />
      {label}
    </label>
  )
}

export function ProdutoFormPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const editId = id && id !== 'novo' ? Number(id) : null
  const { data: existente } = useProduto(editId)
  const salvar = useSalvarProduto()

  const [aba, setAba] = useState('dados')
  const [form, setForm] = useState<ProdutoForm>(VAZIO)
  const [erros, setErros] = useState<Record<string, string>>({})
  const [erroRegra, setErroRegra] = useState<string | null>(null)
  const [labels, setLabels] = useState<Record<string, string | null>>({})
  const [ufLabels, setUfLabels] = useState<Record<number, string | null>>({})

  useEffect(() => {
    if (existente) {
      const f: ProdutoForm = { ...VAZIO }
      ;(Object.keys(VAZIO) as (keyof ProdutoForm)[]).forEach((k) => {
        const v = (existente as any)[k]
        if (v !== undefined && v !== null) {
          if (['vasilhameretornavel', 'ativo', 'enviaappnf', 'nfepermite'].includes(k as string)) {
            ;(f as any)[k] = Number(v) === 1
          } else (f as any)[k] = v
        }
      })
      // GLP vem em colunas camelCase (pGNi/pGNn/pGLP) → mapeia p/ as chaves do form
      f.pgni = existente.pGNi ?? existente.pgni ?? ''
      f.pgnn = existente.pGNn ?? existente.pgnn ?? ''
      f.pglp = existente.pGLP ?? existente.pglp ?? ''
      const origens: OrigemCombustivel[] = (existente.origens ?? []).map((o: any) => ({
        id: o.id, indimport: Number(o.indimport), cuforig: Number(o.cuforig), porig: Number(o.porig), uf: o.uf,
      }))
      f.origens = origens
      setForm(f)
      setLabels({
        classe: existente.classe_label ?? null,
        unidade: existente.unidade_label ?? null,
        grupofiscal: existente.grupofiscal_label ?? null,
        vasilhame: existente.vasilhame_label ?? null,
      })
      setUfLabels(Object.fromEntries(origens.map((o, i) => [i, o.uf ? `${o.uf}` : null])))
    }
  }, [existente])

  function campo<K extends keyof ProdutoForm>(k: K, v: ProdutoForm[K]) {
    setForm((prev) => ({ ...prev, [k]: v }))
  }

  async function onSubmit() {
    setErros({}); setErroRegra(null)
    try {
      const salvo = await salvar.mutateAsync({ id: editId, data: form })
      navigate(`/produtos/${salvo.id}`)
    } catch (e: any) {
      const status = e?.response?.status
      if (status === 422 && e.response.data?.errors) {
        const ve = e.response.data.errors as Record<string, string[]>
        setErros(Object.fromEntries(Object.entries(ve).map(([k, v]) => [k, v[0]])))
        setAba('dados')
      } else if (status === 422 && e.response.data?.message) {
        // Erro de regra de negócio (GLP/origens/inativar)
        setErroRegra(e.response.data.message)
      }
    }
  }

  const tabs = [
    { id: 'dados', label: 'Dados' },
    { id: 'precos', label: 'Preços' },
    { id: 'fiscal', label: 'Fiscal / NF-e' },
    { id: 'glp', label: 'GLP' },
    { id: 'origens', label: 'Origens' },
  ]

  return (
    <div>
      <PageHeader
        title={editId ? (form.descricao || 'Produto') : 'Novo produto'}
        action={
          <div className="flex gap-2">
            <Button variant="ghost" onClick={() => navigate('/produtos')}><ArrowLeft size={16} /> Voltar</Button>
            <Button onClick={onSubmit} disabled={salvar.isPending}>
              <Save size={16} /> {salvar.isPending ? 'Salvando…' : 'Salvar'}
            </Button>
          </div>
        }
      />

      {erroRegra && (
        <div className="mb-4 rounded-md bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-300">
          {erroRegra}
        </div>
      )}

      <Card>
        <div className="px-4 pt-2 overflow-x-auto">
          <Tabs active={aba} onChange={setAba} tabs={tabs} />
        </div>

        <div className="p-6">
          {aba === 'dados' && (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Input label="Descrição *" value={form.descricao} maxLength={50} onChange={(e) => campo('descricao', e.target.value)} error={erros.descricao} className="md:col-span-2" />
              <AsyncSelect
                label="Classe *" endpoint="/lookups/produto-classes" value={form.produtoclasse_id} valueLabel={labels.classe} error={erros.produtoclasse_id}
                onChange={(id, opt) => { campo('produtoclasse_id', id); setLabels((l) => ({ ...l, classe: opt?.label ?? null })) }}
              />
              <AsyncSelect
                label="Unidade de medida *" endpoint="/lookups/unidades" value={form.unidademedida_id} valueLabel={labels.unidade} error={erros.unidademedida_id}
                onChange={(id, opt) => { campo('unidademedida_id', id); setLabels((l) => ({ ...l, unidade: opt?.label ?? null })) }}
              />
              <Input label="Espécie" value={form.especie ?? ''} maxLength={60} onChange={(e) => campo('especie', e.target.value)} />
              <Input label="Marca" value={form.marca ?? ''} maxLength={60} onChange={(e) => campo('marca', e.target.value)} />
              <Input label="Dias de giro" type="number" value={form.diasgiro ?? ''} onChange={(e) => campo('diasgiro', e.target.value ? Number(e.target.value) : null)} />
              <Input label="Observação" value={form.observacao ?? ''} maxLength={500} onChange={(e) => campo('observacao', e.target.value)} />
              <div className="md:col-span-2 grid grid-cols-2 sm:grid-cols-3 gap-2 border-t border-slate-100 dark:border-slate-800 pt-2">
                <Check label="Ativo" checked={!!form.ativo} onChange={(b) => campo('ativo', b)} />
                <Check label="Envia no app (NF)" checked={!!form.enviaappnf} onChange={(b) => campo('enviaappnf', b)} />
                <Check label="Vasilhame retornável" checked={!!form.vasilhameretornavel} onChange={(b) => campo('vasilhameretornavel', b)} />
              </div>
              {form.vasilhameretornavel && (
                <AsyncSelect
                  label="Produto vasilhame *" endpoint="/lookups/produtos-vasilhame" value={form.produtoretornavel_id ?? null} valueLabel={labels.vasilhame} error={erros.produtoretornavel_id}
                  onChange={(id, opt) => { campo('produtoretornavel_id', id); setLabels((l) => ({ ...l, vasilhame: opt?.label ?? null })) }}
                />
              )}
            </div>
          )}

          {aba === 'precos' && (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Input label="Preço de venda" type="number" step="0.0001" value={form.precovenda ?? ''} onChange={(e) => campo('precovenda', e.target.value)} />
              <Input label="Preço mínimo" type="number" step="0.0001" value={form.precovendaminimo ?? ''} onChange={(e) => campo('precovendaminimo', e.target.value)} />
              <Input label="Custo médio" type="number" step="0.0001" value={form.customedio ?? ''} onChange={(e) => campo('customedio', e.target.value)} />
              <Input label="Custo de frete" type="number" step="0.0001" value={form.custofrete ?? ''} onChange={(e) => campo('custofrete', e.target.value)} />
              <Input label="Preço Gás do Povo" type="number" step="0.0001" value={form.precogasdopovo ?? ''} onChange={(e) => campo('precogasdopovo', e.target.value)} />
              <div className="md:col-span-2 grid grid-cols-2 gap-4 border-t border-slate-100 dark:border-slate-800 pt-4">
                <Input label="Peso líquido (Kg)" type="number" step="0.0001" value={form.pesoliquido ?? ''} onChange={(e) => campo('pesoliquido', e.target.value)} />
                <Input label="Peso bruto (Kg)" type="number" step="0.0001" value={form.pesobruto ?? ''} onChange={(e) => campo('pesobruto', e.target.value)} />
              </div>
            </div>
          )}

          {aba === 'fiscal' && (
            <div className="space-y-4">
              <div className="flex flex-wrap gap-4 border-b border-slate-100 dark:border-slate-800 pb-2">
                <Check label="Permite NF-e" checked={!!form.nfepermite} onChange={(b) => campo('nfepermite', b)} />
                <Check label="SPED (informar tipo de item)" checked={!!form.sped} onChange={(b) => campo('sped', b)} />
              </div>
              {!form.nfepermite ? (
                <p className="text-sm text-slate-400">Marque “Permite NF-e” para preencher os dados fiscais.</p>
              ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <AsyncSelect
                    label="Grupo fiscal *" endpoint="/lookups/nf-grupos-fiscais" value={form.nfgrupofiscal_id ?? null} valueLabel={labels.grupofiscal} error={erros.nfgrupofiscal_id}
                    onChange={(id, opt) => { campo('nfgrupofiscal_id', id); setLabels((l) => ({ ...l, grupofiscal: opt?.label ?? null })) }}
                  />
                  <Input label="Descrição fiscal *" value={form.nfedescricaofiscal ?? ''} maxLength={50} onChange={(e) => campo('nfedescricaofiscal', e.target.value)} error={erros.nfedescricaofiscal} />
                  {form.sped && <Input label="Tipo de item (SPED) *" type="number" value={form.nfetipoitem ?? ''} onChange={(e) => campo('nfetipoitem', e.target.value ? Number(e.target.value) : null)} error={erros.nfetipoitem} />}
                  <Input label="NCM" value={form.ncm ?? ''} maxLength={8} onChange={(e) => campo('ncm', e.target.value)} />
                  <Input label="CEST" value={form.nfcest ?? ''} maxLength={7} onChange={(e) => campo('nfcest', e.target.value)} />
                  <Input label="EAN" value={form.ean ?? ''} maxLength={14} onChange={(e) => campo('ean', e.target.value)} />
                  <Input label="EAN tributável" value={form.eantrib ?? ''} maxLength={14} onChange={(e) => campo('eantrib', e.target.value)} />
                  <Input label="Alíquota IPI" type="number" step="0.0001" value={form.nfealiqipi ?? ''} onChange={(e) => campo('nfealiqipi', e.target.value)} />
                  <Input label="BC IPI" type="number" step="0.0001" value={form.nfebcipi ?? ''} onChange={(e) => campo('nfebcipi', e.target.value)} />
                  <Input label="Cód. enquadramento IPI" type="number" value={form.nfecodenquadramentoipi ?? ''} onChange={(e) => campo('nfecodenquadramentoipi', e.target.value ? Number(e.target.value) : null)} />
                </div>
              )}
            </div>
          )}

          {aba === 'glp' && (
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="md:col-span-3 space-y-1">
                <label className="block text-sm font-medium">Tipo de GLP</label>
                <AsyncSelect endpoint="/lookups/tipo-glp" value={form.tipo_glp ?? null} valueLabel={form.tipo_glp ? `GLP (${form.tipo_glp})` : null}
                  onChange={(id) => campo('tipo_glp', id)} />
              </div>
              <Input label="% GNi" type="number" step="0.0001" value={form.pgni ?? ''} onChange={(e) => campo('pgni', e.target.value)} />
              <Input label="% GNn" type="number" step="0.0001" value={form.pgnn ?? ''} onChange={(e) => campo('pgnn', e.target.value)} />
              <Input label="% GLP" type="number" step="0.0001" value={form.pglp ?? ''} onChange={(e) => campo('pglp', e.target.value)} />
              <p className="md:col-span-3 text-xs text-slate-400">A soma de %GNi + %GNn + %GLP deve ser 100 ou 0.</p>
              <div className="md:col-span-3 grid grid-cols-1 md:grid-cols-2 gap-4 border-t border-slate-100 dark:border-slate-800 pt-4">
                <Input label="Código ANP" value={form.nfecprodanp ?? ''} maxLength={9} onChange={(e) => campo('nfecprodanp', e.target.value)} error={erros.nfecprodanp} />
                <Input label="Descrição ANP" value={form.nfedescanp ?? ''} onChange={(e) => campo('nfedescanp', e.target.value)} error={erros.nfedescanp} />
                <Input label="BC da CIDE" type="number" step="0.0001" value={form.nfeqbcprod ?? ''} onChange={(e) => campo('nfeqbcprod', e.target.value)} error={erros.nfeqbcprod} />
                <Input label="Valor alíq. CIDE" type="number" step="0.0001" value={form.nfevaliqprod ?? ''} onChange={(e) => campo('nfevaliqprod', e.target.value)} error={erros.nfevaliqprod} />
                <Input label="Valor CIDE" type="number" step="0.0001" value={form.nfevcide ?? ''} onChange={(e) => campo('nfevcide', e.target.value)} error={erros.nfevcide} />
              </div>
            </div>
          )}

          {aba === 'origens' && (
            <OrigensTab
              origens={form.origens ?? []}
              onChange={(o) => campo('origens', o)}
              ufLabels={ufLabels}
              setUfLabel={(idx, label) => setUfLabels((l) => ({ ...l, [idx]: label }))}
            />
          )}
        </div>
      </Card>
    </div>
  )
}
