import { useMemo, useState } from 'react'
import {
  DatabaseZap, Plus, PlugZap, Stethoscope, Building2, PlayCircle,
  CheckCircle2, AlertTriangle, Download, FlaskConical,
} from 'lucide-react'
import {
  Button, Input, Badge, Field, Card, CardHeader, CardTitle, CardDescription,
  CardContent, Select, SelectTrigger, SelectValue, SelectContent, SelectItem,
  FormDialog, ResourceList, AsyncState, toast, type Column,
} from '@/components/ui'
import {
  useSaMigracoes, useSaMigracao, useSaMigracaoAcoes, saDescartesCsvUrl,
  type SaMigracao, type SaMapaEmpresa, type SaResultadoEtapa,
} from './api'

/**
 * Ferramenta de migração de sistemas antigos.
 *
 * É um ASSISTENTE, não um botão: migrar um ERP inteiro tem uma decisão que só o
 * humano pode tomar (a qual tenant cada empresa do sistema antigo corresponde) e
 * um volume que não cabe numa requisição. Por isso os passos são explícitos —
 * conectar, diagnosticar, mapear, executar — com o diagnóstico ANTES de gravar
 * qualquer coisa.
 */

const ORIGENS = [
  { valor: 'erp_pg', rotulo: 'ERP antigo (PostgreSQL)', porta: 5432 },
  { valor: 'app_mysql', rotulo: 'App do consumidor (MySQL)', porta: 3306 },
  { valor: 'monitora_mysql', rotulo: 'Rastreamento/Monitora (MySQL)', porta: 3306 },
]

const STATUS: Record<string, { rotulo: string; variant: any }> = {
  pendente: { rotulo: 'Pendente', variant: 'secondary' },
  diagnosticando: { rotulo: 'Diagnosticando', variant: 'warning' },
  aguardando_mapeamento: { rotulo: 'Aguardando mapeamento', variant: 'warning' },
  migrando: { rotulo: 'Migrando', variant: 'warning' },
  concluida: { rotulo: 'Concluída', variant: 'success' },
  falhou: { rotulo: 'Falhou', variant: 'destructive' },
}

export function SaMigracaoPage() {
  const { data: migracoes, isLoading } = useSaMigracoes()
  const [selecionada, setSelecionada] = useState<number | null>(null)
  const [novaAberta, setNovaAberta] = useState(false)

  const columns: Column<SaMigracao>[] = [
    {
      key: 'descricao', header: 'Migração',
      cell: (m) => (
        <button className="text-left font-medium hover:underline" onClick={() => setSelecionada(m.id)}>
          {m.descricao}
        </button>
      ),
    },
    {
      key: 'origem', header: 'Origem',
      cell: (m) => ORIGENS.find((o) => o.valor === m.origem_tipo)?.rotulo ?? m.origem_tipo,
    },
    {
      key: 'status', header: 'Status',
      cell: (m) => {
        const s = STATUS[m.status] ?? { rotulo: m.status, variant: 'secondary' }
        return (
          <div className="flex items-center gap-2">
            <Badge variant={s.variant}>{s.rotulo}</Badge>
            {m.status === 'migrando' && (
              <span className="text-xs text-muted-foreground">
                {m.progresso}%{m.etapa_atual ? ` · ${m.etapa_atual}` : ''}
              </span>
            )}
          </div>
        )
      },
    },
    {
      key: 'quando', header: 'Criada em',
      cell: (m) => new Date(m.created_at).toLocaleString('pt-BR'),
    },
    {
      key: 'acoes', header: '', align: 'right',
      cell: (m) => <Button variant="outline" size="sm" onClick={() => setSelecionada(m.id)}>Abrir</Button>,
    },
  ]

  return (
    <>
      <ResourceList
        title="Migração de sistemas antigos"
        subtitle="Traz os dados de um ERP, app ou rastreador legado para dentro da plataforma"
        action={<Button onClick={() => setNovaAberta(true)}><Plus size={16} /> Nova migração</Button>}
        columns={columns}
        rows={migracoes}
        loading={isLoading}
        rowKey={(m) => m.id}
        emptyIcon={<DatabaseZap />}
        emptyTitle="Nenhuma migração"
        emptyDescription="Comece conectando o banco do sistema antigo."
      />

      <NovaMigracaoDialog
        open={novaAberta}
        onOpenChange={setNovaAberta}
        onCriada={(id) => { setNovaAberta(false); setSelecionada(id) }}
      />

      {selecionada != null && (
        <AssistenteMigracao id={selecionada} onFechar={() => setSelecionada(null)} />
      )}
    </>
  )
}

// ── Passo 1: origem e credenciais ────────────────────────────────────────────

function NovaMigracaoDialog({
  open, onOpenChange, onCriada,
}: { open: boolean; onOpenChange: (v: boolean) => void; onCriada: (id: number) => void }) {
  const { criar } = useSaMigracaoAcoes()
  const [form, setForm] = useState<Record<string, any>>({ origem_tipo: 'erp_pg', port: 5432 })

  const origem = ORIGENS.find((o) => o.valor === form.origem_tipo)

  async function salvar() {
    try {
      const m = await criar.mutateAsync({
        descricao: form.descricao,
        origem_tipo: form.origem_tipo,
        config: {
          host: form.host,
          port: Number(form.port) || origem?.porta,
          database: form.database,
          username: form.username,
          password: form.password ?? '',
          schema: form.schema || undefined,
        },
      })
      toast.success('Migração criada. Agora teste a conexão.')
      onCriada(m.id)
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Não foi possível criar.')
    }
  }

  return (
    <FormDialog
      open={open} onOpenChange={onOpenChange}
      title="Nova migração"
      description="Informe o banco do sistema antigo. Nada é gravado até você revisar o diagnóstico."
      loading={criar.isPending} onConfirm={salvar}
    >
      <Field label="Descrição">
        <Input
          value={form.descricao ?? ''}
          onChange={(e) => setForm({ ...form, descricao: e.target.value })}
          placeholder="Ex.: ERP da Revenda Guarapuava"
        />
      </Field>

      <Field label="Sistema de origem">
        <Select
          value={form.origem_tipo}
          onValueChange={(v) => setForm({ ...form, origem_tipo: v, port: ORIGENS.find((o) => o.valor === v)?.porta })}
        >
          <SelectTrigger><SelectValue /></SelectTrigger>
          <SelectContent>
            {ORIGENS.map((o) => <SelectItem key={o.valor} value={o.valor}>{o.rotulo}</SelectItem>)}
          </SelectContent>
        </Select>
      </Field>

      <div className="grid grid-cols-3 gap-3">
        <div className="col-span-2">
          <Field label="Servidor">
            <Input value={form.host ?? ''} onChange={(e) => setForm({ ...form, host: e.target.value })} placeholder="127.0.0.1" />
          </Field>
        </div>
        <Field label="Porta">
          <Input type="number" value={form.port ?? ''} onChange={(e) => setForm({ ...form, port: e.target.value })} />
        </Field>
      </div>

      <Field label="Banco de dados">
        <Input value={form.database ?? ''} onChange={(e) => setForm({ ...form, database: e.target.value })} />
      </Field>

      <div className="grid grid-cols-2 gap-3">
        <Field label="Usuário">
          <Input value={form.username ?? ''} onChange={(e) => setForm({ ...form, username: e.target.value })} />
        </Field>
        <Field label="Senha">
          <Input type="password" value={form.password ?? ''} onChange={(e) => setForm({ ...form, password: e.target.value })} />
        </Field>
      </div>

      {form.origem_tipo === 'erp_pg' && (
        <Field label="Schema (opcional)" hint="Deixe vazio para usar o schema padrão (public).">
          <Input value={form.schema ?? ''} onChange={(e) => setForm({ ...form, schema: e.target.value })} placeholder="public" />
        </Field>
      )}
    </FormDialog>
  )
}

// ── Passos 2 a 5: diagnóstico, mapeamento, execução, conferência ─────────────

function AssistenteMigracao({ id, onFechar }: { id: number; onFechar: () => void }) {
  const { data: migracao, isLoading } = useSaMigracao(id)
  const { conectar, diagnosticar, salvarMapa, simular, executar, validar } = useSaMigracaoAcoes()
  const [mapa, setMapa] = useState<Record<number, SaMapaEmpresa>>({})
  const [validacao, setValidacao] = useState<any[] | null>(null)

  const empresas = migracao?.diagnostico?.empresas ?? []

  // Sugestão do backend como ponto de partida — o usuário confirma ou muda.
  const mapaEfetivo = useMemo(() => {
    const base: Record<number, SaMapaEmpresa> = {}
    for (const e of empresas) {
      base[e.id_origem] = mapa[e.id_origem] ?? {
        id_origem: e.id_origem,
        acao: e.acao_sugerida,
        empresa_id: e.tenant_sugerido,
      }
    }
    return base
  }, [empresas, mapa])

  async function onConectar() {
    try {
      const r = await conectar.mutateAsync(id)
      toast.success(`Conectado. ${r.tabelas?.length ?? 0} tabela(s) encontrada(s).`)
    } catch (e: any) {
      toast.error(e?.response?.data?.erro ?? 'Falha ao conectar.')
    }
  }

  async function onDiagnosticar() {
    try {
      await diagnosticar.mutateAsync(id)
      toast.success('Diagnóstico concluído. Nada foi gravado ainda.')
    } catch (e: any) {
      toast.error(e?.response?.data?.erro ?? 'Falha no diagnóstico.')
    }
  }

  async function onExecutar() {
    try {
      await salvarMapa.mutateAsync({ id, mapa: Object.values(mapaEfetivo) })
      await executar.mutateAsync(id)
      toast.success('Migração iniciada. O progresso aparece nesta tela.')
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Não foi possível iniciar.')
    }
  }

  async function onValidar() {
    try {
      setValidacao(await validar.mutateAsync(id))
    } catch {
      toast.error('Falha ao validar.')
    }
  }

  return (
    <FormDialog
      open onOpenChange={(v) => { if (!v) onFechar() }}
      title={migracao?.descricao ?? 'Migração'}
      description="Conectar → diagnosticar → mapear empresas → migrar → conferir."
      confirmLabel="Fechar"
      onConfirm={onFechar}
      widthClass="max-w-3xl"
    >
      <AsyncState loading={isLoading} empty={!migracao}>
        {migracao && (
          <div className="space-y-4">
            {/* Passo 1 e 2 */}
            <Card>
              <CardHeader>
                <CardTitle className="text-base">1. Conexão e diagnóstico</CardTitle>
                <CardDescription>
                  O diagnóstico apenas lê a origem e conta o que existe. Nada é gravado.
                </CardDescription>
              </CardHeader>
              <CardContent className="flex flex-wrap gap-2">
                <Button variant="outline" onClick={onConectar} disabled={conectar.isPending}>
                  <PlugZap size={16} /> Testar conexão
                </Button>
                <Button onClick={onDiagnosticar} disabled={diagnosticar.isPending}>
                  <Stethoscope size={16} /> {diagnosticar.isPending ? 'Analisando…' : 'Diagnosticar'}
                </Button>
              </CardContent>
            </Card>

            {migracao.diagnostico && (
              <>
                <Card>
                  <CardHeader>
                    <CardTitle className="text-base">O que foi encontrado</CardTitle>
                  </CardHeader>
                  <CardContent className="space-y-3">
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                      {Object.entries(migracao.diagnostico.contagens).map(([rotulo, n]) => (
                        <div key={rotulo} className="rounded-md border p-2">
                          <div className="text-xs text-muted-foreground">{rotulo}</div>
                          <div className="text-lg font-semibold tabular-nums">{n.toLocaleString('pt-BR')}</div>
                        </div>
                      ))}
                    </div>

                    {migracao.diagnostico.alertas.map((a, i) => (
                      <div key={i} className="flex items-start gap-2 rounded-md border border-amber-500/40 bg-amber-500/10 p-2 text-sm">
                        <AlertTriangle size={16} className="mt-0.5 shrink-0 text-amber-600" />
                        <span>{a.mensagem}</span>
                      </div>
                    ))}
                  </CardContent>
                </Card>

                {/* Passo 3 — a decisão humana */}
                {empresas.length > 0 && (
                  <Card>
                    <CardHeader>
                      <CardTitle className="text-base">
                        <Building2 size={16} className="mr-1 inline" /> 2. Empresas do sistema antigo
                      </CardTitle>
                      <CardDescription>
                        Diga a que empresa da plataforma cada uma corresponde. Sem correspondente,
                        o padrão é criar uma nova — assim nenhum dado fica para trás.
                      </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2">
                      {empresas.map((e) => {
                        const atual = mapaEfetivo[e.id_origem]
                        return (
                          <div key={e.id_origem} className="flex flex-wrap items-center gap-2 rounded-md border p-2">
                            <div className="min-w-40 flex-1">
                              <div className="text-sm font-medium">{e.nome}</div>
                              {e.cnpj && <div className="text-xs text-muted-foreground">CNPJ {e.cnpj}</div>}
                            </div>
                            <Select
                              value={atual.acao}
                              onValueChange={(v) => setMapa({
                                ...mapa,
                                [e.id_origem]: { ...atual, acao: v as SaMapaEmpresa['acao'] },
                              })}
                            >
                              <SelectTrigger className="w-44"><SelectValue /></SelectTrigger>
                              <SelectContent>
                                <SelectItem value="mapear">Usar empresa existente</SelectItem>
                                <SelectItem value="criar">Criar nova empresa</SelectItem>
                                <SelectItem value="ignorar">Ignorar</SelectItem>
                              </SelectContent>
                            </Select>
                            {atual.acao === 'mapear' && (
                              <Input
                                className="w-32"
                                type="number"
                                value={atual.empresa_id ?? ''}
                                onChange={(ev) => setMapa({
                                  ...mapa,
                                  [e.id_origem]: { ...atual, empresa_id: Number(ev.target.value) },
                                })}
                                placeholder="ID empresa"
                              />
                            )}
                          </div>
                        )
                      })}
                    </CardContent>
                  </Card>
                )}

                {/* Passo 4 */}
                <Card>
                  <CardHeader>
                    <CardTitle className="text-base">3. Migrar</CardTitle>
                    <CardDescription>
                      A carga roda em segundo plano — pode levar horas em bases grandes.
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="flex flex-wrap items-center gap-2">
                    <Button variant="outline" onClick={() => simular.mutate(id)} disabled={simular.isPending}>
                      <FlaskConical size={16} /> Simular (não grava)
                    </Button>
                    <Button onClick={onExecutar} disabled={executar.isPending || migracao.status === 'migrando'}>
                      <PlayCircle size={16} /> {migracao.status === 'migrando' ? 'Migrando…' : 'Iniciar migração'}
                    </Button>

                    {migracao.status === 'migrando' && (
                      <div className="w-full">
                        <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                          <div
                            className="h-full bg-primary transition-all"
                            style={{ width: `${migracao.progresso}%` }}
                          />
                        </div>
                        <div className="mt-1 text-xs text-muted-foreground">
                          {migracao.progresso}% {migracao.etapa_atual ? `· ${migracao.etapa_atual}` : ''}
                        </div>
                      </div>
                    )}
                  </CardContent>
                </Card>
              </>
            )}

            {/* Passo 5 — resultado e conferência */}
            {migracao.resultado && (
              <Card>
                <CardHeader>
                  <CardTitle className="text-base">
                    <CheckCircle2 size={16} className="mr-1 inline text-emerald-600" /> Resultado
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  <TabelaResultado resultado={migracao.resultado} />

                  <div className="flex flex-wrap gap-2">
                    <Button variant="outline" onClick={onValidar} disabled={validar.isPending}>
                      Conferir contagens
                    </Button>
                    {(migracao.descartes_count ?? 0) > 0 && (
                      <Button variant="outline" asChild>
                        <a href={saDescartesCsvUrl(id)}>
                          <Download size={16} /> Baixar o que não entrou ({migracao.descartes_count})
                        </a>
                      </Button>
                    )}
                  </div>

                  {validacao && (
                    <div className="space-y-1">
                      {validacao.map((v, i) => (
                        <div key={i} className="flex items-center gap-2 text-sm">
                          <Badge variant={v.ok ? 'success' : 'destructive'}>{v.ok ? 'OK' : 'Diverge'}</Badge>
                          <span className="text-muted-foreground">{v.resumo}</span>
                        </div>
                      ))}
                    </div>
                  )}
                </CardContent>
              </Card>
            )}

            {migracao.erro && (
              <div className="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm">
                <div className="font-medium">A migração falhou</div>
                <div className="mt-1 text-muted-foreground">{migracao.erro}</div>
              </div>
            )}
          </div>
        )}
      </AsyncState>
    </FormDialog>
  )
}

function TabelaResultado({ resultado }: { resultado: Record<string, SaResultadoEtapa> }) {
  const linhas = Object.entries(resultado)
  const total = linhas.reduce(
    (acc, [, r]) => ({
      lidos: acc.lidos + (r.lidos ?? 0),
      gravados: acc.gravados + (r.gravados ?? 0),
      pulados: acc.pulados + (r.pulados ?? 0),
    }),
    { lidos: 0, gravados: 0, pulados: 0 },
  )

  return (
    <div className="overflow-x-auto">
      <table className="w-full text-sm">
        <thead className="text-left text-xs text-muted-foreground">
          <tr>
            <th className="py-1">Etapa</th>
            <th className="py-1 text-right">Lidos</th>
            <th className="py-1 text-right">Migrados</th>
            <th className="py-1 text-right">Não migrados</th>
          </tr>
        </thead>
        <tbody>
          {linhas.map(([nome, r]) => (
            <tr key={nome} className="border-t">
              <td className="py-1">
                {nome}
                {r.erro && <div className="text-xs text-destructive">{r.erro}</div>}
                {r.avisos?.map((a, i) => (
                  <div key={i} className="text-xs text-muted-foreground">{a}</div>
                ))}
              </td>
              <td className="py-1 text-right tabular-nums">{(r.lidos ?? 0).toLocaleString('pt-BR')}</td>
              <td className="py-1 text-right tabular-nums">{(r.gravados ?? 0).toLocaleString('pt-BR')}</td>
              <td className="py-1 text-right tabular-nums">
                {r.pulados ? <span className="text-amber-600">{r.pulados.toLocaleString('pt-BR')}</span> : '—'}
              </td>
            </tr>
          ))}
          <tr className="border-t font-medium">
            <td className="py-1">Total</td>
            <td className="py-1 text-right tabular-nums">{total.lidos.toLocaleString('pt-BR')}</td>
            <td className="py-1 text-right tabular-nums">{total.gravados.toLocaleString('pt-BR')}</td>
            <td className="py-1 text-right tabular-nums">{total.pulados.toLocaleString('pt-BR')}</td>
          </tr>
        </tbody>
      </table>
    </div>
  )
}
