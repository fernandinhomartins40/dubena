import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { ArrowLeft, Save } from 'lucide-react'
import {
  Button, Card, CardContent, PageHeader, Field, Input, CheckboxField, AsyncSelect,
  Tabs, TabsList, TabsTrigger, TabsContent,
  Select, SelectTrigger, SelectValue, SelectContent, SelectItem, toast,
} from '@/components/ui'
import { ConfigTab } from './ConfigTab'
import { CertificadoSection } from './CertificadoSection'
import { IntegracoesSection } from './IntegracoesSection'
import { useResourceForm } from '@/lib/useResourceForm'
import { useEmpresa, useSalvarEmpresa } from './api'

/** Campos da empresa que o form edita (subconjunto amigável do fillable). */
const CAMPOS = [
  'razao_social', 'nome_fantasia', 'nome_informal', 'cnpj', 'inscricao_estadual', 'inscricao_municipal',
  'inscricao_estadual_st', 'suframa', 'cnae', 'registro_anp',
  'cidade_id', 'bairro_id', 'rua_id', 'numero', 'complemento', 'cep', 'uf', 'regiao_id',
  'telefone1', 'telefone2', 'email',
  'contnome', 'contcpf', 'contcnpj', 'contcrc', 'conttelefone', 'contemail',
  'nfeserie', 'nfenumero', 'nfetipoambiente', 'nfecrt', 'nfceserie', 'nfcenumero', 'nfcetipoambiente',
  'ativo', 'matriz', 'nfeemite', 'nfceemite', 'spedemite', 'distribuidora',
] as const

type Form = Record<string, any>

const AMBIENTE = [{ v: 1, l: '1 - Produção' }, { v: 2, l: '2 - Homologação' }]
const CRT = [{ v: 1, l: '1 - Simples Nacional' }, { v: 2, l: '2 - Excesso de Sublimite' }, { v: 3, l: '3 - Regime Normal' }]

export function EmpresaFormPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const editId = id && id !== 'novo' ? Number(id) : null
  const { data: existente } = useEmpresa(editId)
  const salvar = useSalvarEmpresa()

  const [aba, setAba] = useState('identificacao')
  const [labels, setLabels] = useState<Record<string, string | null>>({})
  const { form, campo, erros, submit } = useResourceForm<Form>({
    vazio: { ativo: true },
    existente: existente as Form | undefined,
    hidratar: (ex) => {
      const f: Form = {}
      CAMPOS.forEach((k) => { if (ex[k] !== undefined && ex[k] !== null) f[k] = ex[k] })
      ;['ativo', 'matriz', 'nfeemite', 'nfceemite', 'spedemite', 'distribuidora'].forEach((k) => { f[k] = !!Number(ex[k]) })
      return f
    },
  })

  useEffect(() => {
    if (existente) setLabels({ cidade: existente.cidade_label, bairro: existente.bairro_label, regiao: existente.regiao_label })
  }, [existente])

  async function onSubmit() {
    try {
      const salvo = await submit((data) => salvar.mutateAsync({ id: editId, data }))
      toast.success(editId ? 'Empresa atualizada.' : 'Empresa cadastrada.')
      navigate(`/empresas/${salvo.id}`)
    } catch (e: any) {
      if (e?.response?.status === 422 && e.response.data?.errors) {
        setAba('identificacao'); toast.error('Verifique os campos destacados.')
      } else toast.error(e?.response?.data?.message ?? 'Erro ao salvar a empresa.')
    }
  }

  return (
    <div>
      <PageHeader
        breadcrumb={<button onClick={() => navigate('/empresas')} className="hover:text-foreground">Empresas</button>}
        title={editId ? (form.nome_informal || form.razao_social || 'Empresa') : 'Nova empresa'}
        subtitle={editId ? `#${editId}` : 'Preencha os dados da nova empresa'}
        action={
          <>
            <Button variant="outline" onClick={() => navigate('/empresas')}><ArrowLeft size={16} /> Voltar</Button>
            <Button loading={salvar.isPending} onClick={onSubmit}><Save size={16} /> Salvar</Button>
          </>
        }
      />

      <Tabs value={aba} onValueChange={setAba}>
        <TabsList className="overflow-x-auto">
          <TabsTrigger value="identificacao">Identificação</TabsTrigger>
          <TabsTrigger value="endereco">Endereço</TabsTrigger>
          <TabsTrigger value="contato">Contato</TabsTrigger>
          <TabsTrigger value="fiscal">Fiscal / NF-e</TabsTrigger>
          <TabsTrigger value="contador">Contador</TabsTrigger>
          {editId && <TabsTrigger value="integracoes">Integrações</TabsTrigger>}
          {editId && <TabsTrigger value="config">Configurações</TabsTrigger>}
        </TabsList>

        <TabsContent value="identificacao">
          <Card><CardContent className="pt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <Field label="Razão social" required error={erros.razao_social} className="md:col-span-2"><Input value={form.razao_social ?? ''} error={!!erros.razao_social} onChange={(e) => campo('razao_social', e.target.value)} /></Field>
            <Field label="Nome fantasia"><Input value={form.nome_fantasia ?? ''} onChange={(e) => campo('nome_fantasia', e.target.value)} /></Field>
            <Field label="Nome informal"><Input value={form.nome_informal ?? ''} onChange={(e) => campo('nome_informal', e.target.value)} /></Field>
            <Field label="CNPJ"><Input value={form.cnpj ?? ''} onChange={(e) => campo('cnpj', e.target.value)} /></Field>
            <Field label="Inscrição estadual"><Input value={form.inscricao_estadual ?? ''} onChange={(e) => campo('inscricao_estadual', e.target.value)} /></Field>
            <Field label="Inscrição municipal"><Input value={form.inscricao_municipal ?? ''} onChange={(e) => campo('inscricao_municipal', e.target.value)} /></Field>
            <Field label="IE ST"><Input value={form.inscricao_estadual_st ?? ''} onChange={(e) => campo('inscricao_estadual_st', e.target.value)} /></Field>
            <Field label="CNAE"><Input value={form.cnae ?? ''} onChange={(e) => campo('cnae', e.target.value)} /></Field>
            <Field label="Registro ANP"><Input value={form.registro_anp ?? ''} onChange={(e) => campo('registro_anp', e.target.value)} /></Field>
            <Field label="Suframa"><Input value={form.suframa ?? ''} onChange={(e) => campo('suframa', e.target.value)} /></Field>
            <div className="md:col-span-2 grid grid-cols-2 sm:grid-cols-4 gap-2 border-t border-border pt-3">
              <CheckboxField label="Ativa" checked={!!form.ativo} onChange={(b) => campo('ativo', b)} />
              <CheckboxField label="Matriz" checked={!!form.matriz} onChange={(b) => campo('matriz', b)} />
              <CheckboxField label="Distribuidora" checked={!!form.distribuidora} onChange={(b) => campo('distribuidora', b)} />
            </div>
          </CardContent></Card>
        </TabsContent>

        <TabsContent value="endereco">
          <Card><CardContent className="pt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <Field label="Cidade"><AsyncSelect endpoint="/lookups/cidades" value={form.cidade_id ?? null} valueLabel={labels.cidade}
              onChange={(id, opt) => { campo('cidade_id', id); setLabels((l) => ({ ...l, cidade: opt?.label ?? null, bairro: null })); campo('bairro_id', null); if (opt?.uf) campo('uf', String(opt.uf)) }} /></Field>
            <Field label="Bairro"><AsyncSelect endpoint="/lookups/bairros" params={{ cidade_id: form.cidade_id }} value={form.bairro_id ?? null} valueLabel={labels.bairro} disabled={!form.cidade_id}
              onChange={(id, opt) => { campo('bairro_id', id); setLabels((l) => ({ ...l, bairro: opt?.label ?? null })) }} /></Field>
            <Field label="Número"><Input value={form.numero ?? ''} onChange={(e) => campo('numero', e.target.value)} /></Field>
            <Field label="Complemento"><Input value={form.complemento ?? ''} onChange={(e) => campo('complemento', e.target.value)} /></Field>
            <Field label="CEP"><Input value={form.cep ?? ''} onChange={(e) => campo('cep', e.target.value)} /></Field>
            <Field label="UF"><Input maxLength={2} value={form.uf ?? ''} onChange={(e) => campo('uf', e.target.value.toUpperCase())} /></Field>
            <Field label="Região"><AsyncSelect endpoint="/lookups/regioes" value={form.regiao_id ?? null} valueLabel={labels.regiao}
              onChange={(id, opt) => { campo('regiao_id', id); setLabels((l) => ({ ...l, regiao: opt?.label ?? null })) }} /></Field>
          </CardContent></Card>
        </TabsContent>

        <TabsContent value="contato">
          <Card><CardContent className="pt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <Field label="Telefone 1"><Input value={form.telefone1 ?? ''} onChange={(e) => campo('telefone1', e.target.value)} /></Field>
            <Field label="Telefone 2"><Input value={form.telefone2 ?? ''} onChange={(e) => campo('telefone2', e.target.value)} /></Field>
            <Field label="E-mail" className="md:col-span-2"><Input type="email" value={form.email ?? ''} onChange={(e) => campo('email', e.target.value)} /></Field>
          </CardContent></Card>
        </TabsContent>

        <TabsContent value="fiscal">
          <Card><CardContent className="pt-6 space-y-4">
            <div className="flex flex-wrap gap-6 border-b border-border pb-3">
              <CheckboxField label="Emite NF-e" checked={!!form.nfeemite} onChange={(b) => campo('nfeemite', b)} />
              <CheckboxField label="Emite NFC-e" checked={!!form.nfceemite} onChange={(b) => campo('nfceemite', b)} />
              <CheckboxField label="Emite SPED" checked={!!form.spedemite} onChange={(b) => campo('spedemite', b)} />
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Field label="Série NF-e"><Input value={form.nfeserie ?? ''} onChange={(e) => campo('nfeserie', e.target.value)} /></Field>
              <Field label="Número NF-e"><Input type="number" value={form.nfenumero ?? ''} onChange={(e) => campo('nfenumero', e.target.value)} /></Field>
              <Field label="Ambiente NF-e">
                <Select value={String(form.nfetipoambiente ?? '')} onValueChange={(v) => campo('nfetipoambiente', Number(v))}>
                  <SelectTrigger><SelectValue placeholder="Selecione" /></SelectTrigger>
                  <SelectContent>{AMBIENTE.map((a) => <SelectItem key={a.v} value={String(a.v)}>{a.l}</SelectItem>)}</SelectContent>
                </Select>
              </Field>
              <Field label="CRT (NF-e)">
                <Select value={String(form.nfecrt ?? '')} onValueChange={(v) => campo('nfecrt', Number(v))}>
                  <SelectTrigger><SelectValue placeholder="Selecione" /></SelectTrigger>
                  <SelectContent>{CRT.map((c) => <SelectItem key={c.v} value={String(c.v)}>{c.l}</SelectItem>)}</SelectContent>
                </Select>
              </Field>
              <Field label="Série NFC-e"><Input value={form.nfceserie ?? ''} onChange={(e) => campo('nfceserie', e.target.value)} /></Field>
              <Field label="Número NFC-e"><Input type="number" value={form.nfcenumero ?? ''} onChange={(e) => campo('nfcenumero', e.target.value)} /></Field>
              <Field label="Ambiente NFC-e">
                <Select value={String(form.nfcetipoambiente ?? '')} onValueChange={(v) => campo('nfcetipoambiente', Number(v))}>
                  <SelectTrigger><SelectValue placeholder="Selecione" /></SelectTrigger>
                  <SelectContent>{AMBIENTE.map((a) => <SelectItem key={a.v} value={String(a.v)}>{a.l}</SelectItem>)}</SelectContent>
                </Select>
              </Field>
            </div>
            {editId
              ? <CertificadoSection empresaId={editId} />
              : <p className="text-xs text-muted-foreground">Salve a empresa para enviar o certificado digital e os tokens NFC-e.</p>}
          </CardContent></Card>
        </TabsContent>

        {editId && (
          <TabsContent value="integracoes">
            <Card><CardContent className="pt-6">
              <p className="text-sm font-semibold">Integrações de pagamento (por empresa)</p>
              <p className="text-xs text-muted-foreground">PIX e cartão com as credenciais desta revenda.</p>
              <IntegracoesSection empresaId={editId} />
            </CardContent></Card>
          </TabsContent>
        )}

        <TabsContent value="contador">
          <Card><CardContent className="pt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <Field label="Nome"><Input value={form.contnome ?? ''} onChange={(e) => campo('contnome', e.target.value)} /></Field>
            <Field label="CRC"><Input value={form.contcrc ?? ''} onChange={(e) => campo('contcrc', e.target.value)} /></Field>
            <Field label="CPF"><Input value={form.contcpf ?? ''} onChange={(e) => campo('contcpf', e.target.value)} /></Field>
            <Field label="CNPJ"><Input value={form.contcnpj ?? ''} onChange={(e) => campo('contcnpj', e.target.value)} /></Field>
            <Field label="Telefone"><Input value={form.conttelefone ?? ''} onChange={(e) => campo('conttelefone', e.target.value)} /></Field>
            <Field label="E-mail"><Input type="email" value={form.contemail ?? ''} onChange={(e) => campo('contemail', e.target.value)} /></Field>
          </CardContent></Card>
        </TabsContent>

        {editId && <TabsContent value="config"><ConfigTab empresaId={editId} /></TabsContent>}
      </Tabs>
    </div>
  )
}
