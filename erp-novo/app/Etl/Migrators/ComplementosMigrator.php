<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Etl\Support\PreservaIdsDoLegado;
use Illuminate\Support\Facades\DB;

/**
 * Funcionalidades do legado que ficaram de fora da reescrita e ganharam tabela
 * na migration `2026_08_14_000100_tabelas_esquecidas_na_refatoracao`.
 *
 * Cada bloco é independente: origem ausente simplesmente não migra aquele
 * pedaço, sem derrubar os demais. Roda por último porque depende de quase todo
 * o resto (estoque, financeiro, fiscal, cobrança, satélites).
 */
final class ComplementosMigrator implements Migrator
{
    use PreservaIdsDoLegado;

    private ?MigrationContext $ctxAtual = null;

    /** @var list<string> */
    private array $avisos = [];

    private int $lidos = 0;

    private int $gravados = 0;

    private int $pulados = 0;

    public function nome(): string
    {
        return 'complementos';
    }

    public function dependeDe(): array
    {
        return ['estoque', 'financeiro', 'fiscal', 'caixa', 'cobranca', 'satelites'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->ctxAtual = $ctx;
        $this->avisos = [];
        $this->lidos = $this->gravados = $this->pulados = 0;

        $origens = [
            'estoquetransferencias', 'contatransferencias', 'nfemitidavolumes',
            'boletohistoricos', 'estoquesetoracertos', 'valegasvendas',
            'condicaopagamentoparcelas', 'promotorvendas',
        ];
        if (array_filter($origens, fn ($t) => $this->tabelaExiste($ctx, $t)) === []) {
            return new MigrationResult($this->nome(), 0, 0, 0,
                ['legado indisponível ou sem as tabelas complementares']);
        }

        $this->transferenciasEstoque($ctx);
        $this->acertosEstoque($ctx);
        $this->transferenciasConta($ctx);
        $this->volumesNota($ctx);
        $this->historicoBoleto($ctx);
        $this->vendasValeGas($ctx);
        $this->parcelasCondicaoPagamento($ctx);
        $this->visitasPromotor($ctx);

        if ($this->pulados > 0) {
            $this->avisos[] = "{$this->pulados} registro(s) descartado(s): referência "
                .'obrigatória ausente no destino';
        }

        return new MigrationResult(
            migrator: $this->nome(),
            lidos: $this->lidos,
            gravados: $ctx->dryRun ? 0 : $this->gravados,
            pulados: $this->pulados,
            avisos: $this->avisos,
        );
    }

    public function invariantes(): array
    {
        return [];
    }

    private function transferenciasEstoque(MigrationContext $ctx): void
    {
        if (! $this->tabelaExiste($ctx, 'estoquetransferencias')) {
            return;
        }
        $setores = $this->idsDe('setores');
        $users = $this->idsDe('users');
        $empresas = $this->idsDe('empresas');

        $lote = [];
        foreach ($ctx->legado()->table('estoquetransferencias')->orderBy('id')->get() as $r) {
            $this->lidos++;
            $origem = (int) $r->origemsetor_id;
            $destino = (int) $r->destinosetor_id;
            if (! isset($empresas[(int) $r->empresa_id], $setores[$origem], $setores[$destino])) {
                $this->pulados++;

                continue;
            }
            $user = (int) ($r->user_id ?? 0);
            $lote[] = [
                'id' => (int) $r->id,
                'empresa_id' => (int) $r->empresa_id,
                'grupo_id' => (int) $r->grupo_id,
                'origem_setor_id' => $origem,
                'destino_setor_id' => $destino,
                'user_id' => isset($users[$user]) ? $user : null,
                'datahora' => $r->datahora,
                'data_competencia' => $r->datahoracompetencia ?? null,
                'observacoes' => $r->observacoes ?? null,
                'created_at' => $r->created_at ?? null,
            ];
        }
        $this->gravar($ctx, 'estoque_transferencias', $lote);

        // Itens (dependem da transferência gravada).
        if (! $this->tabelaExiste($ctx, 'estoquetransferenciaitems')) {
            return;
        }
        $empresaDaTransf = $this->mapa('estoque_transferencias', 'empresa_id');
        $produtos = $this->idsDe('produtos');

        $ctx->legado()->table('estoquetransferenciaitems')->orderBy('id')->chunk(5000,
            function ($rows) use ($ctx, $empresaDaTransf, $produtos) {
                $lote = [];
                foreach ($rows as $r) {
                    $this->lidos++;
                    $transf = (int) $r->estoquetransferencia_id;
                    $produto = (int) $r->produto_id;
                    if (! isset($empresaDaTransf[$transf], $produtos[$produto])) {
                        $this->pulados++;

                        continue;
                    }
                    $lote[] = [
                        'id' => (int) $r->id,
                        'empresa_id' => $empresaDaTransf[$transf],
                        'estoque_transferencia_id' => $transf,
                        'produto_id' => $produto,
                        'quantidade' => (float) $r->quantidade,
                        'created_at' => $r->created_at ?? null,
                    ];
                }
                $this->gravar($ctx, 'estoque_transferencia_itens', $lote);
            });
    }

    private function acertosEstoque(MigrationContext $ctx): void
    {
        if (! $this->tabelaExiste($ctx, 'estoquesetoracertos')) {
            return;
        }
        $setores = $this->idsDe('setores');
        $produtos = $this->idsDe('produtos');
        $users = $this->idsDe('users');

        $lote = [];
        foreach ($ctx->legado()->table('estoquesetoracertos')->orderBy('id')->get() as $r) {
            $this->lidos++;
            $setor = (int) $r->setor_id;
            $produto = (int) $r->produto_id;
            if (! isset($setores[$setor], $produtos[$produto])) {
                $this->pulados++;

                continue;
            }
            $user = (int) ($r->user_id ?? 0);
            $lote[] = [
                'id' => (int) $r->id,
                'empresa_id' => (int) $r->empresa_id,
                'setor_id' => $setor,
                'produto_id' => $produto,
                'user_id' => isset($users[$user]) ? $user : null,
                'quantidade_anterior' => (float) $r->quantidadeantiga,
                'quantidade_nova' => (float) $r->quantidadenova,
                'observacao' => mb_substr((string) ($r->observacao ?? ''), 0, 255) ?: null,
                'datahora' => $r->datahora,
                'created_at' => $r->created_at ?? null,
            ];
        }
        $this->gravar($ctx, 'estoque_acertos', $lote);
    }

    private function transferenciasConta(MigrationContext $ctx): void
    {
        if (! $this->tabelaExiste($ctx, 'contatransferencias')) {
            return;
        }
        $contas = $this->idsDe('contas');
        $tipos = $this->tabelaDestinoExiste('contamovimentotipos')
            ? $this->idsDe('contamovimentotipos') : [];

        $lote = [];
        foreach ($ctx->legado()->table('contatransferencias')->orderBy('id')->get() as $r) {
            $this->lidos++;
            $origem = (int) $r->origemconta_id;
            $destino = (int) $r->destinoconta_id;
            if (! isset($contas[$origem], $contas[$destino])) {
                $this->pulados++;

                continue;
            }
            $tipo = (int) ($r->contamovimentotipo_id ?? 0);
            $lote[] = [
                'id' => (int) $r->id,
                'empresa_id' => (int) $r->empresa_id,
                'grupo_id' => (int) $r->grupo_id,
                'origem_conta_id' => $origem,
                'destino_conta_id' => $destino,
                'contamovimentotipo_id' => isset($tipos[$tipo]) ? $tipo : null,
                'valor' => round((float) $r->valor, 2),
                'datahora_processo' => $r->datahoraprocesso,
                'created_at' => $r->created_at ?? null,
            ];
        }
        $this->gravar($ctx, 'conta_transferencias', $lote);
    }

    private function volumesNota(MigrationContext $ctx): void
    {
        if (! $this->tabelaExiste($ctx, 'nfemitidavolumes')) {
            return;
        }
        $empresaDaNota = $this->mapa('notas_fiscais', 'empresa_id');
        $produtos = $this->idsDe('produtos');

        $ctx->legado()->table('nfemitidavolumes')->orderBy('id')->chunk(5000,
            function ($rows) use ($ctx, $empresaDaNota, $produtos) {
                $lote = [];
                foreach ($rows as $r) {
                    $this->lidos++;
                    $nota = (int) $r->nfemitida_id;
                    if (! isset($empresaDaNota[$nota])) {
                        $this->pulados++;

                        continue;
                    }
                    $produto = (int) ($r->produto_id ?? 0);
                    $lote[] = [
                        'id' => (int) $r->id,
                        'empresa_id' => $empresaDaNota[$nota],
                        'nota_fiscal_id' => $nota,
                        'produto_id' => isset($produtos[$produto]) ? $produto : null,
                        'quantidade' => (float) ($r->quantidadevolume ?? 0),
                        'especie' => mb_substr((string) ($r->produtoespecie ?? ''), 0, 60) ?: null,
                        'marca' => mb_substr((string) ($r->produtomarca ?? ''), 0, 60) ?: null,
                        'peso_liquido' => (float) ($r->pesoliquido ?? 0),
                        'peso_bruto' => (float) ($r->pesobruto ?? 0),
                        'created_at' => $r->created_at ?? null,
                    ];
                }
                $this->gravar($ctx, 'nota_volumes', $lote);
            });
    }

    private function historicoBoleto(MigrationContext $ctx): void
    {
        if (! $this->tabelaExiste($ctx, 'boletohistoricos')) {
            return;
        }
        $empresaDoBoleto = $this->mapa('boletos', 'empresa_id');
        $users = $this->idsDe('users');

        $ctx->legado()->table('boletohistoricos')->orderBy('id')->chunk(5000,
            function ($rows) use ($ctx, $empresaDoBoleto, $users) {
                $lote = [];
                foreach ($rows as $r) {
                    $this->lidos++;
                    $boleto = (int) $r->boleto_id;
                    if (! isset($empresaDoBoleto[$boleto])) {
                        $this->pulados++;

                        continue;
                    }
                    $user = (int) ($r->alteracaouser_id ?? 0);
                    $lote[] = [
                        'id' => (int) $r->id,
                        'empresa_id' => $empresaDoBoleto[$boleto],
                        'boleto_id' => $boleto,
                        'user_id' => isset($users[$user]) ? $user : null,
                        'nosso_numero' => mb_substr((string) ($r->nossonumero ?? ''), 0, 30) ?: null,
                        'cancelado' => $this->booleano($r->cancelado ?? null),
                        'gerou_boleto' => $this->booleano($r->gerouboleto ?? null),
                        'gerou_remessa' => $this->booleano($r->gerouremessa ?? null),
                        'alterou_vencimento' => $this->booleano($r->alterouvencimento ?? null),
                        'info_cancelamento' => mb_substr((string) ($r->info_cancelamento ?? ''), 0, 255) ?: null,
                        'datahora' => $r->datahora,
                        'created_at' => $r->created_at ?? null,
                    ];
                }
                $this->gravar($ctx, 'boleto_historicos', $lote);
            });
    }

    private function vendasValeGas(MigrationContext $ctx): void
    {
        if (! $this->tabelaExiste($ctx, 'valegasvendas')) {
            return;
        }
        $clientes = $this->idsDe('clientes');
        $produtos = $this->idsDe('produtos');

        $lote = [];
        foreach ($ctx->legado()->table('valegasvendas')->orderBy('id')->get() as $r) {
            $this->lidos++;
            $cliente = (int) $r->cliente_id;
            if (! isset($clientes[$cliente])) {
                $this->pulados++;

                continue;
            }
            $produto = (int) ($r->produto_id ?? 0);
            $lote[] = [
                'id' => (int) $r->id,
                'empresa_id' => (int) $r->empresa_id,
                'grupo_id' => (int) $r->grupo_id,
                'cliente_id' => $cliente,
                'produto_id' => isset($produtos[$produto]) ? $produto : null,
                'quantidade' => (float) ($r->quantidade ?? 0),
                'valor_unitario' => round((float) ($r->valorunitario ?? 0), 2),
                'valor_total' => round((float) ($r->valortotal ?? 0), 2),
                'data_venda' => $r->datavenda ?? null,
                'cancelado' => $this->booleano($r->cancelado ?? null),
                'created_at' => $r->created_at ?? null,
            ];
        }
        $this->gravar($ctx, 'vale_gas_vendas', $lote);
    }

    private function parcelasCondicaoPagamento(MigrationContext $ctx): void
    {
        if (! $this->tabelaExiste($ctx, 'condicaopagamentoparcelas')) {
            return;
        }
        $condicoes = $this->idsDe('condicaopagamentos');

        $lote = [];
        $vistos = [];
        foreach ($ctx->legado()->table('condicaopagamentoparcelas')->orderBy('id')->get() as $r) {
            $this->lidos++;
            $cond = (int) $r->condicaopagamento_id;
            $dias = (int) ($r->dias ?? 0);
            // O destino tem unique (condicao, dias).
            if (! isset($condicoes[$cond]) || isset($vistos[$cond.':'.$dias])) {
                $this->pulados++;

                continue;
            }
            $vistos[$cond.':'.$dias] = true;
            $lote[] = [
                'id' => (int) $r->id,
                'condicaopagamento_id' => $cond,
                'dias' => $dias,
                'percentual_valor' => round((float) ($r->percentualvalor ?? 0), 4),
                'created_at' => $r->created_at ?? null,
            ];
        }
        $this->gravar($ctx, 'condicaopagamento_parcelas', $lote);
    }

    private function visitasPromotor(MigrationContext $ctx): void
    {
        if (! $this->tabelaExiste($ctx, 'promotorvendas')) {
            return;
        }
        $empresas = $this->idsDe('empresas');
        $users = $this->idsDe('users');
        $clientes = $this->idsDe('clientes');
        $cidades = $this->idsDe('cidades');
        $bairros = $this->idsDe('bairros');
        $ruas = $this->idsDe('ruas');
        $setores = $this->idsDe('setores');

        $ctx->legado()->table('promotorvendas')->orderBy('id')->chunk(5000,
            function ($rows) use ($ctx, $empresas, $users, $clientes, $cidades, $bairros, $ruas, $setores) {
                $lote = [];
                foreach ($rows as $r) {
                    $this->lidos++;
                    if (! isset($empresas[(int) $r->empresa_id])) {
                        $this->pulados++;

                        continue;
                    }
                    $lote[] = [
                        'id' => (int) $r->id,
                        'empresa_id' => (int) $r->empresa_id,
                        'user_id' => $this->ou($users, $r->user_id ?? null),
                        'cliente_id' => $this->ou($clientes, $r->cliente_id ?? null),
                        'cidade_id' => $this->ou($cidades, $r->cidade_id ?? null),
                        'bairro_id' => $this->ou($bairros, $r->bairro_id ?? null),
                        'rua_id' => $this->ou($ruas, $r->rua_id ?? null),
                        'setor_id' => $this->ou($setores, $r->setor_id ?? null),
                        'ausente' => $this->booleano($r->ausente ?? null),
                        'uf' => mb_substr((string) ($r->uf ?? ''), 0, 2) ?: null,
                        'numero' => mb_substr((string) ($r->numero ?? ''), 0, 20) ?: null,
                        'complemento' => mb_substr((string) ($r->complemento ?? ''), 0, 120) ?: null,
                        'ponto_referencia' => mb_substr((string) ($r->ponto_referencia ?? ''), 0, 160) ?: null,
                        'created_at' => $r->created_at ?? null,
                    ];
                }
                $this->gravar($ctx, 'promotor_visitas', $lote);
            });
    }

    /** id se existir no conjunto, senão null (FK opcional). */
    private function ou(array $conjunto, mixed $valor): ?int
    {
        $id = (int) ($valor ?? 0);

        return isset($conjunto[$id]) ? $id : null;
    }

    /** @param list<array<string,mixed>> $lote */
    private function gravar(MigrationContext $ctx, string $tabela, array $lote): void
    {
        if ($lote === [] || $ctx->dryRun) {
            return;
        }
        $this->gravados += $this->gravarPreservandoId($tabela, $lote, ['id'], 1000);
    }

    /** @return array<int,int> id => valor da coluna */
    private function mapa(string $tabela, string $coluna): array
    {
        $out = [];
        foreach (DB::table($tabela)->select('id', $coluna)->cursor() as $r) {
            $out[(int) $r->id] = (int) $r->{$coluna};
        }

        return $out;
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

    private function booleano(mixed $v): bool
    {
        $v = mb_strtoupper(trim((string) ($v ?? '')));

        return in_array($v, ['1', 'S', 'T', 'TRUE', 'Y'], true);
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
