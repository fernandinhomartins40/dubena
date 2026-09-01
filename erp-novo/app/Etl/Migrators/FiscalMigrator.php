<?php

namespace App\Etl\Migrators;

use App\Domain\Fiscal\ModeloDocumento;
use App\Domain\Shared\NumeroSequencialService;
use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Etl\Support\PreservaIdsDoLegado;
use Illuminate\Support\Facades\DB;

/**
 * N9 — fiscal: notas emitidas (241 mil), seus itens (254 mil) e notas recebidas.
 *
 * Nomes REAIS do legado: `nfemitidas`/`nfemitidaitems` e
 * `nfrecebidas`/`nfrecebidaitems`. O legado guarda ~150 colunas por nota
 * (XML, protocolo, todos os tributos, emitente e destinatário desnormalizados);
 * o schema novo guarda o essencial + totais. A migração traz o que o schema
 * novo modela — o XML original permanece no legado para consulta fiscal.
 *
 * Conversões-chave:
 *  - `nfsituacao_id` (595 situações do legado) → `situacao` textual
 *    (AUTORIZADA/CANCELADA/REJEITADA/RASCUNHO), derivada de quem tem protocolo
 *    e de `inutilizarcancelar`;
 *  - `nfserie` é VARCHAR no legado e integer no destino;
 *  - `chave` tem UNIQUE no destino: nota sem chave entra como null, e chave
 *    repetida (contingência reemitida) mantém a primeira.
 *  - o item aponta para `produtos.id`; o legado guarda só `cprod` (código do
 *    produto como texto), então o casamento é por código.
 */
final class FiscalMigrator implements Migrator
{
    use PreservaIdsDoLegado;

    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'fiscal';
    }

    public function dependeDe(): array
    {
        return ['clientes', 'produtos', 'pedidos'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->usarConexaoDe($ctx);

        $this->ctxAtual = $ctx;

        if (! $this->tabelaExiste($ctx, 'nfemitidas')) {
            return new MigrationResult($this->nome(), 0, 0, 0,
                ['legado indisponível ou sem as tabelas fiscais']);
        }

        $lidos = 0;
        $gravados = 0;
        $pulados = 0;
        $avisos = [];

        $idsEmpresa = $this->idsDe('empresas');
        $idsCliente = $this->idsDe('clientes');
        $chavesUsadas = [];
        // O destino tem UNIQUE (empresa, modelo, serie, numero). O legado
        // repete a combinacao — nota inutilizada e reemitida com o mesmo
        // numero, ou reenvio em contingencia. Fica a PRIMEIRA (a original);
        // as demais sao contadas como puladas.
        $numerosUsados = [];

        // ── Notas emitidas ──
        $ctx->legado()->table('nfemitidas')->orderBy('id')->chunk(2000,
            function ($rows) use (
                &$lidos, &$gravados, &$pulados, &$chavesUsadas, &$numerosUsados,
                $ctx, $idsEmpresa, $idsCliente
            ) {
                $lote = [];
                foreach ($rows as $r) {
                    $lidos++;
                    $empresa = (int) $r->empresa_id;
                    if (! isset($idsEmpresa[$empresa])) {
                        $pulados++;

                        continue;
                    }

                    // `chave` é UNIQUE no destino: contingência reemitida repete.
                    $chave = trim((string) ($r->chaveacesso ?? ''));
                    if ($chave !== '') {
                        if (isset($chavesUsadas[$chave])) {
                            $chave = null;
                        } else {
                            $chavesUsadas[$chave] = true;
                        }
                    } else {
                        $chave = null;
                    }

                    $cliente = (int) ($r->cliente_id ?? 0);
                    $modelo = mb_substr((string) $r->nfmodelo, 0, 4);
                    $serie = (int) preg_replace('/\D/', '', (string) $r->nfserie) ?: 1;
                    $numero = (int) $r->nfnumero;

                    $chaveNota = "{$empresa}|{$modelo}|{$serie}|{$numero}";
                    if (isset($numerosUsados[$chaveNota])) {
                        $pulados++;

                        continue;
                    }
                    $numerosUsados[$chaveNota] = true;

                    $lote[] = [
                        'id' => (int) $r->id,
                        'empresa_id' => $empresa,
                        'grupo_id' => (int) $r->grupo_id,
                        'cliente_id' => isset($idsCliente[$cliente]) ? $cliente : null,
                        'modelo' => $modelo,
                        'tipo' => 'S',   // emitida = saída
                        'serie' => $serie,
                        'numero' => $numero,
                        'chave' => $chave !== null ? mb_substr($chave, 0, 44) : null,
                        'protocolo' => mb_substr((string) ($r->protocolo ?? ''), 0, 30) ?: null,
                        'valor_produtos' => $this->dec($r->vprod ?? 0),
                        'valor_desconto' => $this->dec($r->vdesc ?? 0),
                        'valor_frete' => $this->dec($r->vfrete ?? 0),
                        'valor_icms' => $this->dec($r->vicms ?? 0),
                        'valor_icms_st' => $this->dec($r->vst ?? 0),
                        'valor_ipi' => $this->dec($r->vipi ?? 0),
                        'valor_pis' => $this->dec($r->vpis ?? 0),
                        'valor_cofins' => $this->dec($r->vcofins ?? 0),
                        'valor_total' => $this->dec($r->vnf ?? 0),
                        'situacao' => $this->situacao($r),
                        'emitida_em' => $r->datahoraemissao ?? null,
                        'created_at' => $r->created_at ?? null,
                    ];
                }
                if ($lote !== [] && ! $ctx->dryRun) {
                    $gravados += $this->gravarPreservandoId('notas_fiscais', $lote, ['id'], 500);
                }
            });

        // ── Itens (dependem da nota gravada; produto casado por código) ──
        if ($this->tabelaExiste($ctx, 'nfemitidaitems')) {
            $empresaDaNota = $this->empresaPorNota();
            $produtoPorCodigo = $this->produtoPorCodigo();
            $fallbackProduto = (int) ($this->destino()->table('produtos')->min('id') ?? 0);
            $itemPorNota = [];

            $ctx->legado()->table('nfemitidaitems')->orderBy('id')->chunk(2000,
                function ($rows) use (
                    &$lidos, &$gravados, &$pulados, &$itemPorNota,
                    $ctx, $empresaDaNota, $produtoPorCodigo, $fallbackProduto
                ) {
                    $lote = [];
                    foreach ($rows as $r) {
                        $lidos++;
                        $nota = (int) $r->nfemitida_id;
                        $empresa = $empresaDaNota[$nota] ?? null;
                        if ($empresa === null) {
                            $pulados++;

                            continue;
                        }

                        $codigo = trim((string) ($r->cprod ?? ''));
                        $produto = $produtoPorCodigo[$codigo] ?? $fallbackProduto;
                        if ($produto === 0) {
                            $pulados++;

                            continue;
                        }

                        // `numero_item` não vem do legado: numera na ordem.
                        $itemPorNota[$nota] = ($itemPorNota[$nota] ?? 0) + 1;

                        $lote[] = [
                            'id' => (int) $r->id,
                            'nota_fiscal_id' => $nota,
                            'empresa_id' => $empresa,
                            'produto_id' => $produto,
                            'numero_item' => $itemPorNota[$nota],
                            'quantidade' => round((float) $r->qcom, 3),
                            'valor_unitario' => round((float) $r->vuncom, 4),
                            'valor_total' => $this->dec($r->vprod ?? 0),
                            'desconto' => $this->dec($r->vdesc ?? 0),
                            'cfop' => mb_substr((string) ($r->cfop ?? ''), 0, 4) ?: null,
                            'cst_icms' => mb_substr((string) ($r->cst ?? ''), 0, 3) ?: null,
                            'bc_icms' => $this->dec($r->vbc ?? 0),
                            'aliq_icms' => $this->dec($r->picms ?? 0, 4),
                            'valor_icms' => $this->dec($r->vicms ?? 0),
                            'aliq_pis' => $this->dec($r->ppis ?? 0, 4),
                            'valor_pis' => $this->dec($r->vpis ?? 0),
                            'aliq_cofins' => $this->dec($r->pcofins ?? 0, 4),
                            'valor_cofins' => $this->dec($r->vcofins ?? 0),
                            'aliq_ipi' => $this->dec($r->pipi ?? 0, 4),
                            'valor_ipi' => $this->dec($r->vipi ?? 0),
                            'created_at' => $r->created_at ?? null,
                        ];
                    }
                    if ($lote !== [] && ! $ctx->dryRun) {
                        $gravados += $this->gravarPreservandoId('nota_itens', $lote, ['id'], 500);
                    }
                });
        }

        // ── Notas recebidas (entrada) ──
        [$n, $g, $p, $aviso] = $this->migrarRecebidas($ctx, $idsEmpresa);
        $lidos += $n;
        $gravados += $g;
        $pulados += $p;
        if ($aviso !== null) {
            $avisos[] = $aviso;
        }

        // ── Itens das recebidas + cartas de correção (pós-auditoria 2026-08-14:
        // as duas fontes estavam espelhadas sem consumidor) ──
        [$n, $g, $p] = $this->migrarItensRecebidas($ctx);
        $lidos += $n;
        $gravados += $g;
        $pulados += $p;
        [$n, $g, $p] = $this->migrarCartasCorrecao($ctx);
        $lidos += $n;
        $gravados += $g;
        $pulados += $p;

        if (! $ctx->dryRun) {
            $avisos = array_merge($avisos, $this->religarNotaAoPedido($ctx));
            $avisos = array_merge($avisos, $this->semearNumeracaoFiscal($ctx));
        }

        if ($pulados > 0) {
            $avisos[] = "{$pulados} registro(s) fiscais descartados: empresa/nota "
                .'ausente no destino';
        }
        $avisos[] = 'o XML original das notas permanece no legado — o schema novo '
            .'guarda os dados estruturados e os totais, não o XML bruto';

        return new MigrationResult(
            migrator: $this->nome(),
            lidos: $lidos,
            gravados: $ctx->dryRun ? 0 : $gravados,
            pulados: $pulados,
            avisos: $avisos,
        );
    }

    public function invariantes(): array
    {
        $ctx = $this->ctxAtual ?? new MigrationContext;

        return [
            // Descarte por COLISÃO DE CHAVE NATURAL, comprovado (T2.5): 3 notas
            // do legado não chegam ao destino. Cada uma tem uma GÊMEA com o
            // mesmo (empresa, MODELO, série, número) — 6 linhas para 3 notas.
            // O destino tem UNIQUE nessa chave
            // (`notas_fiscais_empresa_id_modelo_serie_numero_unique`) e ficou
            // com uma de cada par.
            //
            // Verificado par a par: a que sobrevive é sempre a de situação 100
            // (AUTORIZADA); as descartadas têm situação 2 e 539 (rejeitada/
            // denegada) — tentativas de emissão que falharam e foram reemitidas
            // com o mesmo número. A nota com valor fiscal é a autorizada, e ela
            // está no destino. Não há perda de documento válido.
            //
            //   SELECT nfnumero, id, nfsituacao_id FROM legado.nfemitidas
            //    WHERE nfnumero::text IN ('168834','168880','168881')
            //    ORDER BY nfnumero::int, id::int;
            new CountInvariant(
                $ctx, 'nfemitidas', 'notas_fiscais',
                descartesEsperados: fn () => $this->notasComNumeroDuplicado($ctx),
            ),
        ];
    }

    /**
     * Quantas notas da origem colidem em (empresa, série, número) — isto é,
     * quantas linhas excedem a primeira de cada chave natural.
     *
     * Calculado sobre a origem a cada execução (T2.5): um número fixo aqui
     * viraria mentira na próxima recarga do dump.
     */
    private function notasComNumeroDuplicado(MigrationContext $ctx): int
    {
        try {
            // O agrupamento espelha EXATAMENTE o UNIQUE do destino
            // (`notas_fiscais_empresa_id_modelo_serie_numero_unique`), MODELO
            // incluído. Sem o modelo o agrupamento acha 38 colisões — NF-e (55)
            // e NFC-e (65) numeram em faixas próprias e coincidem sem conflito
            // real. Com ele, exatamente 3: os pares medidos.
            $duplicadas = $ctx->legado()
                ->table('nfemitidas')
                ->selectRaw('count(*) - 1 AS excedentes')
                ->groupBy('empresa_id', 'nfmodelo', 'nfserie', 'nfnumero')
                ->havingRaw('count(*) > 1')
                ->pluck('excedentes');
        } catch (\Throwable) {
            return 0;
        }

        return (int) $duplicadas->sum();
    }

    /** @return array{0:int,1:int,2:int,3:?string} */
    private function migrarRecebidas(MigrationContext $ctx, array $idsEmpresa): array
    {
        if (! $this->tabelaExiste($ctx, 'nfrecebidas')
            || ! $this->tabelaDestinoExiste('nf_recebidas')) {
            return [0, 0, 0, null];
        }

        $colunas = DB::getSchemaBuilder()->getColumnListing('nf_recebidas');
        $lidos = 0;
        $gravados = 0;
        $pulados = 0;

        $ctx->legado()->table('nfrecebidas')->orderBy('id')->chunk(500,
            function ($rows) use (&$lidos, &$gravados, &$pulados, $ctx, $idsEmpresa, $colunas) {
                $lote = [];
                foreach ($rows as $r) {
                    $lidos++;
                    $empresa = (int) $r->empresa_id;
                    if (! isset($idsEmpresa[$empresa])) {
                        $pulados++;

                        continue;
                    }

                    // Monta só com as colunas que a tabela de destino tem —
                    // `nf_recebidas` variou entre migrações do schema.
                    $linha = array_filter([
                        'id' => (int) $r->id,
                        'empresa_id' => $empresa,
                        'grupo_id' => (int) $r->grupo_id,
                        'chave' => mb_substr((string) ($r->chaveacesso ?? ''), 0, 44) ?: null,
                        'numero' => (int) $r->nfnumero,
                        'serie' => (int) preg_replace('/\D/', '', (string) $r->nfserie) ?: 1,
                        'modelo' => mb_substr((string) $r->nfmodelo, 0, 4),
                        'emitente_cnpj' => preg_replace('/\D/', '', (string) ($r->emitcnpj ?? '')) ?: null,
                        'emitente_nome' => mb_substr((string) ($r->emitrazaosocial ?? ''), 0, 255) ?: null,
                        'valor_total' => $this->dec($r->vnf ?? 0),
                        'data_emissao' => $r->datahoraemissao ?? null,
                        'created_at' => $r->created_at ?? null,
                    ], fn ($k) => in_array($k, $colunas, true), ARRAY_FILTER_USE_KEY);

                    $lote[] = $linha;
                }
                if ($lote !== [] && ! $ctx->dryRun) {
                    $gravados += $this->gravarPreservandoId('nf_recebidas', $lote, ['id'], 500);
                }
            });

        return [$lidos, $gravados, $pulados, null];
    }

    /**
     * Itens das notas recebidas (o legado detalha ~100 colunas de tributo por
     * item; o destino guarda o essencial da entrada).
     *
     * @return array{0:int,1:int,2:int}
     */
    private function migrarItensRecebidas(MigrationContext $ctx): array
    {
        if (! $this->tabelaExiste($ctx, 'nfrecebidaitems')
            || ! $this->tabelaDestinoExiste('nf_recebida_itens')) {
            return [0, 0, 0];
        }

        $empresaDaNota = [];
        foreach ($this->destino()->table('nf_recebidas')->select('id', 'empresa_id')->cursor() as $r) {
            $empresaDaNota[(int) $r->id] = (int) $r->empresa_id;
        }

        $lidos = 0;
        $gravados = 0;
        $pulados = 0;
        $lote = [];
        foreach ($ctx->legado()->table('nfrecebidaitems')->orderBy('id')->get() as $r) {
            $lidos++;
            $nota = (int) $r->nfrecebida_id;
            if (! isset($empresaDaNota[$nota])) {
                $pulados++;

                continue;
            }
            $lote[] = [
                'id' => (int) $r->id,
                'nf_recebida_id' => $nota,
                'empresa_id' => $empresaDaNota[$nota],
                'codigo_fornecedor' => mb_substr((string) ($r->cprod ?? ''), 0, 60) ?: null,
                'descricao' => mb_substr(trim((string) ($r->xprod ?? '')), 0, 255) ?: 'Item',
                'ncm' => mb_substr((string) ($r->ncm ?? ''), 0, 10) ?: null,
                'cfop' => mb_substr((string) ($r->cfop ?? ''), 0, 4) ?: null,
                'quantidade' => round((float) ($r->qcom ?? 0), 3),
                'valor_unitario' => round((float) ($r->vuncom ?? 0), 4),
                'valor_total' => $this->dec($r->vprod ?? 0),
                'created_at' => $r->created_at ?? null,
            ];
        }
        if ($lote !== [] && ! $ctx->dryRun) {
            $gravados += $this->gravarPreservandoId('nf_recebida_itens', $lote, ['id'], 500);
        }

        return [$lidos, $gravados, $pulados];
    }

    /**
     * Cartas de correção emitidas (evento 110110) → cartas_correcao.
     *
     * @return array{0:int,1:int,2:int}
     */
    private function migrarCartasCorrecao(MigrationContext $ctx): array
    {
        if (! $this->tabelaExiste($ctx, 'nfemitidacartacorrecaos')
            || ! $this->tabelaDestinoExiste('cartas_correcao')) {
            return [0, 0, 0];
        }

        $empresaDaNota = $this->empresaPorNota();

        $lidos = 0;
        $gravados = 0;
        $pulados = 0;
        $lote = [];
        foreach ($ctx->legado()->table('nfemitidacartacorrecaos')->orderBy('id')->get() as $r) {
            $lidos++;
            $nota = (int) ($r->nfemitida_id ?? 0);
            if (! isset($empresaDaNota[$nota])) {
                $pulados++;

                continue;
            }
            $protocolo = trim((string) ($r->protocoloretornoevento ?? ''));
            $lote[] = [
                'id' => (int) $r->id,
                'empresa_id' => $empresaDaNota[$nota],
                'nota_fiscal_id' => $nota,
                'sequencia' => (int) ($r->nseqevento ?? 1) ?: 1,
                'correcao' => trim((string) ($r->xcorrecao ?? '')) ?: '-',
                'protocolo' => mb_substr($protocolo, 0, 30) ?: null,
                'registrada' => $protocolo !== '',
                'created_at' => $r->created_at ?? null,
            ];
        }
        if ($lote !== [] && ! $ctx->dryRun) {
            $gravados += $this->gravarPreservandoId('cartas_correcao', $lote, ['id'], 500);
        }

        return [$lidos, $gravados, $pulados];
    }

    /**
     * Situação textual a partir do que o legado registra.
     *
     * O legado tem 595 linhas em `nfsituacaos` (mensagens da SEFAZ por empresa),
     * o que não mapeia para o enum do destino. O estado real é derivado do que
     * importa: tem protocolo de autorização? foi cancelada/inutilizada?
     */
    private function situacao(object $r): string
    {
        if ($this->booleano($r->inutilizarcancelar ?? null)
            || ! empty($r->cancelamentomotivo)
            || ! empty($r->protocoloretornocancelamento)) {
            return 'CANCELADA';
        }
        if (! empty($r->protocolo) || ! empty($r->datahoraautorizacao)) {
            return 'AUTORIZADA';
        }

        return 'RASCUNHO';
    }

    /** @return array<int,int> nota_id => empresa_id */
    private function empresaPorNota(): array
    {
        $out = [];
        foreach ($this->destino()->table('notas_fiscais')->select('id', 'empresa_id')->cursor() as $n) {
            $out[(int) $n->id] = (int) $n->empresa_id;
        }

        return $out;
    }

    /**
     * Código do produto (como texto, igual ao `cprod` da nota) => produtos.id.
     * O legado grava o id do produto em `cprod`; cobre-se também o casamento
     * por descrição quando o código não bate.
     *
     * @return array<string,int>
     */
    private function produtoPorCodigo(): array
    {
        $out = [];
        foreach ($this->destino()->table('produtos')->select('id', 'descricao')->cursor() as $p) {
            $out[(string) $p->id] = (int) $p->id;
            $desc = trim(mb_strtoupper((string) $p->descricao));
            if ($desc !== '') {
                $out[$desc] ??= (int) $p->id;
            }
        }

        return $out;
    }

    private function dec(mixed $v, int $casas = 2): float
    {
        return round((float) ($v ?? 0), $casas);
    }

    private function booleano(mixed $v): bool
    {
        $v = mb_strtoupper(trim((string) ($v ?? '')));

        return in_array($v, ['1', 'S', 'T', 'TRUE', 'Y'], true);
    }

    /** @return array<int,true> */
    private function idsDe(string $tabela): array
    {
        $ids = [];
        foreach ($this->destino()->table($tabela)->select('id')->cursor() as $r) {
            $ids[(int) $r->id] = true;
        }

        return $ids;
    }

    private function tabelaDestinoExiste(string $tabela): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($tabela);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Liga a nota fiscal ao pedido que a originou.
     *
     * **O vínculo está do lado do PEDIDO no legado.** `nfemitidas` não tem
     * coluna de pedido; quem aponta é `pedidos.nfce_id` — e por isso nem o
     * `PedidosMigrator` (que não lê a coluna) nem o `FiscalMigrator` (que só
     * enxerga a nota) o traziam. As 241.021 notas migraram com `pedido_id` nulo.
     *
     * O custo: o diálogo do pedido mostra "NF-e: —" mesmo nos 167.442 pedidos
     * faturados, e a SPA usa `notasVivas` para decidir se ainda cabe emitir —
     * ou seja, oferecia emitir NFC-e para pedido que já tem nota autorizada.
     *
     * Roda aqui, e não no `PedidosMigrator`, porque `notas_fiscais` só existe
     * depois desta carga (`fiscal` depende de `pedidos`, nunca o contrário).
     *
     * @return list<string> avisos para o relatório do `etl:run`
     */
    private function religarNotaAoPedido(MigrationContext $ctx): array
    {
        if (! $this->tabelaExiste($ctx, 'pedidos')) {
            return [];
        }

        $idsNota = $this->destino()->table('notas_fiscais')->pluck('id')->flip();
        $idsPedido = $this->destino()->table('pedidos')->pluck('id')->flip();
        $ligados = 0;
        $semNota = 0;

        // Em blocos: são 400 mil pedidos, e o UPDATE é por nota.
        $ctx->legado()->table('pedidos')
            ->whereNotNull('nfce_id')
            ->select('id', 'nfce_id')
            ->orderBy('id')
            ->chunk(5000, function ($rows) use (&$ligados, &$semNota, $idsNota, $idsPedido) {
                $porNota = [];
                foreach ($rows as $r) {
                    $nota = (int) $r->nfce_id;
                    $pedido = (int) $r->id;
                    if (! isset($idsNota[$nota]) || ! isset($idsPedido[$pedido])) {
                        $semNota++;

                        continue;
                    }
                    $porNota[$nota] = $pedido;
                }

                foreach (array_chunk($porNota, 1000, true) as $bloco) {
                    foreach ($bloco as $nota => $pedido) {
                        $this->destino()->table('notas_fiscais')->where('id', $nota)->update(['pedido_id' => $pedido]);
                        $ligados++;
                    }
                }
            });

        $avisos = ["{$ligados} nota(s) fiscal(is) religada(s) ao pedido de origem "
            .'(o vínculo vive em `pedidos.nfce_id`, não na nota)'];

        if ($semNota > 0) {
            $avisos[] = "{$semNota} pedido(s) apontam para nota que não sobreviveu à carga — vínculo não criado";
        }

        return $avisos;
    }

    /**
     * Semeia a numeração fiscal por empresa+modelo+série.
     *
     * **Sem isto a primeira NF-e emitida no sistema novo sai com número 1** — e
     * colide com as 40.316 notas modelo 55 já autorizadas na Receita para a
     * matriz. `NumeroSequencialService::definir()` existia exatamente para este
     * fim (o docblock diz "ETL importando a numeração da empresa legada"), mas
     * nenhum migrator o chamava.
     *
     * **A regra é `max(contador, maior_emitido)`, e isso não é excesso de zelo.**
     * Em 13 das 14 combinações do dump os dois valores batem; na empresa 2
     * modelo 55 o contador da empresa diz 81.074 enquanto o maior número
     * realmente emitido é 335.358 (o legado reiniciou a série em algum momento).
     * Seguir o contador cegamente faria a matriz emitir nota com número já
     * usado — o pior desfecho possível, porque a SEFAZ rejeita e o erro só
     * aparece na hora de faturar.
     *
     * @return list<string> avisos para o relatório do `etl:run`
     */
    private function semearNumeracaoFiscal(MigrationContext $ctx): array
    {
        $sequencia = app(NumeroSequencialService::class);
        $avisos = [];

        // 1) O que a empresa diz no contador dela.
        $contador = [];
        if ($this->tabelaExiste($ctx, 'empresas')) {
            foreach ($ctx->legado()->table('empresas')->get() as $e) {
                $id = (int) $e->id;
                foreach ([
                    ModeloDocumento::NFE->value => [$e->nfeserie ?? 1, $e->nfenumero ?? 0],
                    ModeloDocumento::NFCE->value => [$e->nfceserie ?? 1, $e->nfcenumero ?? 0],
                ] as $modelo => [$serie, $numero]) {
                    $contador["{$id}:{$modelo}:".(int) $serie] = (int) $numero;
                }
            }
        }

        // 2) O que foi REALMENTE emitido — a verdade que a Receita conhece.
        $emitido = [];
        foreach ($this->destino()->table('notas_fiscais')
            ->selectRaw('empresa_id, modelo, serie, MAX(numero) AS maxnum')
            ->groupBy('empresa_id', 'modelo', 'serie')
            ->get() as $n) {
            $emitido[(int) $n->empresa_id.':'.$n->modelo.':'.(int) $n->serie] = (int) $n->maxnum;
        }

        $idsEmpresa = $this->destino()->table('empresas')->pluck('id')->flip();
        $divergentes = 0;

        foreach (array_unique([...array_keys($contador), ...array_keys($emitido)]) as $chave) {
            [$empresaId, $modelo, $serie] = explode(':', $chave);
            if (! isset($idsEmpresa[(int) $empresaId])) {
                continue;
            }

            $doContador = $contador[$chave] ?? 0;
            $doEmitido = $emitido[$chave] ?? 0;
            $valor = max($doContador, $doEmitido);

            if ($valor === 0) {
                continue; // série nunca usada: deixa a sequência nascer em 1
            }

            if ($doEmitido > $doContador && $doContador > 0) {
                $divergentes++;
                $avisos[] = sprintf(
                    'numeração: empresa %s modelo %s série %s — contador do legado (%d) está ATRÁS '
                    .'do maior número emitido (%d); adotado o maior para não repetir nota',
                    $empresaId, $modelo, $serie, $doContador, $doEmitido,
                );
            }

            $modeloEnum = ModeloDocumento::tryFrom($modelo);
            if ($modeloEnum === null) {
                continue;
            }

            $sequencia->definir(
                $modeloEnum->chaveSequencia((int) $empresaId, (int) $serie),
                $valor,
            );
        }

        $avisos[] = sprintf(
            'numeração fiscal semeada para %d sequência(s) empresa+modelo+série%s',
            count(array_unique([...array_keys($contador), ...array_keys($emitido)])),
            $divergentes > 0 ? " ({$divergentes} com contador defasado)" : '',
        );

        return $avisos;
    }

    private function tabelaExiste(MigrationContext $ctx, string $tabela): bool
    {
        try {
            return $ctx->legado()->getSchemaBuilder()->hasTable($tabela);
        } catch (\Throwable) {
            return false;
        }
    }
}
