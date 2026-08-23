import { useState } from 'react'
import { Download, Search, AlertTriangle, CheckCircle2, Loader2, Wrench } from 'lucide-react'
import {
  Button, Card, Input, Badge, DataTable, type Column, EmptyState, AsyncState,
  Field, AsyncSelect, Dialog, DialogContent, DialogHeader, DialogTitle, toast,
} from '@/components/ui'
import {
  useMunicipiosIbge, useAdotarMunicipio, useConciliacaoIbge, useAplicarConciliacao,
  useImportacoes, useImportarLogradouros,
  type MunicipioIbge, type Divergencia, type Importacao,
} from './api'

/**
 * Catálogo IBGE e importação de logradouros.
 *
 * Duas coisas que resolvem problemas medidos na base:
 *  - o código IBGE digitado à mão gerou código inventado, zerado e de outra
 *    cidade — e ele vai no XML da NF-e, onde errado é rejeição da SEFAZ;
 *  - cidade nova entra com a base geográfica vazia e o entregador digita o
 *    mesmo logradouro de várias formas.
 */
export function ImportacaoTab() {
  return (
    <div className="space-y-6">
      <ConciliacaoIbge />
      <AdotarCidade />
      <ImportarRuas />
    </div>
  )
}

// =================== CONCILIAÇÃO ===================
function ConciliacaoIbge() {
  const { data, isLoading, error } = useConciliacaoIbge()
  const aplicar = useAplicarConciliacao()
  const divergencias = data?.data ?? []

  async function corrigir() {
    try {
      const r = await aplicar.mutateAsync()
      toast.success(r.message ?? 'Conciliação aplicada.')
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Não foi possível aplicar.')
    }
  }

  const columns: Column<Divergencia>[] = [
    {
      key: 'cidade', header: 'Cidade',
      cell: (d) => (
        <div className="min-w-0">
          <div className="font-medium truncate">{d.cidade} <span className="text-muted-foreground">/{d.uf}</span></div>
          {d.nome_oficial && d.nome_oficial !== d.cidade && (
            <div className="text-xs text-muted-foreground">oficial: {d.nome_oficial}</div>
          )}
        </div>
      ),
    },
    { key: 'atual', header: 'Código atual', align: 'right', cell: (d) => <span className="tabular-nums text-destructive">{d.cod_ibge_atual ?? '—'}</span> },
    { key: 'correto', header: 'Código oficial', align: 'right', cell: (d) => <span className="tabular-nums">{d.cod_ibge_correto ?? '—'}</span> },
    {
      key: 'motivo', header: 'Problema',
      cell: (d) => (
        <Badge variant={d.criterio === 'sem_correspondencia' ? 'secondary' : 'destructive'}>
          {d.criterio === 'nome' ? 'código ausente/inválido'
            : d.criterio === 'codigo_uf_divergente' ? 'código de outra UF'
            : 'sem correspondência'}
        </Badge>
      ),
    },
  ]

  const corrigiveis = divergencias.filter((d) => d.cod_ibge_correto !== null).length

  return (
    <Card className="p-4">
      <div className="mb-3 flex items-start justify-between gap-4">
        <div>
          <h3 className="font-medium flex items-center gap-2"><Wrench size={16} /> Conferência do código IBGE</h3>
          <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
            O código IBGE da cidade vai no XML da NF-e. Código errado é
            <strong> rejeição da SEFAZ</strong> — esta lista é o risco fiscal em aberto.
          </p>
        </div>
        {corrigiveis > 0 && (
          <Button onClick={corrigir} disabled={aplicar.isPending}>
            {aplicar.isPending ? <Loader2 size={16} className="animate-spin" /> : <Wrench size={16} />}
            Corrigir {corrigiveis}
          </Button>
        )}
      </div>

      <AsyncState loading={isLoading} error={error} skeletonRows={2}>
        <DataTable
          columns={columns}
          rows={divergencias}
          rowKey={(d) => d.cidade_id}
          empty={
            <EmptyState
              icon={<CheckCircle2 />}
              title="Todos os códigos conferem"
              description="Nenhuma cidade diverge do catálogo oficial do IBGE."
            />
          }
        />
      </AsyncState>
    </Card>
  )
}

// =================== ADOTAR CIDADE ===================
function AdotarCidade() {
  const [aberto, setAberto] = useState(false)
  const [busca, setBusca] = useState('')
  const [uf, setUf] = useState('')
  const { data, isLoading } = useMunicipiosIbge(busca, uf)
  const adotar = useAdotarMunicipio()

  async function escolher(m: MunicipioIbge) {
    try {
      const r = await adotar.mutateAsync(m.cod_ibge)
      toast.success(r.message ?? `${m.nome}/${m.uf} cadastrada.`)
      setAberto(false)
      setBusca('')
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Não foi possível cadastrar.')
    }
  }

  return (
    <Card className="p-4">
      <div className="flex items-start justify-between gap-4">
        <div>
          <h3 className="font-medium">Cadastrar cidade pelo catálogo oficial</h3>
          <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
            Nome e código vêm do IBGE — não há como digitar um código errado.
          </p>
        </div>
        <Button onClick={() => setAberto(true)}><Search size={16} /> Buscar município</Button>
      </div>

      {/* Dialog cru, não FormDialog: a escolha acontece ao clicar no município
          da lista, então um botão "Salvar" no rodapé não teria o que confirmar. */}
      <Dialog open={aberto} onOpenChange={setAberto}>
        <DialogContent>
          <DialogHeader><DialogTitle>Municípios do IBGE</DialogTitle></DialogHeader>
          <div className="space-y-3">
            <div className="flex gap-2">
              <div className="flex-1">
                <Field label="Nome do município">
                  <Input autoFocus value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Ex.: Guarapuava" />
                </Field>
              </div>
              <div className="w-32">
                <Field label="UF">
                  <AsyncSelect endpoint="/lookups/estados" value={uf ? -1 : null} valueLabel={uf || null}
                    placeholder="Todas" onChange={(_, opt) => setUf(opt?.uf ? String(opt.uf) : '')} />
                </Field>
              </div>
            </div>

            <div className="max-h-72 overflow-y-auto rounded-md border">
              {isLoading && <div className="p-4 text-sm text-muted-foreground">Buscando…</div>}
              {!isLoading && (data?.data.length ?? 0) === 0 && (
                <div className="p-4 text-sm text-muted-foreground">
                  {busca.trim().length < 2 && uf === ''
                    ? 'Digite ao menos 2 letras ou escolha uma UF.'
                    : 'Nenhum município encontrado.'}
                </div>
              )}
              {data?.data.map((m) => (
                <button
                  key={m.cod_ibge}
                  type="button"
                  onClick={() => escolher(m)}
                  disabled={adotar.isPending}
                  className="flex w-full items-center justify-between border-b px-3 py-2 text-left text-sm last:border-0 hover:bg-muted/60 disabled:opacity-50"
                >
                  <span>{m.nome} <span className="text-muted-foreground">/{m.uf}</span></span>
                  <span className="tabular-nums text-xs text-muted-foreground">{m.cod_ibge}</span>
                </button>
              ))}
            </div>
          </div>
        </DialogContent>
      </Dialog>
    </Card>
  )
}

// =================== IMPORTAR RUAS ===================
function ImportarRuas() {
  const importacoes = useImportacoes()
  const importar = useImportarLogradouros()
  const [cidadeId, setCidadeId] = useState<number | null>(null)
  const [cidadeLabel, setCidadeLabel] = useState<string | null>(null)

  async function disparar() {
    if (cidadeId === null) { toast.error('Escolha a cidade.'); return }
    try {
      const r = await importar.mutateAsync(cidadeId)
      toast.success(r.message ?? 'Importação iniciada.')
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Não foi possível iniciar.')
    }
  }

  const columns: Column<Importacao>[] = [
    {
      key: 'cidade', header: 'Cidade',
      cell: (i) => (
        <div className="min-w-0">
          <div className="font-medium truncate">{i.cidade ?? '—'}{i.uf && <span className="text-muted-foreground">/{i.uf}</span>}</div>
          <div className="text-xs text-muted-foreground">{new Date(i.criado_em).toLocaleString('pt-BR')}</div>
        </div>
      ),
    },
    {
      key: 'situacao', header: 'Situação',
      cell: (i) => i.situacao === 'processando'
        ? <Badge variant="secondary"><Loader2 size={12} className="mr-1 inline animate-spin" />processando</Badge>
        : i.situacao === 'falhou'
          ? <Badge variant="destructive" title={i.erro ?? undefined}>falhou</Badge>
          : <Badge>concluída</Badge>,
    },
    { key: 'ruas', header: 'Ruas', align: 'right', cell: (i) => <span className="tabular-nums">{i.ruas_criadas}</span> },
    { key: 'bairros', header: 'Bairros', align: 'right', cell: (i) => <span className="tabular-nums">{i.bairros_criados}</span> },
    { key: 'completadas', header: 'Completadas', align: 'right', cell: (i) => <span className="tabular-nums text-muted-foreground">{i.ruas_atualizadas}</span> },
    {
      key: 'aviso', header: '', align: 'right',
      cell: (i) => i.termos_truncados > 0
        ? (
          <span title={`${i.termos_truncados} busca(s) atingiram o limite da fonte: podem faltar ruas.`}>
            <AlertTriangle size={15} className="text-amber-500" />
          </span>
        )
        : null,
    },
  ]

  return (
    <Card className="p-4">
      <div className="mb-3">
        <h3 className="font-medium flex items-center gap-2"><Download size={16} /> Importar ruas e bairros</h3>
        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
          Carrega os logradouros da cidade a partir da base de CEP dos Correios,
          com bairro e CEP. Leva alguns minutos e roda em segundo plano.
          Ruas já cadastradas <strong>não são recriadas</strong> — apenas completadas.
        </p>
      </div>

      <div className="mb-4 flex flex-wrap items-end gap-2">
        <div className="w-64">
          <Field label="Cidade">
            <AsyncSelect endpoint="/lookups/cidades" value={cidadeId} valueLabel={cidadeLabel}
              placeholder="Escolha a cidade" onChange={(id, opt) => { setCidadeId(id); setCidadeLabel(opt?.label ?? null) }} />
          </Field>
        </div>
        <Button onClick={disparar} disabled={importar.isPending || cidadeId === null}>
          {importar.isPending ? <Loader2 size={16} className="animate-spin" /> : <Download size={16} />}
          Importar
        </Button>
      </div>

      <DataTable
        columns={columns}
        rows={importacoes}
        rowKey={(i) => i.id}
        empty={<EmptyState icon={<Download />} title="Nenhuma importação ainda" description="Escolha uma cidade acima para carregar as ruas dela." />}
      />
    </Card>
  )
}
