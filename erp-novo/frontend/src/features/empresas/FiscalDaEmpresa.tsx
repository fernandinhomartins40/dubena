import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AlertTriangle } from 'lucide-react'
import { api } from '@/lib/api'
import { Badge, CheckboxField, Field, Input, Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui'

/**
 * Fiscal da empresa: o que a EMISSÃO de nota usa (`config_fiscais`) mais
 * contador e cadastro fiscal.
 *
 * Antes estes campos viajavam no formulário da empresa para `PUT
 * /empresas/{id}`, que os descartava todos; e a emissão lia `config_fiscais`,
 * que nenhuma tela gravava. Aqui têm estado e endpoint próprios
 * (`/empresas/{id}/fiscal`), e só o que mudou é enviado.
 */
export interface Fiscal {
  ambiente: number | null
  regime_tributario: number | null
  serie_nfe: number | null
  serie_nfce: number | null
  csc_id: string | null
  tem_csc: boolean
  ultimo_numero_nfe: number | null
  ultimo_numero_nfce: number | null
  nfe_emite: boolean
  nfce_emite: boolean
  sped_emite: boolean
  contador: Record<string, string | null>
  cadastro: Record<string, string | boolean | null>
}

const VAZIO: Fiscal = {
  ambiente: 2, regime_tributario: null, serie_nfe: 1, serie_nfce: 1, csc_id: null, tem_csc: false,
  ultimo_numero_nfe: null, ultimo_numero_nfce: null, nfe_emite: false, nfce_emite: false, sped_emite: false,
  contador: {}, cadastro: {},
}

const AMBIENTE = [{ v: 1, l: 'Produção (nota com valor fiscal)' }, { v: 2, l: 'Homologação (teste, sem valor fiscal)' }]
const CRT = [{ v: 1, l: '1 - Simples Nacional' }, { v: 2, l: '2 - Excesso de Sublimite' }, { v: 3, l: '3 - Regime Normal' }]

export function useFiscalDaEmpresa(empresaId: number | null) {
  const qc = useQueryClient()
  const q = useQuery<Fiscal>({
    queryKey: ['empresa-fiscal', empresaId],
    enabled: empresaId !== null,
    queryFn: async () => (await api.get(`/empresas/${empresaId}/fiscal`)).data.data,
  })
  const [fx, setFx] = useState<Fiscal & { csc_token?: string }>(VAZIO)
  useEffect(() => { if (q.data) setFx({ ...VAZIO, ...q.data, csc_token: '' }) }, [q.data])

  const mut = useMutation({
    mutationFn: async ({ id, data }: { id: number; data: Record<string, unknown> }) => (await api.put(`/empresas/${id}/fiscal`, data)).data.data,
    onSuccess: (_d, v) => qc.invalidateQueries({ queryKey: ['empresa-fiscal', v.id] }),
  })

  /** Só o que mudou em relação ao que veio do servidor (ou tudo, em empresa nova). */
  function alteracoes(): Record<string, unknown> {
    const base = (q.data ?? VAZIO) as unknown as Record<string, unknown>
    const out: Record<string, unknown> = {}
    for (const k of ['ambiente', 'regime_tributario', 'serie_nfe', 'serie_nfce', 'csc_id', 'nfe_emite', 'nfce_emite', 'sped_emite', 'contador', 'cadastro'] as const) {
      if (JSON.stringify(fx[k]) !== JSON.stringify(base[k])) out[k] = fx[k]
    }
    if (fx.csc_token) out.csc_token = fx.csc_token
    return out
  }

  async function salvar(id: number): Promise<boolean> {
    const dados = alteracoes()
    if (Object.keys(dados).length === 0) return false
    await mut.mutateAsync({ id, data: dados })
    return true
  }

  return { fx, setFx, salvar, carregando: q.isLoading }
}

type Ctl = ReturnType<typeof useFiscalDaEmpresa>

export function FiscalCampos({ ctl }: { ctl: Ctl }) {
  const { fx, setFx } = ctl
  const set = <K extends keyof Fiscal>(k: K, v: Fiscal[K]) => setFx((f) => ({ ...f, [k]: v }))
  return (
    <div className="space-y-4">
      <div className="flex flex-wrap gap-6 border-b border-border pb-3">
        <CheckboxField label="Emite NF-e" checked={fx.nfe_emite} onChange={(b) => set('nfe_emite', b)} />
        <CheckboxField label="Emite NFC-e" checked={fx.nfce_emite} onChange={(b) => set('nfce_emite', b)} />
        <CheckboxField label="Emite SPED" checked={fx.sped_emite} onChange={(b) => set('sped_emite', b)} />
      </div>
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <Field label="Ambiente de emissão" hint="Vale para NF-e e NFC-e desta empresa">
          <Select value={String(fx.ambiente ?? '')} onValueChange={(v) => set('ambiente', Number(v))}>
            <SelectTrigger><SelectValue placeholder="Selecione" /></SelectTrigger>
            <SelectContent>{AMBIENTE.map((a) => <SelectItem key={a.v} value={String(a.v)}>{a.l}</SelectItem>)}</SelectContent>
          </Select>
        </Field>
        <Field label="Regime tributário (CRT)">
          <Select value={String(fx.regime_tributario ?? '')} onValueChange={(v) => set('regime_tributario', Number(v))}>
            <SelectTrigger><SelectValue placeholder="Selecione" /></SelectTrigger>
            <SelectContent>{CRT.map((c) => <SelectItem key={c.v} value={String(c.v)}>{c.l}</SelectItem>)}</SelectContent>
          </Select>
        </Field>
        <Field label="Série NF-e" hint={fx.ultimo_numero_nfe != null ? `Último número emitido: ${fx.ultimo_numero_nfe}` : undefined}>
          <Input type="number" min={1} value={fx.serie_nfe ?? ''} onChange={(e) => set('serie_nfe', e.target.value ? Number(e.target.value) : null)} />
        </Field>
        <Field label="Série NFC-e" hint={fx.ultimo_numero_nfce != null ? `Último número emitido: ${fx.ultimo_numero_nfce}` : undefined}>
          <Input type="number" min={1} value={fx.serie_nfce ?? ''} onChange={(e) => set('serie_nfce', e.target.value ? Number(e.target.value) : null)} />
        </Field>
        <Field label="ID do CSC (NFC-e)" hint="Código de segurança do contribuinte, fornecido pela SEFAZ para o ambiente escolhido">
          <Input value={fx.csc_id ?? ''} onChange={(e) => set('csc_id', e.target.value || null)} />
        </Field>
        <Field label="CSC (NFC-e)" hint={fx.tem_csc ? 'Já cadastrado — deixe em branco para manter' : 'Ainda não cadastrado'}>
          <Input type="password" autoComplete="off" value={fx.csc_token ?? ''} placeholder={fx.tem_csc ? '•••••• (salvo)' : ''} onChange={(e) => setFx((f) => ({ ...f, csc_token: e.target.value }))} />
        </Field>
      </div>
      {fx.ambiente === 1
        ? <p className="flex items-center gap-1.5 text-sm font-medium"><Badge variant="success">Produção</Badge> Notas emitidas têm valor fiscal.</p>
        : <p className="flex items-center gap-1.5 text-sm text-muted-foreground"><AlertTriangle size={15} /> Em homologação as notas são de teste e não têm valor fiscal.</p>}
      <p className="text-xs text-muted-foreground">A numeração é controlada pelo sistema, para nunca repetir número de nota.</p>
    </div>
  )
}

export function ContadorCampos({ ctl }: { ctl: Ctl }) {
  const { fx, setFx } = ctl
  const set = (k: string, v: string) => setFx((f) => ({ ...f, contador: { ...f.contador, [k]: v || null } }))
  const v = (k: string) => (fx.contador[k] as string | null) ?? ''
  return (
    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
      <Field label="Nome"><Input value={v('nome')} onChange={(e) => set('nome', e.target.value)} /></Field>
      <Field label="CRC"><Input value={v('crc')} onChange={(e) => set('crc', e.target.value)} /></Field>
      <Field label="CPF"><Input value={v('cpf')} onChange={(e) => set('cpf', e.target.value)} /></Field>
      <Field label="CNPJ"><Input value={v('cnpj')} onChange={(e) => set('cnpj', e.target.value)} /></Field>
      <Field label="Telefone"><Input value={v('telefone')} onChange={(e) => set('telefone', e.target.value)} /></Field>
      <Field label="E-mail"><Input type="email" value={v('email')} onChange={(e) => set('email', e.target.value)} /></Field>
    </div>
  )
}

/** CNAE, ANP, SUFRAMA, IE ST, e-mail e "distribuidora" — moram no cadastro fiscal. */
export function cadastroFiscal(ctl: Ctl) {
  const { fx, setFx } = ctl
  return {
    valor: (k: string) => fx.cadastro[k],
    set: (k: string, v: string | boolean | null) => setFx((f) => ({ ...f, cadastro: { ...f.cadastro, [k]: v === '' ? null : v } })),
  }
}

interface Prontidao { pronto: boolean; itens: { chave: string; titulo: string; ok: boolean; detalhe: string | null }[] }

/**
 * "Pronto para emitir?" — a lista inteira do que a emissão exige, conferida
 * antes da primeira nota (servidor: `ProntidaoFiscalService`). Sem isto cada
 * falta só aparecia como erro ao transmitir, uma de cada vez.
 */
export function ProntidaoFiscal({ empresaId }: { empresaId: number }) {
  const q = useQuery<Prontidao>({
    queryKey: ['empresa-fiscal', empresaId, 'prontidao'],
    queryFn: async () => (await api.get(`/empresas/${empresaId}/fiscal/prontidao`)).data.data,
  })
  if (q.isLoading || !q.data) return null
  const faltam = q.data.itens.filter((i) => !i.ok)
  return (
    <section aria-labelledby="prontidao-titulo" className={`rounded-lg border p-4 ${q.data.pronto ? 'border-success/40' : 'border-primary/50'}`}>
      <h3 id="prontidao-titulo" className="mb-2 flex items-center gap-2 text-sm font-semibold">
        {q.data.pronto
          ? <><Badge variant="success">Pronto</Badge> Tudo certo para emitir notas.</>
          : <><Badge variant="warning">{faltam.length} pendência(s)</Badge> Antes de emitir notas:</>}
      </h3>
      <ul className="space-y-1.5 text-sm">
        {q.data.itens.map((i) => (
          <li key={i.chave} className="flex items-start gap-2">
            <span aria-hidden className={i.ok ? 'text-success' : 'text-destructive'}>{i.ok ? '✓' : '✗'}</span>
            <span>
              <span className={i.ok ? 'text-muted-foreground' : 'font-medium'}>{i.titulo}</span>
              {!i.ok && i.detalhe && <span className="block text-xs text-muted-foreground">{i.detalhe}</span>}
            </span>
          </li>
        ))}
      </ul>
    </section>
  )
}
