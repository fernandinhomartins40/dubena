import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { ArrowLeft, Save } from 'lucide-react'
import {
  Button, Card, CardContent, Field, Input, Textarea, CheckboxField, PageHeader, AsyncSelect,
  Tabs, TabsList, TabsTrigger, TabsContent,
  Select, SelectTrigger, SelectValue, SelectContent, SelectItem, toast,
} from '@/components/ui'
import { TelefonesTab } from './TelefonesTab'
import { HistoricoTab } from './HistoricoTab'
import { InteracoesTab } from './InteracoesTab'
import { ConvenioTab } from './ConvenioTab'
import { PrecosTab } from './PrecosTab'
import { useResourceForm } from '@/lib/useResourceForm'
import { useCliente, useSalvarCliente, type ClienteForm } from './api'

const VAZIO: ClienteForm = {
  nome: '', fantasia: '', tipopessoa_id: null, segmento_id: null, sexo: '',
  datanascimento: '', observacoes: '',
  cpf: '', rg: '', cnpj: '', inscricao_estadual: '', indicador_ie: null, suframa: '',
  cliente: true, fornecedor: false, transportador: false, simples: false, ativo: true, nfemite: false, gasdopovo: false,
  numero: '', cidade_id: null, bairro_id: null, rua_id: null, uf: '', cep: '', complemento: '', ponto_referencia: '', email: '',
}

const INDICADOR_IE = [
  { v: 1, l: 'Contribuinte ICMS' },
  { v: 2, l: 'Contribuinte Isento' },
  { v: 9, l: 'Não Contribuinte' },
]

export function ClienteFormPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const editId = id && id !== 'novo' ? Number(id) : null
  const { data: existente } = useCliente(editId)
  const salvar = useSalvarCliente()

  const [aba, setAba] = useState('dados')
  const { form, campo, erros, submit } = useResourceForm<ClienteForm>({
    vazio: VAZIO, existente,
    booleanKeys: ['cliente', 'fornecedor', 'transportador', 'simples', 'ativo', 'nfemite', 'gasdopovo'],
  })
  const [labels, setLabels] = useState<Record<string, string | null>>({})

  useEffect(() => {
    if (existente) {
      setLabels({
        cidade: existente.cidade_label ?? null, bairro: existente.bairro_label ?? null,
        rua: existente.rua_label ?? null, segmento: existente.segmento_label ?? null,
        tipopessoa: existente.tipopessoa_label ?? null,
      })
    }
  }, [existente])

  const ehJuridica = (labels.tipopessoa ?? '').toUpperCase().includes('JUR')

  async function onSubmit() {
    try {
      const salvo = await submit((data) => salvar.mutateAsync({ id: editId, data }))
      toast.success(editId ? 'Cliente atualizado.' : 'Cliente cadastrado.')
      navigate(`/clientes/${salvo.id}`)
    } catch (e: any) {
      if (e?.response?.status === 422) {
        setAba('dados')
        toast.error('Verifique os campos destacados.')
      } else toast.error('Erro ao salvar o cliente.')
    }
  }

  return (
    <div>
      <PageHeader
        breadcrumb={<button onClick={() => navigate('/clientes')} className="hover:text-foreground">Clientes</button>}
        title={editId ? (form.nome || 'Cliente') : 'Novo cliente'}
        subtitle={editId ? `#${editId}` : 'Preencha os dados do novo cliente'}
        action={
          <>
            <Button variant="outline" onClick={() => navigate('/clientes')}><ArrowLeft size={16} /> Voltar</Button>
            <Button loading={salvar.isPending} onClick={onSubmit}><Save size={16} /> Salvar</Button>
          </>
        }
      />

      <Tabs value={aba} onValueChange={setAba}>
        <TabsList className="overflow-x-auto">
          <TabsTrigger value="dados">Dados Gerais</TabsTrigger>
          <TabsTrigger value="endereco">Endereço</TabsTrigger>
          {editId && <TabsTrigger value="contatos">Contatos</TabsTrigger>}
          {editId && <TabsTrigger value="historico">Histórico</TabsTrigger>}
          {editId && <TabsTrigger value="interacoes">Interações</TabsTrigger>}
          {editId && <TabsTrigger value="convenio">Convênio</TabsTrigger>}
          {editId && <TabsTrigger value="precos">Preços</TabsTrigger>}
        </TabsList>

        <TabsContent value="dados">
          <Card><CardContent className="pt-6 space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Field label="Tipo de Pessoa">
                <AsyncSelect endpoint="/lookups/tipo-pessoa" value={form.tipopessoa_id ?? null} valueLabel={labels.tipopessoa}
                  onChange={(id, opt) => { campo('tipopessoa_id', id); setLabels((l) => ({ ...l, tipopessoa: opt?.label ?? null })) }} />
              </Field>
              <Field label="Segmento">
                <AsyncSelect endpoint="/lookups/segmentos" value={form.segmento_id ?? null} valueLabel={labels.segmento}
                  onChange={(id, opt) => { campo('segmento_id', id); setLabels((l) => ({ ...l, segmento: opt?.label ?? null })) }} />
              </Field>
              <Field label={ehJuridica ? 'Razão Social' : 'Nome'} required error={erros.nome}>
                <Input value={form.nome} error={!!erros.nome} onChange={(e) => campo('nome', e.target.value)} />
              </Field>
              {ehJuridica && <Field label="Fantasia / Apelido"><Input value={form.fantasia ?? ''} onChange={(e) => campo('fantasia', e.target.value)} /></Field>}
              {!ehJuridica && <Field label="Nascimento"><Input type="date" value={form.datanascimento ?? ''} onChange={(e) => campo('datanascimento', e.target.value)} /></Field>}
              {!ehJuridica && <Field label="Sexo (F/M)"><Input maxLength={1} value={form.sexo ?? ''} onChange={(e) => campo('sexo', e.target.value.toUpperCase())} /></Field>}
              <Field label="E-mail" error={erros.email}><Input type="email" value={form.email ?? ''} error={!!erros.email} onChange={(e) => campo('email', e.target.value)} /></Field>
              <Field label="Observações" className="md:col-span-2"><Textarea value={form.observacoes ?? ''} onChange={(e) => campo('observacoes', e.target.value)} /></Field>
            </div>

            <div className="grid grid-cols-2 sm:grid-cols-5 gap-2 border-t border-border pt-3">
              <CheckboxField label="Cliente" checked={!!form.cliente} onChange={(b) => campo('cliente', b)} />
              <CheckboxField label="Fornecedor" checked={!!form.fornecedor} onChange={(b) => campo('fornecedor', b)} />
              <CheckboxField label="Transportador" checked={!!form.transportador} onChange={(b) => campo('transportador', b)} />
              <CheckboxField label="Ativo" checked={!!form.ativo} onChange={(b) => campo('ativo', b)} />
              <CheckboxField label="Gás do Povo" checked={!!form.gasdopovo} onChange={(b) => campo('gasdopovo', b)} />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 border-t border-border pt-4">
              <p className="md:col-span-2 text-sm font-semibold text-muted-foreground">Documentos / Fiscal</p>
              {!ehJuridica && <Field label="CPF" error={erros.cpf}><Input value={form.cpf ?? ''} error={!!erros.cpf} onChange={(e) => campo('cpf', e.target.value)} /></Field>}
              {!ehJuridica && <Field label="RG"><Input value={form.rg ?? ''} onChange={(e) => campo('rg', e.target.value)} /></Field>}
              {ehJuridica && <Field label="CNPJ" error={erros.cnpj}><Input value={form.cnpj ?? ''} error={!!erros.cnpj} onChange={(e) => campo('cnpj', e.target.value)} /></Field>}
              <Field label="Inscrição Estadual"><Input value={form.inscricao_estadual ?? ''} onChange={(e) => campo('inscricao_estadual', e.target.value)} /></Field>
              {ehJuridica && <Field label="Suframa"><Input value={form.suframa ?? ''} onChange={(e) => campo('suframa', e.target.value)} /></Field>}
              <Field label="Indicador I.E.">
                <Select value={String(form.indicador_ie ?? '')} onValueChange={(v) => campo('indicador_ie', v ? Number(v) : null)}>
                  <SelectTrigger><SelectValue placeholder="Selecione" /></SelectTrigger>
                  <SelectContent>{INDICADOR_IE.map((o) => <SelectItem key={o.v} value={String(o.v)}>{o.l}</SelectItem>)}</SelectContent>
                </Select>
              </Field>
              <div className="flex items-end gap-4">
                <CheckboxField label="Simples" checked={!!form.simples} onChange={(b) => campo('simples', b)} />
                <CheckboxField label="Emite NF-e" checked={!!form.nfemite} onChange={(b) => campo('nfemite', b)} />
              </div>
            </div>
          </CardContent></Card>
        </TabsContent>

        <TabsContent value="endereco">
          <Card><CardContent className="pt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <Field label="Cidade" required error={erros.cidade_id}>
              <AsyncSelect endpoint="/lookups/cidades" value={form.cidade_id} valueLabel={labels.cidade} error={!!erros.cidade_id}
                onChange={(id, opt) => { campo('cidade_id', id); setLabels((l) => ({ ...l, cidade: opt?.label ?? null, bairro: null })); campo('bairro_id', null); if (opt?.uf) campo('uf', String(opt.uf)) }} />
            </Field>
            <Field label="Bairro">
              <AsyncSelect endpoint="/lookups/bairros" params={{ cidade_id: form.cidade_id }} value={form.bairro_id ?? null} valueLabel={labels.bairro} disabled={!form.cidade_id}
                placeholder={form.cidade_id ? 'Selecione…' : 'Escolha a cidade primeiro'}
                onChange={(id, opt) => { campo('bairro_id', id); setLabels((l) => ({ ...l, bairro: opt?.label ?? null })) }} />
            </Field>
            <Field label="Rua">
              <AsyncSelect endpoint="/lookups/ruas" params={{ bairro_id: form.bairro_id }} value={form.rua_id ?? null} valueLabel={labels.rua}
                onChange={(id, opt) => { campo('rua_id', id); setLabels((l) => ({ ...l, rua: opt?.label ?? null })) }} />
            </Field>
            <Field label="Número" required error={erros.numero}><Input value={form.numero} error={!!erros.numero} onChange={(e) => campo('numero', e.target.value)} /></Field>
            <Field label="CEP"><Input value={form.cep ?? ''} onChange={(e) => campo('cep', e.target.value)} /></Field>
            <Field label="UF"><Input maxLength={2} value={form.uf ?? ''} onChange={(e) => campo('uf', e.target.value.toUpperCase())} /></Field>
            <Field label="Complemento"><Input value={form.complemento ?? ''} onChange={(e) => campo('complemento', e.target.value)} /></Field>
            <Field label="Ponto de referência" className="md:col-span-2"><Input value={form.ponto_referencia ?? ''} onChange={(e) => campo('ponto_referencia', e.target.value)} /></Field>
          </CardContent></Card>
        </TabsContent>

        {editId && <TabsContent value="contatos"><Card><CardContent className="pt-6"><TelefonesTab clienteId={editId} /></CardContent></Card></TabsContent>}
        {editId && <TabsContent value="historico"><HistoricoTab clienteId={editId} /></TabsContent>}
        {editId && <TabsContent value="interacoes"><Card><CardContent className="pt-6"><InteracoesTab clienteId={editId} /></CardContent></Card></TabsContent>}
        {editId && <TabsContent value="convenio"><Card><CardContent className="pt-6"><ConvenioTab clienteId={editId} /></CardContent></Card></TabsContent>}
        {editId && <TabsContent value="precos"><PrecosTab clienteId={editId} /></TabsContent>}
      </Tabs>
    </div>
  )
}
