import { useEffect, useState } from 'react'
import { Save, KeyRound, Mail } from 'lucide-react'
import {
  Button, Card, CardContent, Field, Input, CheckboxField, AsyncSelect, Skeleton,
  Tabs, TabsList, TabsTrigger, TabsContent,
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogFooter, DialogClose, toast,
} from '@/components/ui'
import { useEmpresaConfig, useSalvarConfig, useSenhaMestra, useTestarEmail } from './api'

type Form = Record<string, any>

/** Aba Configurações da empresa: 106 colunas reorganizadas em SUB-ABAS temáticas. */
export function ConfigTab({ empresaId }: { empresaId: number }) {
  const { data, isLoading } = useEmpresaConfig(empresaId)
  const salvar = useSalvarConfig(empresaId)
  const [form, setForm] = useState<Form>({})
  const [labels, setLabels] = useState<Record<string, string | null>>({})

  useEffect(() => { if (data) setForm({ ...data }) }, [data])
  const campo = (k: string, v: any) => setForm((p) => ({ ...p, [k]: v }))
  const lbl = (k: string, v: string | null) => setLabels((l) => ({ ...l, [k]: v }))

  async function onSubmit() {
    try { await salvar.mutateAsync(form); toast.success('Configurações salvas.') }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao salvar configurações.') }
  }

  if (isLoading) return <Card><CardContent className="pt-6 space-y-3">{Array.from({ length: 4 }).map((_, i) => <Skeleton key={i} className="h-10 w-full" />)}</CardContent></Card>

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div className="flex gap-2">
          <SenhaMestraDialog empresaId={empresaId} tem={!!data?.tem_senhamestre} />
          <TesteEmailDialog empresaId={empresaId} />
        </div>
        <Button loading={salvar.isPending} onClick={onSubmit}><Save size={16} /> Salvar configurações</Button>
      </div>

      <Tabs defaultValue="pedido">
        <TabsList className="overflow-x-auto">
          <TabsTrigger value="pedido">Pedido/Entrega</TabsTrigger>
          <TabsTrigger value="estoque">Estoque</TabsTrigger>
          <TabsTrigger value="impressao">Impressão</TabsTrigger>
          <TabsTrigger value="email">E-mail</TabsTrigger>
          <TabsTrigger value="contabil">Contábil</TabsTrigger>
          <TabsTrigger value="frete">Frete</TabsTrigger>
          <TabsTrigger value="percentuais">Percentuais</TabsTrigger>
        </TabsList>

        <TabsContent value="pedido">
          <Card><CardContent className="pt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <Field label="Tempo de entrega (min)"><Input type="number" value={form.tempoentrega ?? ''} onChange={(e) => campo('tempoentrega', e.target.value)} /></Field>
            <Field label="Tempo urgente (min)"><Input type="number" value={form.tempourgente ?? ''} onChange={(e) => campo('tempourgente', e.target.value)} /></Field>
            <Field label="Máximo de parcelas"><Input type="number" value={form.maximoparcelas ?? ''} onChange={(e) => campo('maximoparcelas', e.target.value)} /></Field>
            <Field label="Dias validação cartão"><Input type="number" value={form.pedidovalidacartaodias ?? ''} onChange={(e) => campo('pedidovalidacartaodias', e.target.value)} /></Field>
            <div className="md:col-span-2 grid grid-cols-2 sm:grid-cols-3 gap-2 border-t border-border pt-3">
              <CheckboxField label="Valida atraso" checked={!!form.validaatraso} onChange={(b) => campo('validaatraso', b)} />
              <CheckboxField label="Valida cartão" checked={!!form.pedidovalidacartao} onChange={(b) => campo('pedidovalidacartao', b)} />
              <CheckboxField label="Valida Gás Bolso" checked={!!form.validagasbolso} onChange={(b) => campo('validagasbolso', b)} />
              <CheckboxField label="Valida coordenadas" checked={!!form.validacordenadasentrega} onChange={(b) => campo('validacordenadasentrega', b)} />
              <CheckboxField label="Valida PIX entrega" checked={!!form.validapixentrega} onChange={(b) => campo('validapixentrega', b)} />
              <CheckboxField label="Emite NFC-e no pedido" checked={!!form.pedidoemitenfce} onChange={(b) => campo('pedidoemitenfce', b)} />
            </div>
          </CardContent></Card>
        </TabsContent>

        <TabsContent value="estoque">
          <Card><CardContent className="pt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <Field label="Setor principal"><AsyncSelect endpoint="/lookups/setores" value={form.setorprincipal_id ?? null} valueLabel={labels.setor}
              onChange={(id, o) => { campo('setorprincipal_id', id); lbl('setor', o?.label ?? null) }} /></Field>
            <Field label="Dias p/ inativar por falta de compra"><Input type="number" value={form.qnddiasinativocompra ?? ''} onChange={(e) => campo('qnddiasinativocompra', e.target.value)} /></Field>
            <div className="md:col-span-2"><CheckboxField label="Permite estoque negativo" checked={!!form.permiteestoquenegativo} onChange={(b) => campo('permiteestoquenegativo', b)} /></div>
          </CardContent></Card>
        </TabsContent>

        <TabsContent value="impressao">
          <Card><CardContent className="pt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <Field label="Tipo de impressão"><Input value={form.impressaotipo ?? ''} onChange={(e) => campo('impressaotipo', e.target.value)} /></Field>
            <Field label="Modelo"><Input value={form.impressaomodelo ?? ''} onChange={(e) => campo('impressaomodelo', e.target.value)} /></Field>
            <Field label="Porta"><Input value={form.impressaoporta ?? ''} onChange={(e) => campo('impressaoporta', e.target.value)} /></Field>
            <Field label="Vias do pedido"><Input type="number" value={form.impressaoqtdviaspedido ?? ''} onChange={(e) => campo('impressaoqtdviaspedido', e.target.value)} /></Field>
            <div className="md:col-span-2"><CheckboxField label="Impressão automática" checked={!!form.impressaoautomatica} onChange={(b) => campo('impressaoautomatica', b)} /></div>
          </CardContent></Card>
        </TabsContent>

        <TabsContent value="email">
          <Card><CardContent className="pt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <Field label="Remetente (e-mail)"><Input value={form.emailremetente ?? ''} onChange={(e) => campo('emailremetente', e.target.value)} /></Field>
            <Field label="Nome do remetente"><Input value={form.emailnomeremente ?? ''} onChange={(e) => campo('emailnomeremente', e.target.value)} /></Field>
            <Field label="Usuário SMTP"><Input value={form.emailusuario ?? ''} onChange={(e) => campo('emailusuario', e.target.value)} /></Field>
            <Field label="Senha SMTP" hint="Deixe em branco para manter a atual"><Input type="password" value={form.emailsenha ?? ''} onChange={(e) => campo('emailsenha', e.target.value)} /></Field>
            <Field label="Servidor SMTP"><Input value={form.emailservidorsmtp ?? ''} onChange={(e) => campo('emailservidorsmtp', e.target.value)} /></Field>
            <Field label="Porta SMTP"><Input type="number" value={form.emailportasmtp ?? ''} onChange={(e) => campo('emailportasmtp', e.target.value)} /></Field>
            <Field label="Assunto padrão"><Input value={form.emailassunto ?? ''} onChange={(e) => campo('emailassunto', e.target.value)} /></Field>
            <div className="flex items-end gap-4">
              <CheckboxField label="Requer autenticação" checked={!!form.emailrequerautenticacao} onChange={(b) => campo('emailrequerautenticacao', b)} />
              <CheckboxField label="Conexão TLS" checked={!!form.emailrequerconexaotls} onChange={(b) => campo('emailrequerconexaotls', b)} />
            </div>
          </CardContent></Card>
        </TabsContent>

        <TabsContent value="contabil">
          <Card><CardContent className="pt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <Field label="Plano de contas principal"><AsyncSelect endpoint="/lookups/planos-conta" value={form.planoconta_id ?? null} valueLabel={labels.pc}
              onChange={(id, o) => { campo('planoconta_id', id); lbl('pc', o?.label ?? null) }} /></Field>
            <Field label="Centro de custo principal"><AsyncSelect endpoint="/lookups/centros-custo" value={form.centrocusto_id ?? null} valueLabel={labels.cc}
              onChange={(id, o) => { campo('centrocusto_id', id); lbl('cc', o?.label ?? null) }} /></Field>
            <Field label="PC Cartão"><AsyncSelect endpoint="/lookups/planos-conta" value={form.pccartao_id ?? null} valueLabel={labels.pccartao}
              onChange={(id, o) => { campo('pccartao_id', id); lbl('pccartao', o?.label ?? null) }} /></Field>
            <Field label="PC Vale-Gás"><AsyncSelect endpoint="/lookups/planos-conta" value={form.pcvalegas_id ?? null} valueLabel={labels.pcvg}
              onChange={(id, o) => { campo('pcvalegas_id', id); lbl('pcvg', o?.label ?? null) }} /></Field>
            <Field label="CC Vale-Gás"><AsyncSelect endpoint="/lookups/centros-custo" value={form.ccvalegas_id ?? null} valueLabel={labels.ccvg}
              onChange={(id, o) => { campo('ccvalegas_id', id); lbl('ccvg', o?.label ?? null) }} /></Field>
            <Field label="Conta malote"><AsyncSelect endpoint="/lookups/contas" value={form.maloteconta_id ?? null} valueLabel={labels.malote}
              onChange={(id, o) => { campo('maloteconta_id', id); lbl('malote', o?.label ?? null) }} /></Field>
          </CardContent></Card>
        </TabsContent>

        <TabsContent value="frete">
          <Card><CardContent className="pt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <Field label="PC Frete"><AsyncSelect endpoint="/lookups/planos-conta" value={form.pcfrete_id ?? null} valueLabel={labels.pcf}
              onChange={(id, o) => { campo('pcfrete_id', id); lbl('pcf', o?.label ?? null) }} /></Field>
            <Field label="CC Frete"><AsyncSelect endpoint="/lookups/centros-custo" value={form.ccfrete_id ?? null} valueLabel={labels.ccf}
              onChange={(id, o) => { campo('ccfrete_id', id); lbl('ccf', o?.label ?? null) }} /></Field>
            <Field label="Valor frete GP"><Input value={form.valorfretegp ?? ''} onChange={(e) => campo('valorfretegp', e.target.value)} /></Field>
          </CardContent></Card>
        </TabsContent>

        <TabsContent value="percentuais">
          <Card><CardContent className="pt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <Field label="Encargos (%)"><Input value={form.percentualencargos ?? ''} onChange={(e) => campo('percentualencargos', e.target.value)} /></Field>
            <Field label="Provisão devedores (%)"><Input value={form.percentualprovisaodevedores ?? ''} onChange={(e) => campo('percentualprovisaodevedores', e.target.value)} /></Field>
            <Field label="Remuneração capital (%)"><Input value={form.percentualremuneracaocapital ?? ''} onChange={(e) => campo('percentualremuneracaocapital', e.target.value)} /></Field>
            <Field label="Distribuição resultado (%)"><Input value={form.percentualdistribuicaoresul ?? ''} onChange={(e) => campo('percentualdistribuicaoresul', e.target.value)} /></Field>
          </CardContent></Card>
        </TabsContent>
      </Tabs>
    </div>
  )
}

function SenhaMestraDialog({ empresaId, tem }: { empresaId: number; tem: boolean }) {
  const mut = useSenhaMestra(empresaId)
  const [open, setOpen] = useState(false)
  const [atual, setAtual] = useState('')
  const [nova, setNova] = useState('')

  async function salvar() {
    if (nova.length < 4) { toast.error('A senha nova deve ter ao menos 4 caracteres.'); return }
    try { await mut.mutateAsync({ senha_atual: atual, senha_nova: nova }); toast.success('Senha mestra atualizada.'); setOpen(false); setAtual(''); setNova('') }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao alterar senha.') }
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button variant="outline"><KeyRound size={16} /> Senha mestra</Button></DialogTrigger>
      <DialogContent>
        <DialogHeader><DialogTitle>{tem ? 'Alterar senha mestra' : 'Definir senha mestra'}</DialogTitle></DialogHeader>
        <div className="space-y-4">
          {tem && <Field label="Senha atual" required><Input type="password" value={atual} onChange={(e) => setAtual(e.target.value)} /></Field>}
          <Field label="Senha nova" required><Input type="password" value={nova} onChange={(e) => setNova(e.target.value)} /></Field>
        </div>
        <DialogFooter><DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose><Button loading={mut.isPending} onClick={salvar}>Salvar</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

function TesteEmailDialog({ empresaId }: { empresaId: number }) {
  const mut = useTestarEmail(empresaId)
  const [open, setOpen] = useState(false)
  const [to, setTo] = useState('')

  async function enviar() {
    if (!to) { toast.error('Informe o destinatário.'); return }
    try { await mut.mutateAsync({ to }); toast.success('E-mail de teste enviado.'); setOpen(false); setTo('') }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Falha ao enviar.') }
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button variant="outline"><Mail size={16} /> Testar e-mail</Button></DialogTrigger>
      <DialogContent>
        <DialogHeader><DialogTitle>Testar configuração de e-mail</DialogTitle></DialogHeader>
        <p className="text-sm text-muted-foreground">Salve as configurações de e-mail antes de testar. Enviaremos um e-mail de teste ao destinatário.</p>
        <Field label="Enviar para" required><Input type="email" value={to} onChange={(e) => setTo(e.target.value)} /></Field>
        <DialogFooter><DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose><Button loading={mut.isPending} onClick={enviar}>Enviar teste</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
