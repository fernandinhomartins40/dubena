<?php

namespace App\Etl\Migrators;

use App\Domain\Cobranca\SituacaoBoleto;
use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Etl\Support\PreservaIdsDoLegado;
use Illuminate\Support\Facades\DB;

/**
 * N7 — cobrança bancária (boletos).
 *
 * O boleto do legado NÃO guarda valor nem vencimento: são da parcela do
 * financeiro que ele cobra (`financeiroparcela_id`). Por isso este migrador
 * depende de `financeiro` e busca valor/vencimento lá — boleto sem parcela
 * correspondente é descartado (as duas colunas são NOT NULL no destino).
 *
 * `banco_codigo` também não está no boleto: vem da conta emissora.
 */
final class CobrancaMigrator implements Migrator
{
    use PreservaIdsDoLegado;

    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'cobranca';
    }

    public function dependeDe(): array
    {
        return ['financeiro', 'caixa'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->ctxAtual = $ctx;

        if (! $this->tabelaExiste($ctx, 'boletos')) {
            return new MigrationResult($this->nome(), 0, 0, 0,
                ['legado sem `boletos` — nada a migrar']);
        }

        $idsEmpresa = $this->idsDe('empresas');
        $parcelas = $this->dadosDaParcela();
        $bancoDaConta = $this->bancoPorConta($ctx);

        $lidos = 0;
        $gravados = 0;
        $pulados = 0;

        $ctx->legado()->table('boletos')->orderBy('id')->chunk(2000,
            function ($rows) use (
                &$lidos, &$gravados, &$pulados, $ctx, $idsEmpresa, $parcelas, $bancoDaConta
            ) {
                $lote = [];
                foreach ($rows as $r) {
                    $lidos++;
                    $empresa = (int) $r->empresa_id;
                    $parcelaId = (int) ($r->financeiroparcela_id ?? 0);
                    $parcela = $parcelas[$parcelaId] ?? null;

                    // valor e vencimento são NOT NULL e só existem na parcela.
                    if (! isset($idsEmpresa[$empresa]) || $parcela === null) {
                        $pulados++;

                        continue;
                    }

                    $lote[] = [
                        'id' => (int) $r->id,
                        'empresa_id' => $empresa,
                        'financeiroparcela_id' => $parcelaId,
                        'cliente_id' => $parcela['cliente_id'],
                        'banco_codigo' => $bancoDaConta[(int) $r->conta_id] ?? 0,
                        'nosso_numero' => mb_substr((string) ($r->nossonumero ?? ''), 0, 30) ?: null,
                        'valor' => $parcela['valor'],
                        'vencimento' => $parcela['vencimento'],
                        'situacao' => $this->situacao($r, $parcela['baixado']),
                        'created_at' => $r->datahora ?? $r->created_at ?? null,
                    ];
                }
                if ($lote !== [] && ! $ctx->dryRun) {
                    $gravados += $this->gravarPreservandoId('boletos', $lote, ['id'], 1000);
                }
            });

        // ── PIX do legado + remessas CNAB (pós-auditoria 2026-08-14) ──
        [$l2, $g2, $p2] = $this->pixLegado($ctx);
        $lidos += $l2;
        $gravados += $g2;
        $pulados += $p2;
        [$l3, $g3] = $this->remessasCnab($ctx, $bancoDaConta);
        $lidos += $l3;
        $gravados += $g3;

        $avisos = [];
        if ($pulados > 0) {
            $avisos[] = "{$pulados} boleto(s)/pix descartado(s): sem parcela/pedido "
                .'correspondente no destino';
        }

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
            // Descarte ESTRUTURAL, comprovado (T2.5): 409 boletos do legado não
            // chegam ao destino, e são exatamente os que estão cancelados E sem
            // parcela financeira. `valor` e `vencimento` são NOT NULL no destino
            // e só existem na parcela (ver a regra em `migrarBoletos`, o
            // `continue` quando $parcela === null) — sem ela o boleto não tem
            // como ser representado.
            //
            // Query que comprova a correlação de 100% (409 = 409 = 409):
            //   WITH aus AS (SELECT id FROM legado.boletos
            //                EXCEPT SELECT id FROM public.boletos)
            //   SELECT count(*) FILTER (WHERE cancelado::text IN ('1','t','true')),
            //          count(*) FILTER (WHERE financeiroparcela_id IS NULL)
            //     FROM legado.boletos b JOIN aus ON aus.id = b.id;
            //
            // Closure e não número fixo: recalcula a cada recarga em vez de
            // petrificar um valor medido uma única vez.
            new CountInvariant(
                $ctx, 'boletos', 'boletos',
                descartesEsperados: fn () => (int) $ctx->legado()->table('boletos')
                    ->whereNull('financeiroparcela_id')
                    ->whereIn(DB::raw('cancelado::text'), ['1', 't', 'true'])
                    ->count(),
            ),
        ];
    }

    /**
     * Cobranças PIX do legado (PIXTRANSACTIONS, 4.961 no dump auditado) →
     * pix_cobrancas. Empresa vem do pedido; txid é obrigatório no destino.
     *
     * @return array{0:int,1:int,2:int} [lidos, gravados, pulados]
     */
    private function pixLegado(MigrationContext $ctx): array
    {
        if (! $this->tabelaExiste($ctx, 'pixtransactions')) {
            return [0, 0, 0];
        }

        $empresaDoPedido = [];
        foreach (DB::table('pedidos')->select('id', 'empresa_id', 'cliente_id')->cursor() as $p) {
            $empresaDoPedido[(int) $p->id] = [(int) $p->empresa_id, $p->cliente_id !== null ? (int) $p->cliente_id : null];
        }
        $empresaPadrao = (int) (DB::table('empresas')->orderByDesc('matriz')->orderBy('id')->value('id') ?? 0);

        $lidos = 0;
        $gravados = 0;
        $pulados = 0;
        $lote = [];
        foreach ($ctx->legado()->table('pixtransactions')->orderBy('id')->get() as $r) {
            $lidos++;
            $txid = trim((string) ($r->txid ?? ''));
            if ($txid === '' || $empresaPadrao === 0) {
                $pulados++;

                continue;
            }
            $pedido = (int) ($r->pedido_id ?? 0);
            [$empresa, $cliente] = $empresaDoPedido[$pedido] ?? [$empresaPadrao, null];

            $lote[] = [
                'id' => (int) $r->id,
                'empresa_id' => $empresa,
                'pedido_id' => isset($empresaDoPedido[$pedido]) ? $pedido : null,
                'cliente_id' => $cliente,
                'txid' => mb_substr($txid, 0, 60),
                'e2eid' => ($r->endtoendid ?? null) !== null ? mb_substr((string) $r->endtoendid, 0, 60) : null,
                'valor' => round((float) ($r->valor ?? 0), 2),
                'copia_e_cola' => $r->pixcopiaecola ?? null,
                'expira_em' => $r->expires_at ?? null,
                'situacao' => $this->situacaoPix($r->status ?? null),
                'pago_em' => $r->datapagamento ?? null,
                'created_at' => $r->created_at ?? null,
            ];
        }
        if ($lote !== [] && ! $ctx->dryRun) {
            $gravados += $this->gravarPreservandoId('pix_cobrancas', $lote, ['id'], 1000);
        }

        return [$lidos, $gravados, $pulados];
    }

    /**
     * Status PIX do PSP → vocabulário do destino (varchar(20)). O legado grava o
     * status cru do Itaú — "REMOVIDA_PELO_USUARIO_RECEBEDOR" estoura a coluna.
     */
    private function situacaoPix(mixed $status): string
    {
        $s = mb_strtoupper(trim((string) ($status ?? '')));

        return match (true) {
            $s === '' => 'DESCONHECIDA',
            str_starts_with($s, 'REMOVIDA') => 'CANCELADA',
            default => mb_substr($s, 0, 20),
        };
    }

    /**
     * Remessas CNAB do legado → remessas_cnab. O total/valor vêm dos vínculos
     * (BOLETOREMESSAFINANCEIROS) somados sobre as parcelas já migradas.
     *
     * @param  array<int,int>  $bancoDaConta
     * @return array{0:int,1:int} [lidos, gravados]
     */
    private function remessasCnab(MigrationContext $ctx, array $bancoDaConta): array
    {
        if (! $this->tabelaExiste($ctx, 'boletoremessas')) {
            return [0, 0];
        }

        // Agregado por remessa: nº de boletos e soma das parcelas cobradas.
        $porRemessa = [];
        if ($this->tabelaExiste($ctx, 'boletoremessafinanceiros')) {
            $valorParcela = [];
            foreach (DB::table('financeiroparcelas')->select('id', 'valor')->cursor() as $p) {
                $valorParcela[(int) $p->id] = (float) $p->valor;
            }
            foreach ($ctx->legado()->table('boletoremessafinanceiros')
                ->get(['boletoremessa_id', 'financeiroparcela_id', 'cancelado']) as $v) {
                if ($this->booleano($v->cancelado ?? null)) {
                    continue;
                }
                $id = (int) $v->boletoremessa_id;
                $porRemessa[$id]['n'] = ($porRemessa[$id]['n'] ?? 0) + 1;
                $porRemessa[$id]['valor'] = ($porRemessa[$id]['valor'] ?? 0.0)
                    + ($valorParcela[(int) ($v->financeiroparcela_id ?? 0)] ?? 0.0);
            }
        }

        $empresaDaConta = [];
        foreach (DB::table('contas')->select('id', 'empresa_id')->cursor() as $c) {
            $empresaDaConta[(int) $c->id] = (int) $c->empresa_id;
        }
        $empresaPadrao = (int) (DB::table('empresas')->min('id') ?? 0);

        $lidos = 0;
        $gravados = 0;
        $lote = [];
        foreach ($ctx->legado()->table('boletoremessas')->orderBy('id')->get() as $r) {
            $lidos++;
            $conta = (int) ($r->conta_id ?? 0);
            $agg = $porRemessa[(int) $r->id] ?? ['n' => 0, 'valor' => 0.0];
            $lote[] = [
                'id' => (int) $r->id,
                'empresa_id' => $empresaDaConta[$conta] ?? $empresaPadrao,
                'banco_codigo' => $bancoDaConta[$conta] ?? 0,
                'numero_remessa' => (int) ($r->numerosequencia ?? $r->id),
                'total_boletos' => (int) $agg['n'],
                'valor_total' => round((float) $agg['valor'], 2),
                'situacao' => $this->booleano($r->cancelado ?? null)
                    ? 'CANCELADA'
                    : ($this->booleano($r->efetivado ?? null) ? 'EFETIVADA' : 'GERADA'),
                'created_at' => $r->datahora ?? $r->created_at ?? null,
            ];
        }
        if ($lote !== [] && ! $ctx->dryRun && $empresaPadrao > 0) {
            $gravados += $this->gravarPreservandoId('remessas_cnab', $lote, ['id'], 1000);
        }

        return [$lidos, $gravados];
    }

    /**
     * Estado do boleto a partir das flags do legado e da baixa da parcela.
     *
     * Usa o enum `SituacaoBoleto` como fonte da verdade — gravar uma string
     * solta aqui produz um erro TARDIO e obscuro: a carga passa, e o 500
     * ("X is not a valid backing value for enum") só aparece quando alguém
     * abre a tela. Foi o que aconteceu com "PAGO" (o correto é LIQUIDADO).
     */
    private function situacao(object $r, bool $baixado): string
    {
        if ($this->booleano($r->cancelado ?? null)) {
            return SituacaoBoleto::CANCELADO->value;
        }
        if ($baixado) {
            return SituacaoBoleto::LIQUIDADO->value;
        }
        if ($this->booleano($r->gerouremessa ?? null)) {
            return SituacaoBoleto::REGISTRADO->value;
        }

        return SituacaoBoleto::PENDENTE->value;
    }

    /**
     * parcela_id => valor, vencimento, baixado e cliente do título.
     *
     * @return array<int, array{valor:float, vencimento:string, baixado:bool, cliente_id:?int}>
     */
    private function dadosDaParcela(): array
    {
        $out = [];
        DB::table('financeiroparcelas as p')
            ->leftJoin('financeiros as f', 'f.id', '=', 'p.financeiro_id')
            ->select('p.id', 'p.valor', 'p.vencimento', 'p.baixado', 'f.cliente_id')
            ->orderBy('p.id')
            ->chunk(20000, function ($rows) use (&$out) {
                foreach ($rows as $p) {
                    $out[(int) $p->id] = [
                        'valor' => round((float) $p->valor, 2),
                        'vencimento' => $p->vencimento,
                        'baixado' => (bool) $p->baixado,
                        'cliente_id' => $p->cliente_id !== null ? (int) $p->cliente_id : null,
                    ];
                }
            });

        return $out;
    }

    /** @return array<int,int> conta_id => código do banco */
    private function bancoPorConta(MigrationContext $ctx): array
    {
        $out = [];
        if (! $this->tabelaExiste($ctx, 'contas')) {
            return $out;
        }
        foreach ($ctx->legado()->table('contas')->select('id', 'banco_id')->get() as $c) {
            $out[(int) $c->id] = (int) ($c->banco_id ?? 0);
        }

        return $out;
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

    private function tabelaExiste(MigrationContext $ctx, string $tabela): bool
    {
        try {
            return $ctx->legado()->getSchemaBuilder()->hasTable($tabela);
        } catch (\Throwable) {
            return false;
        }
    }
}
