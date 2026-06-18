import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { ArrowLeft, Save } from 'lucide-react'
import { Button, Card, Input, PageHeader, Tabs } from '@/components/ui'
import { useCliente, useSalvarCliente, type ClienteForm } from './api'

const VAZIO: ClienteForm = {
  nome: '', fantasia: '', numero: '', cidade_id: null, bairro_id: null,
  uf: '', cep: '', email: '', cpf: '', cnpj: '', sexo: '',
  tipopessoa_id: null, segmento_id: null, observacoes: '',
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

  useEffect(() => {
    if (existente) {
      setForm({ ...VAZIO, ...existente })
    }
  }, [existente])

  function campo<K extends keyof ClienteForm>(k: K, v: ClienteForm[K]) {
    setForm((f) => ({ ...f, [k]: v }))
  }

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
        <div className="px-4 pt-2">
          <Tabs
            active={aba}
            onChange={setAba}
            tabs={[
              { id: 'dados', label: 'Dados' },
              { id: 'endereco', label: 'Endereço' },
              { id: 'fiscal', label: 'Fiscal' },
            ]}
          />
        </div>

        <div className="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
          {aba === 'dados' && (
            <>
              <Input label="Nome / Razão social *" value={form.nome} onChange={(e) => campo('nome', e.target.value)} error={erros.nome} />
              <Input label="Nome fantasia" value={form.fantasia ?? ''} onChange={(e) => campo('fantasia', e.target.value)} />
              <Input label="E-mail" type="email" value={form.email ?? ''} onChange={(e) => campo('email', e.target.value)} error={erros.email} />
              <Input label="Sexo (M/F)" maxLength={1} value={form.sexo ?? ''} onChange={(e) => campo('sexo', e.target.value.toUpperCase())} />
            </>
          )}

          {aba === 'endereco' && (
            <>
              <Input label="Número *" value={form.numero} onChange={(e) => campo('numero', e.target.value)} error={erros.numero} />
              <Input label="CEP" value={form.cep ?? ''} onChange={(e) => campo('cep', e.target.value)} />
              <Input label="Cidade (ID) *" type="number" value={form.cidade_id ?? ''} onChange={(e) => campo('cidade_id', e.target.value ? Number(e.target.value) : null)} error={erros.cidade_id} />
              <Input label="Bairro (ID)" type="number" value={form.bairro_id ?? ''} onChange={(e) => campo('bairro_id', e.target.value ? Number(e.target.value) : null)} />
              <Input label="UF" maxLength={2} value={form.uf ?? ''} onChange={(e) => campo('uf', e.target.value.toUpperCase())} />
            </>
          )}

          {aba === 'fiscal' && (
            <>
              <Input label="CPF" value={form.cpf ?? ''} onChange={(e) => campo('cpf', e.target.value)} error={erros.cpf} />
              <Input label="CNPJ" value={form.cnpj ?? ''} onChange={(e) => campo('cnpj', e.target.value)} error={erros.cnpj} />
            </>
          )}
        </div>
      </Card>
    </div>
  )
}
