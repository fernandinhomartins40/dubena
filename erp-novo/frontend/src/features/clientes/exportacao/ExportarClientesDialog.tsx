import { useMemo, useState } from 'react'
import { FileSpreadsheet, FileText, FileType, ShieldAlert, Loader2 } from 'lucide-react'
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter,
  Button, Field, Input, Checkbox, CheckboxField, AsyncMultiSelect, Select,
  SelectTrigger, SelectValue, SelectContent, SelectItem, Tabs, TabsList,
  TabsTrigger, TabsContent, Badge, toast, type Option,
} from '@/components/ui'
import { cn } from '@/lib/cn'
import {
  useOpcoesExportacao, usePreviaExportacao, baixarExportacao,
  type FormatoExportacao, type FiltrosExportacao, type CampoExportavel,
} from './api'

interface Props {
  open: boolean
  onOpenChange: (v: boolean) => void
  /** Situação da aba em que a tela está — vira o valor inicial do filtro. */
  situacaoInicial?: 'ativos' | 'inativos' | 'todos'
}

const FORMATOS: { valor: FormatoExportacao; rotulo: string; hint: string; icone: typeof FileText }[] = [
  { valor: 'xlsx', rotulo: 'Excel', hint: 'Ordena e soma na planilha', icone: FileSpreadsheet },
  { valor: 'csv', rotulo: 'CSV', hint: 'Importável em qualquer sistema', icone: FileType },
  { valor: 'pdf', rotulo: 'PDF', hint: 'Para leitura e impressão', icone: FileText },
]

const MESES = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
  'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro']

/** Flags de papel/classificação que viram um "Sim / Não / Tanto faz". */
const FLAGS: { chave: keyof FiltrosExportacao; rotulo: string }[] = [
  { chave: 'cliente', rotulo: 'É cliente' },
  { chave: 'fornecedor', rotulo: 'É fornecedor' },
  { chave: 'transportador', rotulo: 'É transportador' },
  { chave: 'convenio', rotulo: 'Tem convênio' },
  { chave: 'convenio_ativo', rotulo: 'Convênio ativo' },
  { chave: 'gasdopovo', rotulo: 'Gás do Povo' },
  { chave: 'nfemite', rotulo: 'Emite NF-e' },
  { chave: 'simples', rotulo: 'Simples Nacional' },
]

/**
 * Modal de exportação personalizada da base de clientes.
 *
 * Duas decisões que moldam a tela:
 *
 * 1. O catálogo de colunas vem do backend, não daqui — é o mesmo array que
 *    autoriza a exportação, então não há como a tela oferecer uma coluna que o
 *    servidor recusa (nem o contrário).
 *
 * 2. Campo sensível (CPF, RG, nascimento, telefone, coordenada) NUNCA vem
 *    pré-marcado, e a contagem deles fica visível no rodapé. A exportação
 *    inteira já é privilégio do dono; o aviso existe para que levar o CPF de
 *    55 mil pessoas seja um ato consciente, não o default de quem clicou em
 *    "selecionar tudo".
 */
export function ExportarClientesDialog({ open, onOpenChange, situacaoInicial = 'ativos' }: Props) {
  const { data: opcoes, isLoading } = useOpcoesExportacao(open)

  const [formato, setFormato] = useState<FormatoExportacao>('xlsx')
  const [selecionados, setSelecionados] = useState<string[] | null>(null)
  const [filtros, setFiltros] = useState<FiltrosExportacao>({ situacao: situacaoInicial })
  const [gerando, setGerando] = useState(false)

  // Seleções de geografia guardam a Option inteira (id + rótulo) para os chips.
  const [ruas, setRuas] = useState<Option[]>([])
  const [bairros, setBairros] = useState<Option[]>([])
  const [cidades, setCidades] = useState<Option[]>([])
  const [segmentos, setSegmentos] = useState<Option[]>([])
  const [tiposPessoa, setTiposPessoa] = useState<Option[]>([])

  // null = ainda não mexeram → usa o padrão que o backend sugeriu.
  const campos = selecionados ?? opcoes?.padrao ?? []

  const filtrosCompletos: FiltrosExportacao = useMemo(() => ({
    ...filtros,
    rua_ids: ruas.map((o) => o.id),
    bairro_ids: bairros.map((o) => o.id),
    cidade_ids: cidades.map((o) => o.id),
    segmento_ids: segmentos.map((o) => o.id),
    tipopessoa_ids: tiposPessoa.map((o) => o.id),
  }), [filtros, ruas, bairros, cidades, segmentos, tiposPessoa])

  const { data: previa, isFetching: contando } = usePreviaExportacao(filtrosCompletos, open)

  // Agrupa o catálogo preservando a ordem em que o backend declarou os campos.
  const grupos = useMemo(() => {
    const mapa = new Map<string, CampoExportavel[]>()
    for (const campo of opcoes?.campos ?? []) {
      mapa.set(campo.grupo, [...(mapa.get(campo.grupo) ?? []), campo])
    }
    return [...mapa.entries()]
  }, [opcoes])

  const sensiveisEscolhidos = (opcoes?.campos ?? [])
    .filter((c) => c.sensivel && campos.includes(c.chave))

  const excedePdf = formato === 'pdf' && (previa?.excede_pdf ?? false)
  const xlsxLento = formato === 'xlsx' && opcoes !== undefined
    && (previa?.total ?? 0) > opcoes.xlsx_lento_acima_de
  const vazio = previa?.total === 0

  function alternarCampo(chave: string) {
    setSelecionados(campos.includes(chave) ? campos.filter((c) => c !== chave) : [...campos, chave])
  }

  function alternarGrupo(itens: CampoExportavel[], marcar: boolean) {
    const chaves = itens.map((i) => i.chave)
    setSelecionados(marcar
      ? [...campos, ...chaves.filter((c) => !campos.includes(c))]
      : campos.filter((c) => !chaves.includes(c)))
  }

  function setFiltro<K extends keyof FiltrosExportacao>(chave: K, valor: FiltrosExportacao[K]) {
    setFiltros((f) => ({ ...f, [chave]: valor }))
  }

  function limparFiltros() {
    setFiltros({ situacao: 'ativos' })
    setRuas([]); setBairros([]); setCidades([]); setSegmentos([]); setTiposPessoa([])
  }

  async function exportar() {
    setGerando(true)
    try {
      await baixarExportacao(formato, campos, filtrosCompletos)
      toast.success(`Exportação gerada (${previa?.total.toLocaleString('pt-BR')} clientes). O download registra quem exportou.`)
      onOpenChange(false)
    } catch (e: any) {
      // O 422 do backend traz o número real de linhas na mensagem — é a
      // informação que diz ao dono o quanto precisa restringir.
      const blob = e?.response?.data
      let msg = e?.response?.data?.message
      if (blob instanceof Blob) {
        try { msg = JSON.parse(await blob.text())?.message } catch { /* resposta não-JSON */ }
      }
      toast.error(msg ?? 'Não foi possível gerar a exportação.')
    } finally {
      setGerando(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-4xl">
        <DialogHeader>
          <DialogTitle>Exportar clientes</DialogTitle>
          <p className="text-sm text-muted-foreground">
            Escolha as colunas e quais clientes entram. O endereço sai em colunas separadas,
            então dá para filtrar e agrupar por rua ou bairro na planilha.
          </p>
        </DialogHeader>

        {isLoading ? (
          <div className="flex items-center justify-center gap-2 py-16 text-sm text-muted-foreground">
            <Loader2 className="size-4 animate-spin" /> Carregando opções…
          </div>
        ) : (
          <Tabs defaultValue="colunas">
            <TabsList>
              <TabsTrigger value="colunas">
                Colunas <Badge variant="secondary" className="ml-2">{campos.length}</Badge>
              </TabsTrigger>
              <TabsTrigger value="filtros">Quais clientes</TabsTrigger>
            </TabsList>

            {/* ───────────────────────── Colunas ───────────────────────── */}
            <TabsContent value="colunas" className="max-h-[52vh] space-y-5 overflow-y-auto pr-1">
              {grupos.map(([grupo, itens]) => {
                const todos = itens.every((i) => campos.includes(i.chave))
                return (
                  <section key={grupo}>
                    <div className="mb-1 flex items-center justify-between border-b border-border pb-1">
                      <h3 className="text-sm font-semibold">{grupo}</h3>
                      <button type="button" onClick={() => alternarGrupo(itens, !todos)}
                        className="text-xs text-primary hover:underline">
                        {todos ? 'Desmarcar' : 'Marcar'} todos
                      </button>
                    </div>
                    <div className="grid gap-x-4 sm:grid-cols-2 lg:grid-cols-3">
                      {itens.map((campo) => (
                        <label key={campo.chave}
                          className="flex cursor-pointer select-none items-center gap-2.5 py-1.5 text-sm">
                          <Checkbox
                            checked={campos.includes(campo.chave)}
                            onCheckedChange={() => alternarCampo(campo.chave)}
                          />
                          <span className={cn('truncate', campo.sensivel && 'text-amber-700 dark:text-amber-500')}>
                            {campo.rotulo}
                            {campo.sensivel && <ShieldAlert className="ml-1 inline size-3.5 align-text-top" />}
                          </span>
                        </label>
                      ))}
                    </div>
                  </section>
                )
              })}
            </TabsContent>

            {/* ───────────────────────── Filtros ───────────────────────── */}
            <TabsContent value="filtros" className="max-h-[52vh] space-y-5 overflow-y-auto pr-1">
              <div className="flex justify-end">
                <button type="button" onClick={limparFiltros} className="text-xs text-primary hover:underline">
                  Limpar todos os filtros
                </button>
              </div>

              <section className="space-y-3">
                <h3 className="border-b border-border pb-1 text-sm font-semibold">Endereço</h3>
                <div className="grid gap-3 sm:grid-cols-3">
                  <Field label="Cidades">
                    <AsyncMultiSelect endpoint="/lookups/cidades" value={cidades} onChange={setCidades} />
                  </Field>
                  <Field label="Bairros">
                    <AsyncMultiSelect endpoint="/lookups/bairros" value={bairros} onChange={setBairros} />
                  </Field>
                  <Field label="Ruas">
                    <AsyncMultiSelect endpoint="/lookups/ruas" value={ruas} onChange={setRuas} />
                  </Field>
                </div>
                <div className="grid gap-3 sm:grid-cols-3">
                  <Field label="UF">
                    <Input maxLength={2} placeholder="Todas" value={filtros.uf ?? ''}
                      onChange={(e) => setFiltro('uf', e.target.value.toUpperCase())} />
                  </Field>
                  <Field label="CEP começa com" hint="Ex.: 85070 pega a região inteira">
                    <Input placeholder="Todos" value={filtros.cep ?? ''}
                      onChange={(e) => setFiltro('cep', e.target.value)} />
                  </Field>
                  <div className="flex items-end">
                    <CheckboxField label="Somente sem geolocalização"
                      checked={filtros.sem_geolocalizacao ?? false}
                      onChange={(b) => setFiltro('sem_geolocalizacao', b || undefined)} />
                  </div>
                </div>
              </section>

              <section className="space-y-3">
                <h3 className="border-b border-border pb-1 text-sm font-semibold">Situação e classificação</h3>
                <div className="grid gap-3 sm:grid-cols-3">
                  <Field label="Situação">
                    <Select value={filtros.situacao ?? 'ativos'}
                      onValueChange={(v) => setFiltro('situacao', v as FiltrosExportacao['situacao'])}>
                      <SelectTrigger><SelectValue /></SelectTrigger>
                      <SelectContent>
                        <SelectItem value="ativos">Somente ativos</SelectItem>
                        <SelectItem value="inativos">Somente desativados</SelectItem>
                        <SelectItem value="todos">Todos</SelectItem>
                      </SelectContent>
                    </Select>
                  </Field>
                  <Field label="Segmentos">
                    <AsyncMultiSelect endpoint="/lookups/segmentos" value={segmentos} onChange={setSegmentos} />
                  </Field>
                  <Field label="Tipos de pessoa">
                    <AsyncMultiSelect endpoint="/lookups/tipos-pessoa" value={tiposPessoa} onChange={setTiposPessoa} />
                  </Field>
                </div>
                <div className="grid gap-x-4 sm:grid-cols-2 lg:grid-cols-4">
                  {FLAGS.map(({ chave, rotulo }) => (
                    <Field key={chave} label={rotulo}>
                      <Select
                        value={filtros[chave] === undefined ? 'qualquer' : String(filtros[chave])}
                        onValueChange={(v) => setFiltro(chave, (v === 'qualquer' ? undefined : v === 'true') as never)}>
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                          <SelectItem value="qualquer">Tanto faz</SelectItem>
                          <SelectItem value="true">Sim</SelectItem>
                          <SelectItem value="false">Não</SelectItem>
                        </SelectContent>
                      </Select>
                    </Field>
                  ))}
                </div>
              </section>

              <section className="space-y-3">
                <h3 className="border-b border-border pb-1 text-sm font-semibold">Período e atividade</h3>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                  <Field label="Cadastrado de">
                    <Input type="date" value={filtros.cadastro_inicio ?? ''}
                      onChange={(e) => setFiltro('cadastro_inicio', e.target.value || undefined)} />
                  </Field>
                  <Field label="Cadastrado até">
                    <Input type="date" value={filtros.cadastro_fim ?? ''}
                      onChange={(e) => setFiltro('cadastro_fim', e.target.value || undefined)} />
                  </Field>
                  <Field label="Última compra de">
                    <Input type="date" value={filtros.ultima_compra_inicio ?? ''}
                      onChange={(e) => setFiltro('ultima_compra_inicio', e.target.value || undefined)} />
                  </Field>
                  <Field label="Última compra até">
                    <Input type="date" value={filtros.ultima_compra_fim ?? ''}
                      onChange={(e) => setFiltro('ultima_compra_fim', e.target.value || undefined)} />
                  </Field>
                </div>
                <div className="grid gap-3 sm:grid-cols-3">
                  <Field label="Sem comprar há (dias)" hint="Inclui quem nunca comprou">
                    <Input type="number" min={1} placeholder="Todos" value={filtros.sem_compra_dias ?? ''}
                      onChange={(e) => setFiltro('sem_compra_dias', e.target.value ? Number(e.target.value) : undefined)} />
                  </Field>
                  <Field label="Aniversariantes do mês">
                    <Select value={filtros.aniversario_mes ? String(filtros.aniversario_mes) : 'todos'}
                      onValueChange={(v) => setFiltro('aniversario_mes', v === 'todos' ? undefined : Number(v))}>
                      <SelectTrigger><SelectValue /></SelectTrigger>
                      <SelectContent>
                        <SelectItem value="todos">Todos os meses</SelectItem>
                        {MESES.map((m, i) => <SelectItem key={m} value={String(i + 1)}>{m}</SelectItem>)}
                      </SelectContent>
                    </Select>
                  </Field>
                  <div className="flex flex-col justify-end">
                    <CheckboxField label="Somente com limite de crédito"
                      checked={filtros.com_credito ?? false}
                      onChange={(b) => setFiltro('com_credito', b || undefined)} />
                    <CheckboxField label="Somente com saldo devedor"
                      checked={filtros.com_saldo_devedor ?? false}
                      onChange={(b) => setFiltro('com_saldo_devedor', b || undefined)} />
                  </div>
                </div>
              </section>

              <section className="space-y-3">
                <h3 className="border-b border-border pb-1 text-sm font-semibold">Busca livre</h3>
                <Field label="Nome, fantasia, CPF ou CNPJ contém">
                  <Input placeholder="Opcional" value={filtros.q ?? ''}
                    onChange={(e) => setFiltro('q', e.target.value || undefined)} />
                </Field>
              </section>
            </TabsContent>
          </Tabs>
        )}

        {/* ── Formato + resumo: o que sai, em quantas linhas, com qual risco ── */}
        <div className="space-y-3 border-t border-border pt-4">
          <div className="grid gap-2 sm:grid-cols-3">
            {FORMATOS.map(({ valor, rotulo, hint, icone: Icone }) => (
              <button key={valor} type="button" onClick={() => setFormato(valor)}
                className={cn(
                  'flex items-center gap-3 rounded-md border p-3 text-left transition-colors',
                  formato === valor ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-input hover:bg-secondary',
                )}>
                <Icone className={cn('size-5 shrink-0', formato === valor ? 'text-primary' : 'text-muted-foreground')} />
                <div className="min-w-0">
                  <div className="text-sm font-medium">{rotulo}</div>
                  <div className="truncate text-xs text-muted-foreground">{hint}</div>
                </div>
              </button>
            ))}
          </div>

          {sensiveisEscolhidos.length > 0 && (
            <p className="flex items-start gap-2 rounded-md bg-amber-50 p-2.5 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-400">
              <ShieldAlert className="mt-0.5 size-4 shrink-0" />
              <span>
                <strong>{sensiveisEscolhidos.length} coluna{sensiveisEscolhidos.length > 1 ? 's' : ''} com dado pessoal sensível</strong>
                {' '}({sensiveisEscolhidos.map((c) => c.rotulo).join(', ')}).
                A LGPD responsabiliza quem exporta pelo destino do arquivo — esta exportação fica registrada em seu nome.
              </span>
            </p>
          )}

          {excedePdf && (
            <p className="rounded-md bg-destructive/10 p-2.5 text-xs text-destructive">
              O filtro atinge {previa?.total.toLocaleString('pt-BR')} clientes e o PDF comporta{' '}
              {opcoes?.limite_pdf.toLocaleString('pt-BR')}. Restrinja o filtro ou escolha Excel/CSV.
            </p>
          )}

          {/* Aviso, não bloqueio: o volume é exportável, só demora. Quem quiser
              o Excel com a base inteira pode — só não deve achar que travou. */}
          {xlsxLento && (
            <p className="rounded-md bg-amber-50 p-2.5 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-400">
              São {previa?.total.toLocaleString('pt-BR')} clientes: o Excel pode levar
              alguns minutos para ser gerado. O CSV sai em segundos e abre no Excel do mesmo jeito.
            </p>
          )}
        </div>

        <DialogFooter className="items-center sm:justify-between">
          <p className="text-sm text-muted-foreground">
            {/* Durante a geração o texto vira progresso: um XLSX grande leva
                minutos, e sem isso a espera parece travamento. */}
            {gerando ? 'Gerando o arquivo… pode levar alguns minutos.'
              : contando ? 'Contando…'
              : previa ? <><strong className="text-foreground">{previa.total.toLocaleString('pt-BR')}</strong> cliente{previa.total === 1 ? '' : 's'} · {campos.length} coluna{campos.length === 1 ? '' : 's'}</>
              : '—'}
          </p>
          <div className="flex gap-2">
            <Button variant="outline" onClick={() => onOpenChange(false)}>Cancelar</Button>
            <Button loading={gerando}
              disabled={campos.length === 0 || excedePdf || vazio || contando}
              onClick={exportar}>
              Exportar
            </Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
