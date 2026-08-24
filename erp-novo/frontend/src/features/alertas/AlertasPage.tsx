import { useState } from 'react'
import { ShieldAlert, UserCheck, CheckCircle2, XCircle, Repeat, Inbox, Search, AlertTriangle } from 'lucide-react'
import {
  Button, Badge, Card, CardContent, StatCard, AsyncState, Can, Textarea, Field,
  FormDialog, toast, Select, SelectTrigger, SelectValue, SelectContent, SelectItem,
} from '@/components/ui'
import { data as fmtData } from '@/lib/format'
import {
  useAlertas, useAssumirAlerta, useEncerrarAlerta, type Alerta, type Filtros,
} from './api'

const ROTULO_ORIGEM: Record<string, string> = {
  comodato_giro: 'Giro de comodato',
  comodato_vencimento: 'Vencimento de comodato',
}

/**
 * Central de alertas — a fila de averiguação.
 *
 * Abre mostrando só o que ainda pede ação: uma central que lista resolvido
 * junto com aberto obriga a filtrar antes de trabalhar.
 */
export function AlertasPage() {
  const [filtros, setFiltros] = useState<Filtros>({})
  const { data, isLoading, error } = useAlertas(filtros)
  const assumir = useAssumirAlerta()
  const [aEncerrar, setAEncerrar] = useState<{ alerta: Alerta; situacao: 'RESOLVIDO' | 'IGNORADO' } | null>(null)

  const resumo = data?.resumo
  const alertas = data?.data ?? []

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-2xl font-semibold">Central de alertas</h1>
        <p className="text-sm text-muted-foreground">
          O que a equipe precisa averiguar — ordenado por risco.
        </p>
      </div>

      <div className="grid gap-3 sm:grid-cols-4">
        <StatCard titulo="Abertos" valor={resumo?.abertos ?? 0} icon={Inbox} accent="primary" />
        <StatCard titulo="Em análise" valor={resumo?.em_analise ?? 0} icon={Search} accent="neutral" />
        <StatCard titulo="Severidade alta" valor={resumo?.alta ?? 0} icon={AlertTriangle} accent="destructive" />
        <StatCard titulo="Severidade média" valor={resumo?.media ?? 0} icon={ShieldAlert} accent="lime" />
      </div>

      <div className="flex flex-wrap gap-2">
        <Filtro
          valor={filtros.origem ?? 'todas'} placeholder="Origem"
          opcoes={[['todas', 'Todas as origens'], ...Object.entries(ROTULO_ORIGEM)]}
          onChange={(v) => setFiltros((f) => ({ ...f, origem: v === 'todas' ? undefined : v }))}
        />
        <Filtro
          valor={filtros.severidade ?? 'todas'} placeholder="Severidade"
          opcoes={[['todas', 'Todas'], ['ALTA', 'Alta'], ['MEDIA', 'Média'], ['BAIXA', 'Baixa']]}
          onChange={(v) => setFiltros((f) => ({ ...f, severidade: v === 'todas' ? undefined : v }))}
        />
        <Filtro
          valor={filtros.situacao ?? 'pendentes'} placeholder="Situação"
          opcoes={[
            ['pendentes', 'Pendentes'], ['ABERTO', 'Abertos'], ['EM_ANALISE', 'Em análise'],
            ['RESOLVIDO', 'Resolvidos'], ['IGNORADO', 'Ignorados'],
          ]}
          onChange={(v) => setFiltros((f) => ({ ...f, situacao: v === 'pendentes' ? undefined : v }))}
        />
      </div>

      <AsyncState
        loading={isLoading} error={error} empty={alertas.length === 0}
        emptyIcon={<ShieldAlert />} emptyTitle="Nenhum alerta pendente"
        emptyDescription="Tudo o que precisava de averiguação já foi tratado."
      >
        <div className="space-y-2">
          {alertas.map((a) => (
            <AlertaCard
              key={a.id} alerta={a}
              onAssumir={() => assumir.mutate(a.id)}
              onEncerrar={(situacao) => setAEncerrar({ alerta: a, situacao })}
            />
          ))}
        </div>
      </AsyncState>

      <EncerrarDialog
        pedido={aEncerrar}
        onOpenChange={(v) => !v && setAEncerrar(null)}
      />
    </div>
  )
}

function Filtro({ valor, placeholder, opcoes, onChange }: {
  valor: string; placeholder: string
  opcoes: Array<[string, string]>; onChange: (v: string) => void
}) {
  return (
    <Select value={valor} onValueChange={onChange}>
      <SelectTrigger className="w-52"><SelectValue placeholder={placeholder} /></SelectTrigger>
      <SelectContent>
        {opcoes.map(([v, rotulo]) => <SelectItem key={v} value={v}>{rotulo}</SelectItem>)}
      </SelectContent>
    </Select>
  )
}

function AlertaCard({ alerta, onAssumir, onEncerrar }: {
  alerta: Alerta
  onAssumir: () => void
  onEncerrar: (situacao: 'RESOLVIDO' | 'IGNORADO') => void
}) {
  const pendente = alerta.situacao === 'ABERTO' || alerta.situacao === 'EM_ANALISE'
  const d = alerta.dados ?? {}

  return (
    <Card>
      <CardContent className="flex flex-col gap-3 p-4 sm:flex-row sm:items-start">
        <div className="min-w-0 flex-1 space-y-2">
          <div className="flex flex-wrap items-center gap-2">
            <SeveridadeBadge severidade={alerta.severidade} />
            <Badge variant="outline">{ROTULO_ORIGEM[alerta.origem] ?? alerta.origem}</Badge>
            {alerta.situacao === 'EM_ANALISE' && (
              <Badge variant="secondary">
                Em análise{alerta.responsavel ? ` · ${alerta.responsavel.name}` : ''}
              </Badge>
            )}
            {!pendente && (
              <Badge variant={alerta.situacao === 'RESOLVIDO' ? 'success' : 'secondary'}>
                {alerta.situacao === 'RESOLVIDO' ? 'Resolvido' : 'Ignorado'}
              </Badge>
            )}
            {alerta.ocorrencias > 1 && (
              // Um alerta que volta há 12 rodadas diz algo diferente de um que
              // apareceu ontem.
              <Badge variant="warning" title="Rodadas em que o problema persistiu">
                <Repeat size={12} /> {alerta.ocorrencias}ª vez
              </Badge>
            )}
          </div>

          <div className="font-medium">{alerta.titulo}</div>
          {alerta.descricao && <p className="text-sm text-muted-foreground">{alerta.descricao}</p>}

          {alerta.origem === 'comodato_giro' && (
            <div className="flex flex-wrap gap-x-5 gap-y-1 text-xs text-muted-foreground">
              <Metrica rotulo="Em posse" valor={d.em_posse} />
              <Metrica rotulo="Giro" valor={d.giro} sufixo="x" />
              {d.baseline_giro != null && <Metrica rotulo="Habitual" valor={d.baseline_giro} sufixo="x" />}
              <Metrica rotulo="Comprou" valor={d.comprado_janela} sufixo={` em ${d.dias_janela}d`} />
              {d.dias_sem_compra != null && <Metrica rotulo="Sem comprar" valor={d.dias_sem_compra} sufixo=" dias" />}
            </div>
          )}

          {alerta.resolucao && (
            <p className="rounded-md bg-secondary/50 px-2 py-1 text-xs">{alerta.resolucao}</p>
          )}

          <div className="text-xs text-muted-foreground">
            Detectado em {fmtData(alerta.created_at)}
          </div>
        </div>

        {pendente && (
          <Can permission="alerta.triar">
            <div className="flex shrink-0 flex-wrap gap-1">
              {alerta.situacao === 'ABERTO' && (
                <Button variant="outline" size="sm" onClick={onAssumir}>
                  <UserCheck size={15} /> Assumir
                </Button>
              )}
              <Button variant="ghost" size="sm" onClick={() => onEncerrar('RESOLVIDO')}>
                <CheckCircle2 size={15} /> Resolver
              </Button>
              <Button variant="ghost" size="sm" onClick={() => onEncerrar('IGNORADO')}>
                <XCircle size={15} /> Ignorar
              </Button>
            </div>
          </Can>
        )}
      </CardContent>
    </Card>
  )
}

function Metrica({ rotulo, valor, sufixo = '' }: { rotulo: string; valor: unknown; sufixo?: string }) {
  if (valor == null) return null
  return (
    <span>
      {rotulo}: <strong className="tabular-nums text-foreground">{Number(valor)}{sufixo}</strong>
    </span>
  )
}

function SeveridadeBadge({ severidade }: { severidade: string }) {
  if (severidade === 'ALTA') return <Badge variant="destructive">Alta</Badge>
  if (severidade === 'MEDIA') return <Badge variant="warning">Média</Badge>
  return <Badge variant="secondary">Baixa</Badge>
}

/**
 * O desfecho exige justificativa.
 *
 * Um alerta de suspeita de desvio patrimonial fechado sem explicação não deixa
 * nada para a próxima pessoa que perguntar por que aquele cliente saiu da fila.
 */
function EncerrarDialog({ pedido, onOpenChange }: {
  pedido: { alerta: Alerta; situacao: 'RESOLVIDO' | 'IGNORADO' } | null
  onOpenChange: (v: boolean) => void
}) {
  const encerrar = useEncerrarAlerta()
  const [texto, setTexto] = useState('')

  async function confirmar() {
    if (!pedido || texto.trim().length === 0) return
    try {
      await encerrar.mutateAsync({ id: pedido.alerta.id, situacao: pedido.situacao, resolucao: texto })
      toast.success(pedido.situacao === 'RESOLVIDO' ? 'Alerta resolvido.' : 'Alerta ignorado.')
      setTexto('')
      onOpenChange(false)
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Erro ao encerrar.')
    }
  }

  const ignorando = pedido?.situacao === 'IGNORADO'

  return (
    <FormDialog
      open={pedido !== null}
      onOpenChange={(v) => { if (!v) setTexto(''); onOpenChange(v) }}
      title={ignorando ? 'Ignorar alerta' : 'Resolver alerta'}
      description={pedido?.alerta.titulo}
      confirmLabel={ignorando ? 'Ignorar' : 'Resolver'}
      loading={encerrar.isPending}
      confirmDisabled={texto.trim().length === 0}
      onConfirm={confirmar}
    >
      <Field label={ignorando ? 'Por que não é um problema?' : 'O que foi feito?'} required>
        <Textarea
          rows={3} value={texto} onChange={(e) => setTexto(e.target.value)} autoFocus
          placeholder={ignorando
            ? 'Ex.: cliente em férias coletivas até março, consumo retoma depois.'
            : 'Ex.: visitamos o cliente, recolhemos 12 vasilhames ociosos.'}
        />
      </Field>
    </FormDialog>
  )
}
