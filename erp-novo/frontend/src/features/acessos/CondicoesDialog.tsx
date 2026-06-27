import { useState } from 'react'
import { Trash2, Plus } from 'lucide-react'
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, Button, Input, Field, Badge,
  Select, SelectTrigger, SelectValue, SelectContent, SelectItem, toast,
} from '@/components/ui'
import {
  useCondicoes, useCriarCondicao, useExcluirCondicao, type CondicaoTipo, type Papel,
} from './api'

const TIPOS: { v: CondicaoTipo; l: string; hint: string }[] = [
  { v: 'limite', l: 'Limite de valor', hint: 'Permite a ação só até um valor máximo do recurso.' },
  { v: 'ownership', l: 'Apenas próprios', hint: 'Permite só sobre recursos do próprio usuário.' },
  { v: 'horario', l: 'Janela de horário', hint: 'Permite a ação só dentro de um intervalo do dia.' },
]

/**
 * Condições ABAC (A4) de um perfil: amarram uma regra de atributo (limite,
 * ownership ou horário) a uma das permissões do papel. A autoridade é o backend
 * (PolicyEvaluator) — aqui só administramos.
 */
export function CondicoesDialog({ papel, open, onOpenChange }: {
  papel: Papel | null; open: boolean; onOpenChange: (v: boolean) => void
}) {
  const { data: condicoes, isLoading } = useCondicoes(papel?.id ?? null)
  const criar = useCriarCondicao()
  const excluir = useExcluirCondicao()

  const [permissao, setPermissao] = useState('')
  const [tipo, setTipo] = useState<CondicaoTipo>('limite')
  const [params, setParams] = useState<Record<string, string>>({})

  if (!papel) return null

  function paramsDoTipo(): Record<string, unknown> {
    if (tipo === 'limite') return { campo: params.campo || 'valor', valor_max: Number(params.valor_max || 0) }
    if (tipo === 'ownership') return { campo_dono: params.campo_dono || 'user_id' }
    return { de: params.de || '08:00', ate: params.ate || '18:00' }
  }

  async function adicionar() {
    if (!permissao) { toast.error('Escolha a permissão.'); return }
    try {
      await criar.mutateAsync({ papelId: papel!.id, data: { permissao, tipo, parametros: paramsDoTipo() } })
      toast.success('Condição adicionada.')
      setPermissao(''); setParams({})
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Erro ao adicionar condição.')
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>Condições — {papel.nome}</DialogTitle>
          <p className="text-sm text-muted-foreground">Regras de atributo (ABAC) aplicadas às permissões deste perfil.</p>
        </DialogHeader>

        {/* Lista das condições existentes */}
        <div className="space-y-2 max-h-56 overflow-y-auto">
          {isLoading && <p className="text-sm text-muted-foreground">Carregando…</p>}
          {!isLoading && (condicoes ?? []).length === 0 && (
            <p className="text-sm text-muted-foreground">Nenhuma condição — o perfil exerce as permissões sem restrição de atributo.</p>
          )}
          {(condicoes ?? []).map((c) => (
            <div key={c.id} className="flex items-center justify-between rounded-md border border-border px-3 py-2 text-sm">
              <div className="flex items-center gap-2">
                <Badge variant="secondary">{c.permissao}</Badge>
                <span className="font-medium">{TIPOS.find((t) => t.v === c.tipo)?.l ?? c.tipo}</span>
                <span className="text-muted-foreground">{c.parametros ? JSON.stringify(c.parametros) : ''}</span>
              </div>
              <Button variant="ghost" size="icon" aria-label="Remover" onClick={() => excluir.mutate({ papelId: papel!.id, id: c.id })}>
                <Trash2 size={15} />
              </Button>
            </div>
          ))}
        </div>

        {/* Nova condição */}
        <div className="rounded-md border border-dashed border-border p-3 space-y-3">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <Field label="Permissão">
              <Select value={permissao} onValueChange={setPermissao}>
                <SelectTrigger><SelectValue placeholder="Escolha…" /></SelectTrigger>
                <SelectContent>
                  {papel.permissoes.map((p) => <SelectItem key={p} value={p}>{p}</SelectItem>)}
                </SelectContent>
              </Select>
            </Field>
            <Field label="Tipo">
              <Select value={tipo} onValueChange={(v) => { setTipo(v as CondicaoTipo); setParams({}) }}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  {TIPOS.map((t) => <SelectItem key={t.v} value={t.v}>{t.l}</SelectItem>)}
                </SelectContent>
              </Select>
            </Field>
          </div>

          {tipo === 'limite' && (
            <div className="grid grid-cols-2 gap-3">
              <Field label="Campo do valor"><Input placeholder="valor" value={params.campo ?? ''} onChange={(e) => setParams((p) => ({ ...p, campo: e.target.value }))} /></Field>
              <Field label="Valor máximo"><Input type="number" value={params.valor_max ?? ''} onChange={(e) => setParams((p) => ({ ...p, valor_max: e.target.value }))} /></Field>
            </div>
          )}
          {tipo === 'ownership' && (
            <Field label="Campo do dono"><Input placeholder="user_id" value={params.campo_dono ?? ''} onChange={(e) => setParams((p) => ({ ...p, campo_dono: e.target.value }))} /></Field>
          )}
          {tipo === 'horario' && (
            <div className="grid grid-cols-2 gap-3">
              <Field label="De"><Input type="time" value={params.de ?? ''} onChange={(e) => setParams((p) => ({ ...p, de: e.target.value }))} /></Field>
              <Field label="Até"><Input type="time" value={params.ate ?? ''} onChange={(e) => setParams((p) => ({ ...p, ate: e.target.value }))} /></Field>
            </div>
          )}

          <p className="text-xs text-muted-foreground">{TIPOS.find((t) => t.v === tipo)?.hint}</p>
          <Button size="sm" loading={criar.isPending} onClick={adicionar}><Plus size={15} /> Adicionar condição</Button>
        </div>
      </DialogContent>
    </Dialog>
  )
}
