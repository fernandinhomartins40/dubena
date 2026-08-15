<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Models\Apoio\Banco;
use App\Models\Apoio\ClienteContatoSituacao;
use App\Models\Apoio\ClienteContatoTipo;
use App\Models\Apoio\ContaMovimentoTipo;
use App\Models\Apoio\Segmento;
use App\Models\Apoio\TelefoneTipo;
use App\Models\Apoio\TipoPessoa;

/**
 * N1 — migra os cadastros de apoio (escopo por grupo) do legado para o schema novo.
 *
 * Cada cadastro tem mesma forma (id/grupo_id/descricao/ativo + extras). O legado
 * guarda flags como string '0'/'1' (Oracle) → aqui viram boolean nativo. Sem dump
 * disponível, cada cadastro fica vazio (0 lidos/gravados) e a invariante confere 0=0.
 */
final class CadastrosApoioMigrator implements Migrator
{
    private ?MigrationContext $ctxAtual = null;

    /**
     * tabela legado → [model novo, colunas extras].
     *
     * @var array<string, array{model: class-string, extras: list<string>, bools: list<string>}>
     */
    private const MAPA = [
        'segmentos' => ['model' => Segmento::class, 'extras' => [], 'bools' => []],
        'tipopessoas' => ['model' => TipoPessoa::class, 'extras' => ['tipopessoacadastro'], 'bools' => []],
        'telefonetipos' => ['model' => TelefoneTipo::class, 'extras' => ['celular'], 'bools' => ['celular']],
        'clientecontatotipos' => ['model' => ClienteContatoTipo::class, 'extras' => [], 'bools' => []],
        'clientecontatosituacaos' => ['model' => ClienteContatoSituacao::class, 'extras' => [], 'bools' => []],
        'bancos' => ['model' => Banco::class, 'extras' => ['codigo', 'site'], 'bools' => []],
        'contamovimentotipos' => ['model' => ContaMovimentoTipo::class, 'extras' => ['pagarreceber', 'cheque', 'cartao', 'valegas', 'convenio'], 'bools' => ['cheque', 'cartao', 'valegas', 'convenio']],
    ];

    public function nome(): string
    {
        return 'cadastros-apoio';
    }

    public function dependeDe(): array
    {
        return ['empresas']; // precisa de grupos existentes (criados junto às empresas)
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->ctxAtual = $ctx;
        $lidos = 0;
        $gravados = 0;

        foreach (self::MAPA as $tabela => $cfg) {
            $linhas = $this->lerLegado($ctx, $tabela, $cfg);
            $lidos += count($linhas);

            if ($ctx->dryRun) {
                continue;
            }

            foreach ($linhas as $l) {
                $cfg['model']::withoutGrupo()->updateOrCreate(
                    ['grupo_id' => $l['grupo_id'], 'descricao' => $l['descricao']],
                    $l,
                );
                $gravados++;
            }
        }

        return new MigrationResult(
            migrator: $this->nome(),
            lidos: $lidos,
            gravados: $ctx->dryRun ? 0 : $gravados,
            pulados: 0,
        );
    }

    public function invariantes(): array
    {
        $ctx = $this->ctxAtual ?? new MigrationContext();
        if (! $this->legadoDisponivel($ctx)) {
            return []; // sem dump não há o que comparar (ambiente dev/CI)
        }

        $invs = [];
        foreach (self::MAPA as $tabela => $cfg) {
            // Compara contagem origem (tabela legado) × destino (tabela do model novo).
            $destino = (new $cfg['model']())->getTable();
            $invs[] = new CountInvariant($ctx, $tabela, $destino);
        }

        return $invs;
    }

    private function legadoDisponivel(MigrationContext $ctx): bool
    {
        try {
            $ctx->legado()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array{model: class-string, extras: list<string>, bools: list<string>} $cfg
     * @return list<array<string, mixed>>
     */
    private function lerLegado(MigrationContext $ctx, string $tabela, array $cfg): array
    {
        try {
            $colunas = array_merge(['id', 'grupo_id', 'descricao', 'ativo'], $cfg['extras']);
            $rows = $ctx->legado()->table($tabela)->get($colunas);
        } catch (\Throwable) {
            return []; // legado indisponível neste ambiente
        }

        // Grupo de fallback: cadastros "globais" do legado (BANCOS vem com
        // grupo_id=0/null) caem no primeiro grupo — a FK do destino é NOT NULL.
        $grupoPadrao = (int) (\Illuminate\Support\Facades\DB::table('grupos')->min('id') ?? 1);

        return $rows->map(function ($r) use ($cfg, $grupoPadrao) {
            $linha = [
                'grupo_id' => (int) ($r->grupo_id ?? 0) ?: $grupoPadrao,
                'descricao' => trim((string) $r->descricao),
                'ativo' => (bool) $r->ativo,
            ];
            foreach ($cfg['extras'] as $campo) {
                $valor = $r->{$campo} ?? null;
                $linha[$campo] = in_array($campo, $cfg['bools'], true) ? (bool) $valor : $valor;
            }

            return $linha;
        })->all();
    }
}
