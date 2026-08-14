<?php

namespace App\Etl\Migrators;

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

        $avisos = [];
        if ($pulados > 0) {
            $avisos[] = "{$pulados} boleto(s) descartado(s): sem parcela correspondente "
                .'no financeiro (valor e vencimento vêm dela)';
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
        $ctx = $this->ctxAtual ?? new MigrationContext();

        return [new CountInvariant($ctx, 'boletos', 'boletos')];
    }

    /** Estado do boleto a partir das flags do legado e da baixa da parcela. */
    private function situacao(object $r, bool $baixado): string
    {
        if ($this->booleano($r->cancelado ?? null)) {
            return 'CANCELADO';
        }
        if ($baixado) {
            return 'PAGO';
        }

        return 'PENDENTE';
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
