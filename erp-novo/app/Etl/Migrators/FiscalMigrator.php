<?php

namespace App\Etl\Migrators;

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

        // ── Notas emitidas ──
        $ctx->legado()->table('nfemitidas')->orderBy('id')->chunk(2000,
            function ($rows) use (&$lidos, &$gravados, &$pulados, &$chavesUsadas, $ctx, $idsEmpresa, $idsCliente) {
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

                    $lote[] = [
                        'id' => (int) $r->id,
                        'empresa_id' => $empresa,
                        'grupo_id' => (int) $r->grupo_id,
                        'cliente_id' => isset($idsCliente[$cliente]) ? $cliente : null,
                        'modelo' => mb_substr((string) $r->nfmodelo, 0, 4),
                        'tipo' => 'S',   // emitida = saída
                        'serie' => (int) preg_replace('/\D/', '', (string) $r->nfserie) ?: 1,
                        'numero' => (int) $r->nfnumero,
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
            $fallbackProduto = (int) (DB::table('produtos')->min('id') ?? 0);
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
        $ctx = $this->ctxAtual ?? new MigrationContext();

        return [
            new CountInvariant($ctx, 'nfemitidas', 'notas_fiscais'),
        ];
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
        foreach (DB::table('notas_fiscais')->select('id', 'empresa_id')->cursor() as $n) {
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
        foreach (DB::table('produtos')->select('id', 'descricao')->cursor() as $p) {
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
        foreach (DB::table($tabela)->select('id')->cursor() as $r) {
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

    private function tabelaExiste(MigrationContext $ctx, string $tabela): bool
    {
        try {
            return $ctx->legado()->getSchemaBuilder()->hasTable($tabela);
        } catch (\Throwable) {
            return false;
        }
    }
}
