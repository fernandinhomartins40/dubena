import { useState } from 'react'
import { CreditCard, QrCode, ShieldCheck, ShieldAlert } from 'lucide-react'
import {
  Button, Field, Input, Badge, Skeleton, toast,
  Select, SelectTrigger, SelectValue, SelectContent, SelectItem,
} from '@/components/ui'
import { useIntegracoes, useSalvarIntegracoes } from './api'

/**
 * Integrações por EMPRESA (multi-tenant): PIX (PSP) e cartão (gateway). Cada
 * revenda usa o SEU credenciamento. Segredos são WRITE-ONLY — a tela mostra
 * "configurado" e um campo para (re)enviar; nunca exibe o segredo salvo. Deixar
 * o campo de segredo vazio ao salvar preserva o valor existente.
 */
export function IntegracoesSection({ empresaId }: { empresaId: number }) {
  const { data, isLoading } = useIntegracoes(empresaId)
  const salvar = useSalvarIntegracoes(empresaId)

  const [pix, setPix] = useState<Record<string, string>>({})
  const [cartao, setCartao] = useState<Record<string, string>>({})

  async function salvarPix() {
    try { await salvar.mutateAsync({ pix }); toast.success('Integração PIX salva.'); setPix({}) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao salvar PIX.') }
  }
  async function salvarCartao() {
    try { await salvar.mutateAsync({ cartao }); toast.success('Integração de cartão salva.'); setCartao({}) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao salvar cartão.') }
  }

  if (isLoading) return <div className="border-t border-border pt-4 mt-4"><Skeleton className="h-24 w-full" /></div>

  const configurado = (ok?: boolean) => ok
    ? <Badge variant="success"><ShieldCheck size={12} className="mr-1" /> Configurado</Badge>
    : <Badge variant="warning"><ShieldAlert size={12} className="mr-1" /> Pendente</Badge>

  return (
    <div className="border-t border-border pt-4 mt-4 space-y-8">
      {/* PIX */}
      <div>
        <div className="flex items-center justify-between mb-2">
          <p className="text-sm font-semibold flex items-center gap-2"><QrCode size={16} /> PIX (PSP)</p>
          {configurado(data?.pix.client_secret_configurado)}
        </div>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
          <Field label="PSP / Banco">
            <Select value={pix.psp ?? data?.pix.psp ?? 'itau'} onValueChange={(v) => setPix((s) => ({ ...s, psp: v }))}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="itau">Itaú</SelectItem>
                <SelectItem value="bb">Banco do Brasil</SelectItem>
                <SelectItem value="caixa">Caixa</SelectItem>
                <SelectItem value="sicoob">Sicoob</SelectItem>
              </SelectContent>
            </Select>
          </Field>
          <Field label="Ambiente">
            <Select value={pix.ambiente ?? data?.pix.ambiente ?? 'homologacao'} onValueChange={(v) => setPix((s) => ({ ...s, ambiente: v }))}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="homologacao">Homologação</SelectItem>
                <SelectItem value="producao">Produção</SelectItem>
              </SelectContent>
            </Select>
          </Field>
          <Field label="Client ID"><Input value={pix.client_id ?? data?.pix.client_id ?? ''} onChange={(e) => setPix((s) => ({ ...s, client_id: e.target.value }))} /></Field>
          <Field label="Chave PIX recebedora"><Input value={pix.chave ?? data?.pix.chave ?? ''} onChange={(e) => setPix((s) => ({ ...s, chave: e.target.value }))} placeholder="e-mail / CNPJ / aleatória" /></Field>
          <Field label="Client Secret" hint={data?.pix.client_secret_configurado ? 'Já configurado — reenvie só para trocar' : undefined}>
            <Input type="password" value={pix.client_secret ?? ''} onChange={(e) => setPix((s) => ({ ...s, client_secret: e.target.value }))} placeholder="••••••••" />
          </Field>
          <Field label="Segredo do webhook (HMAC)" hint={data?.pix.webhook_hmac_configurado ? 'Já configurado' : 'Opcional — valida a assinatura do PSP'}>
            <Input type="password" value={pix.webhook_hmac_secret ?? ''} onChange={(e) => setPix((s) => ({ ...s, webhook_hmac_secret: e.target.value }))} placeholder="••••••••" />
          </Field>
        </div>
        <div className="mt-3"><Button loading={salvar.isPending} onClick={salvarPix}>Salvar PIX</Button></div>
      </div>

      {/* Cartão */}
      <div>
        <div className="flex items-center justify-between mb-2">
          <p className="text-sm font-semibold flex items-center gap-2"><CreditCard size={16} /> Cartão (gateway)</p>
          {configurado(data?.cartao.token_configurado)}
        </div>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
          <Field label="Gateway">
            <Select value={cartao.gateway ?? data?.cartao.gateway ?? 'erede'} onValueChange={(v) => setCartao((s) => ({ ...s, gateway: v }))}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="erede">e.Rede</SelectItem>
              </SelectContent>
            </Select>
          </Field>
          <Field label="PV (ponto de venda)"><Input value={cartao.pv ?? data?.cartao.pv ?? ''} onChange={(e) => setCartao((s) => ({ ...s, pv: e.target.value }))} /></Field>
          <Field label="Token" hint={data?.cartao.token_configurado ? 'Já configurado — reenvie só para trocar' : undefined}>
            <Input type="password" value={cartao.token ?? ''} onChange={(e) => setCartao((s) => ({ ...s, token: e.target.value }))} placeholder="••••••••" />
          </Field>
          <Field label="URL da API (opcional)"><Input value={cartao.url ?? data?.cartao.url ?? ''} onChange={(e) => setCartao((s) => ({ ...s, url: e.target.value }))} /></Field>
        </div>
        <div className="mt-3"><Button loading={salvar.isPending} onClick={salvarCartao}>Salvar cartão</Button></div>
      </div>

      <p className="text-xs text-muted-foreground">
        Cada empresa cobra com as PRÓPRIAS credenciais. Os segredos são gravados cifrados e nunca são exibidos de volta —
        para trocar, basta reenviar o campo. O Google Maps é configurado no nível do grupo (Configurações globais).
      </p>
    </div>
  )
}
