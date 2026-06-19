import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { ArrowLeft, Save } from 'lucide-react'
import { Button, Card, Input, PageHeader, Tabs } from '@/components/ui'
import { AsyncSelect } from '@/components/AsyncSelect'
import { TelefonesTab } from './TelefonesTab'
import { HistoricoTab } from './HistoricoTab'
import { InteracoesTab } from './InteracoesTab'
import { ConvenioTab } from './ConvenioTab'
import { PrecosTab } from './PrecosTab'
import { useCliente, useSalvarCliente, type ClienteForm } from './api'

const VAZIO: ClienteForm = {
  nome: '', fantasia: '', tipopessoa_id: null, segmento_id: null, sexo: '',
  datanascimento: '', observacoes: '',
  cpf: '', rg: '', cnpj: '', inscricao_estadual: '', indicador_ie: null, suframa: '', consisa_id: '',
  cliente: true, fornecedor: false, transportador: false, simples: false, ativo: true, nfemite: false, gasdopovo: false,
  numero: '', cidade_id: null, bairro_id: null, rua_id: null, uf: '', cep: '', complemento: '', ponto_referencia: '', email: '',
}

const INDICADOR_IE = [
  { v: 1, l: 'Contribuinte ICMS' },
  { v: 2, l: 'Contribuinte Isento' },
  { v: 9, l: 'Não Contribuinte' },
]

function Check({ label, checked, onChange }: { label: string; checked: boolean; onChange: (b: boolean) => void }) {
  return (
    <label className="flex items-center gap-2 text-sm py-2 cursor-pointer">
      <input type="checkbox" checked={checked} onChange={(e) => onChange(e.target.checked)} className="rounded" />
      {label}
    </label>
  )
}

export function ClienteFormPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const editId = id && id !== 'novo' ? Number(id) : null
  const { data: existente } = useCliente(editId)
  const salvar = useSalvarCliente()

  const [aba, setAba] = useState('dados')
  const [form, setForm] = useState<ClienteForm>(VAZIO)
  const [erros, setErros] = useState<Record<string, string>>({})
  const [labels, setLabels] = useState<Record<string, string | null>>({})

  useEffect(() => {
    if (existente) {
      const f: ClienteForm = { ...VAZIO }
      ;(Object.keys(VAZIO) as (keyof ClienteForm)[]).forEach((k) => {
        const v = existente[k]
        if (v !== undefined && v !== null) {
          // flags smallint(0/1) → boolean
          if (['cliente', 'fornecedor', 'transportador', 'simples', 'ativo', 'nfemite', 'gasdopovo'].includes(k as string)) {
            ;(f as any)[k] = Number(v) === 1
          } else (f as any)[k] = v
        }
      })
      setForm(f)
      setLabels({
        cidade: existente.cidade_label ?? null,
        bairro: existente.bairro_label ?? null,
        rua: existente.rua_label ?? null,
        segmento: existente.segmento_label ?? null,
        tipopessoa: existente.tipopessoa_label ?? null,
      })
    }
  }, [existente])

  function campo<K extends keyof ClienteForm>(k: K, v: ClienteForm[K]) {
    setForm((prev) => ({ ...prev, [k]: v }))
  }

  // Pessoa Jurídica quando o tipo selecionado indica J (heurística pelo label).
  const ehJuridica = (labels.tipopessoa ?? '').toUpperCase().includes('JUR')

  async function onSubmit() {
    setErros({})
    try {
      const salvo = await salvar.mutateAsync({ id: editId, data: form })
      navigate(`/clientes/${salvo.id}`)
    } catch (e: any) {
      if (e?.response?.status === 422) {
        const ve = e.response.data.errors as Record<string, string[]>
        setErros(Object.fromEntries(Object.entries(ve).map(([k, v]) => [k, v[0]])))
        setAba('dados')
      }
    }
  }

  const tabs = [
    { id: 'dados', label: 'Dados Gerais' },
    { id: 'endereco', label: 'Endereço' },
    ...(editId ? [
      { id: 'contatos', label: 'Contatos' },
      { id: 'historico', label: 'Histórico' },
      { id: 'interacoes', label: 'Interações' },
      { id: 'convenio', label: 'Convênio' },
      { id: 'precos', label: 'Preços' },
    ] : []),
  ]

  return (
    <div>
      <PageHeader
        title={editId ? (form.nome || 'Cliente') : 'Novo cliente'}
        action={
          <div className="flex gap-2">
            <Button variant="ghost" onClick={() => navigate('/clientes')}><ArrowLeft size={16} /> Voltar</Button>
            <Button onClick={onSubmit} disabled={salvar.isPending}>
              <Save size={16} /> {salvar.isPending ? 'Salvando…' : 'Salvar'}
            </Button>
          </div>
        }
      />

      <Card>
        <div className="px-4 pt-2 overflow-x-auto">
          <Tabs active={aba} onChange={setAba} tabs={tabs} />
        </div>

        <div className="p-6">
          {aba === 'dados' && (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <AsyncSelect
                label="Tipo de Pessoa" endpoint="/lookups/tipo-pessoa"
                value={form.tipopessoa_id ?? null} valueLabel={labels.tipopessoa}
                onChange={(id, opt) => { campo('tipopessoa_id', id); setLabels((l) => ({ ...l, tipopessoa: opt?.label ?? null })) }}
              />
              <AsyncSelect
                label="Segmento" endpoint="/lookups/segmentos"
                value={form.segmento_id ?? null} valueLabel={labels.segmento}
                onChange={(id, opt) => { campo('segmento_id', id); setLabels((l) => ({ ...l, segmento: opt?.label ?? null })) }}
              />
              <Input label={ehJuridica ? 'Razão Social *' : 'Nome *'} value={form.nome} onChange={(e) => campo('nome', e.target.value)} error={erros.nome} />
              {ehJuridica && <Input label="Fantasia / Apelido" value={form.fantasia ?? ''} onChange={(e) => campo('fantasia', e.target.value)} />}
              {!ehJuridica && (
                <>
                  <Input label="Nascimento" type="date" value={form.datanascimento ?? ''} onChange={(e) => campo('datanascimento', e.target.value)} />
                  <Input label="Sexo (F/M)" maxLength={1} value={form.sexo ?? ''} onChange={(e) => campo('sexo', e.target.value.toUpperCase())} />
                </>
              )}
              <Input label="E-mail" type="email" value={form.email ?? ''} onChange={(e) => campo('email', e.target.value)} error={erros.email} />
              <Input label="Observações" value={form.observacoes ?? ''} onChange={(e) => campo('observacoes', e.target.value)} className="md:col-span-2" />
              <div className="md:col-span-2 grid grid-cols-2 sm:grid-cols-4 gap-2 border-t border-slate-100 dark:border-slate-800 pt-2">
                <Check label="Cliente" checked={!!form.cliente} onChange={(b) => campo('cliente', b)} />
                <Check label="Fornecedor" checked={!!form.fornecedor} onChange={(b) => campo('fornecedor', b)} />
                <Check label="Transportador" checked={!!form.transportador} onChange={(b) => campo('transportador', b)} />
                <Check label="Ativo" checked={!!form.ativo} onChange={(b) => campo('ativo', b)} />
                <Check label="Gás do Povo" checked={!!form.gasdopovo} onChange={(b) => campo('gasdopovo', b)} />
              </div>
            </div>
          )}

          {aba === 'endereco' && (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <AsyncSelect
                label="Cidade *" endpoint="/lookups/cidades" value={form.cidade_id} valueLabel={labels.cidade} error={erros.cidade_id}
                onChange={(id, opt) => {
                  campo('cidade_id', id); setLabels((l) => ({ ...l, cidade: opt?.label ?? null, bairro: null }))
                  campo('bairro_id', null); if (opt?.uf) campo('uf', String(opt.uf))
                }}
              />
              <AsyncSelect
                label="Bairro" endpoint="/lookups/bairros" params={{ cidade_id: form.cidade_id }}
                value={form.bairro_id ?? null} valueLabel={labels.bairro} disabled={!form.cidade_id}
                placeholder={form.cidade_id ? 'Selecione…' : 'Escolha a cidade primeiro'}
                onChange={(id, opt) => { campo('bairro_id', id); setLabels((l) => ({ ...l, bairro: opt?.label ?? null })) }}
              />
              <Input label="Número *" value={form.numero} onChange={(e) => campo('numero', e.target.value)} error={erros.numero} />
              <Input label="CEP" value={form.cep ?? ''} onChange={(e) => campo('cep', e.target.value)} />
              <Input label="UF" maxLength={2} value={form.uf ?? ''} onChange={(e) => campo('uf', e.target.value.toUpperCase())} />
              <Input label="Complemento" value={form.complemento ?? ''} onChange={(e) => campo('complemento', e.target.value)} />
              <Input label="Ponto de referência" value={form.ponto_referencia ?? ''} onChange={(e) => campo('ponto_referencia', e.target.value)} className="md:col-span-2" />
            </div>
          )}

          {/* Documentos/Fiscal: seção dentro de Dados Gerais (varia PF×PJ). */}
          {aba === 'dados' && (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 border-t border-slate-100 dark:border-slate-800 mt-4 pt-4">
              <p className="md:col-span-2 text-sm font-semibold text-slate-500">Documentos / Fiscal</p>
              {!ehJuridica && <Input label="CPF" value={form.cpf ?? ''} onChange={(e) => campo('cpf', e.target.value)} error={erros.cpf} />}
              {!ehJuridica && <Input label="RG" value={form.rg ?? ''} onChange={(e) => campo('rg', e.target.value)} />}
              {ehJuridica && <Input label="CNPJ" value={form.cnpj ?? ''} onChange={(e) => campo('cnpj', e.target.value)} error={erros.cnpj} />}
              <Input label="Inscrição Estadual" value={form.inscricao_estadual ?? ''} onChange={(e) => campo('inscricao_estadual', e.target.value)} />
              {ehJuridica && <Input label="Suframa" value={form.suframa ?? ''} onChange={(e) => campo('suframa', e.target.value)} />}
              <Input label="Cód. Contábil" value={form.consisa_id ?? ''} onChange={(e) => campo('consisa_id', e.target.value)} />
              <div className="space-y-1">
                <label className="block text-sm font-medium">Indicador I.E.</label>
                <select
                  value={form.indicador_ie ?? ''} onChange={(e) => campo('indicador_ie', e.target.value ? Number(e.target.value) : null)}
                  className="w-full rounded-md border border-slate-300 dark:border-slate-700 bg-transparent px-3 py-2 outline-none focus:ring-2 focus:ring-marca-500"
                >
                  <option value="">Selecione</option>
                  {INDICADOR_IE.map((o) => <option key={o.v} value={o.v}>{o.l}</option>)}
                </select>
              </div>
              <div className="flex items-end gap-4">
                <Check label="Simples" checked={!!form.simples} onChange={(b) => campo('simples', b)} />
                <Check label="Emite NF-e" checked={!!form.nfemite} onChange={(b) => campo('nfemite', b)} />
              </div>
            </div>
          )}

          {aba === 'contatos' && editId && <TelefonesTab clienteId={editId} />}
          {aba === 'historico' && editId && <HistoricoTab clienteId={editId} />}
          {aba === 'interacoes' && editId && <InteracoesTab clienteId={editId} />}
          {aba === 'convenio' && editId && <ConvenioTab clienteId={editId} />}
          {aba === 'precos' && editId && <PrecosTab clienteId={editId} />}
        </div>
      </Card>
    </div>
  )
}
