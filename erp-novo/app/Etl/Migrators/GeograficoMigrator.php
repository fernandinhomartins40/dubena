<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Etl\Support\PreservaIdsDoLegado;
use Illuminate\Support\Facades\DB;

/**
 * N2 — migra geográfico (cidades/bairros/ruas) do legado. Base do endereço.
 */
final class GeograficoMigrator implements Migrator
{
    use PreservaIdsDoLegado;

    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'geografico';
    }

    public function dependeDe(): array
    {
        return ['empresas']; // precisa de grupos
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->ctxAtual = $ctx;

        $cidades = $this->ler($ctx, 'cidades', ['id', 'grupo_id', 'descricao', 'uf', 'cod_ibge', 'ativo']);
        $bairros = $this->ler($ctx, 'bairros', ['id', 'grupo_id', 'cidade_id', 'descricao', 'ativo']);
        $ruas = $this->ler($ctx, 'ruas', ['id', 'grupo_id', 'cidade_id', 'descricao', 'cep', 'ativo']);

        // 1) Deduplicação PRIMEIRO: o legado repete a mesma cidade em ids
        // diferentes ("Tunas do Paraná" em 522 e 4127882), o que violaria a
        // unique (grupo_id, descricao, uf) do schema novo. Fica a linha
        // canônica (id == cod_ibge, quando existe) e as demais são REMAPEADAS,
        // para não orfanar os bairros/ruas que apontavam para a descartada.
        $canonica = [];
        $remap = [];
        foreach ($cidades as $c) {
            $chave = $c['grupo_id'].'|'.mb_strtolower(trim((string) $c['descricao'])).'|'.$c['uf'];
            $id = (int) $c['id'];
            $ibge = (int) ($c['cod_ibge'] ?? 0);
            if (! isset($canonica[$chave])) {
                $canonica[$chave] = $id;
            } elseif ($id === $ibge) {
                $remap[$canonica[$chave]] = $id;   // a sede assume o lugar
                $canonica[$chave] = $id;
            } else {
                $remap[$id] = $canonica[$chave];
            }
        }
        $duplicadas = count($remap);
        $cidades = array_values(array_filter(
            $cidades,
            fn ($c) => ! isset($remap[(int) $c['id']])
        ));

        $aplicaRemap = function (array $linhas) use ($remap) {
            foreach ($linhas as &$l) {
                $ref = (int) ($l['cidade_id'] ?? 0);
                if ($ref !== 0 && isset($remap[$ref])) {
                    $l['cidade_id'] = $remap[$ref];
                }
            }

            return $linhas;
        };
        $bairros = $aplicaRemap($bairros);
        $ruas = $aplicaRemap($ruas);

        // 2) No legado, `bairros.cidade_id`/`ruas.cidade_id` guardam o CÓDIGO
        // IBGE, não a PK de `cidades` — a FK do schema novo aponta para a PK.
        // Sem esta tradução a carga quebra (ex.: 4109401 = Guarapuava).
        // Atenção: distritos compartilham o cod_ibge do município-sede
        // (Palmeirinha e Colônia Vitória usam o de Guarapuava). Quando há
        // empate, vence a linha cujo id É o próprio código IBGE — a sede.
        $porIbge = [];
        foreach ($cidades as $c) {
            $ibge = (int) ($c['cod_ibge'] ?? 0);
            if ($ibge === 0) {
                continue;
            }
            $id = (int) $c['id'];
            if (! isset($porIbge[$ibge]) || $id === $ibge) {
                $porIbge[$ibge] = $id;
            }
        }
        $idsCidade = array_flip(array_map(fn ($c) => (int) $c['id'], $cidades));

        $traduz = function (array $linhas) use ($porIbge, $idsCidade, &$semCidade) {
            foreach ($linhas as &$l) {
                $ref = isset($l['cidade_id']) ? (int) $l['cidade_id'] : 0;
                if ($ref === 0 || isset($idsCidade[$ref])) {
                    continue; // já é PK válida
                }
                if (isset($porIbge[$ref])) {
                    $l['cidade_id'] = $porIbge[$ref];
                } else {
                    // Cidade referenciada não veio no dump. `cidade_id` é NOT
                    // NULL no destino, então a linha é DESCARTADA (marcada) em
                    // vez de inventar uma FK ou derrubar a carga inteira.
                    $l['cidade_id'] = null;
                    $semCidade++;
                }
            }

            return $linhas;
        };

        $semCidade = 0;
        $semFk = fn (array $l) => $l['cidade_id'] === null;
        $bairros = array_values(array_filter($traduz($bairros), fn ($l) => ! $semFk($l)));
        $ruas = array_values(array_filter($traduz($ruas), fn ($l) => ! $semFk($l)));

        $gravados = 0;
        if (! $ctx->dryRun) {
            // Os ids do legado TÊM de ser preservados: bairros/ruas (e mais
            // adiante clientes e pedidos) referenciam a cidade por esse id.
            // `updateOrCreate` deixaria o auto-increment escolher outro valor e
            // as FKs do dump apontariam para a linha errada.
            $gravados += $this->gravarPreservandoId('cidades', $cidades);
            $gravados += $this->gravarPreservandoId('bairros', $bairros);
            $gravados += $this->gravarPreservandoId('ruas', $ruas);
        }

        $avisos = [];
        if ($duplicadas > 0) {
            $avisos[] = "{$duplicadas} cidade(s) duplicada(s) no legado "
                .'(mesma descrição/UF em ids diferentes) — unificadas';
        }
        if ($semCidade > 0) {
            $avisos[] = "{$semCidade} bairro(s)/rua(s) referenciam cidade ausente "
                .'no dump — DESCARTADOS (cidade_id é obrigatório)';
        }

        return new MigrationResult(
            migrator: $this->nome(),
            lidos: count($cidades) + count($bairros) + count($ruas) + $semCidade,
            gravados: $ctx->dryRun ? 0 : $gravados,
            pulados: $semCidade,
            avisos: $avisos,
        );
    }

    public function invariantes(): array
    {
        $ctx = $this->ctxAtual ?? new MigrationContext();
        if (! $this->legadoDisponivel($ctx)) {
            return [];
        }

        return [
            // Divergência de −1 EXPLICADA (T2.5), e não é perda: "Tunas do
            // Paraná" existe no destino com id 4127882 (o código IBGE) em vez
            // do id 522 do legado — este migrator usa o código IBGE como
            // identificador, que é o certo (chave nacional estável). O EXCEPT
            // por id acusa ausência; o EXCEPT por (descrição, uf) não acusa
            // nenhuma.
            //
            //   SELECT d.id, d.descricao FROM public.cidades d
            //     JOIN legado.cidades a ON upper(trim(d.descricao)) = upper(trim(a.descricao))
            //    WHERE a.id = '522';   -- devolve id 4127882
            //
            // O descarte é a linha do legado cujo id foi substituído pelo IBGE.
            new CountInvariant(
                $ctx, 'cidades', 'cidades',
                descartesEsperados: fn () => $this->cidadesComIdSubstituidoPeloIbge($ctx),
            ),
            new CountInvariant($ctx, 'bairros', 'bairros'),
            new CountInvariant($ctx, 'ruas', 'ruas'),
        ];
    }

    /**
     * Cidades do legado cujo id NÃO aparece no destino porque a linha foi
     * gravada sob o código IBGE.
     *
     * Confere que a cidade de fato existe lá (por descrição+UF): se não
     * existisse, seria perda real e a invariante deve continuar falhando.
     */
    private function cidadesComIdSubstituidoPeloIbge(MigrationContext $ctx): int
    {
        try {
            $origem = $ctx->legado()->table('cidades')->get(['id', 'descricao', 'uf']);
        } catch (\Throwable) {
            return 0;
        }

        $idsDestino = DB::table('cidades')->pluck('id')
            ->map(fn ($v) => (string) $v)->flip();

        $destinoPorNome = [];
        foreach (DB::table('cidades')->get(['descricao', 'uf']) as $c) {
            $destinoPorNome[$this->chaveCidade($c->descricao, $c->uf)] = true;
        }

        $remapeadas = 0;
        foreach ($origem as $c) {
            if (isset($idsDestino[(string) $c->id])) {
                continue;
            }

            // Só conta como remapeamento se a cidade REALMENTE está lá sob
            // outro id — caso contrário é ausência de verdade.
            if (isset($destinoPorNome[$this->chaveCidade($c->descricao, $c->uf)])) {
                $remapeadas++;
            }
        }

        return $remapeadas;
    }

    private function chaveCidade(?string $descricao, ?string $uf): string
    {
        return mb_strtoupper(trim((string) $descricao)).'|'.mb_strtoupper(trim((string) $uf));
    }

    /**
     * @param list<string> $colunas
     * @return list<array<string, mixed>>
     */
    private function ler(MigrationContext $ctx, string $tabela, array $colunas): array
    {
        try {
            // Pede só as colunas que a origem realmente tem: nem toda tabela do
            // legado traz todas (ex.: `cidades` não tem `ativo`). Selecionar uma
            // coluna inexistente derrubaria a leitura inteira.
            $existentes = $ctx->legado()->getSchemaBuilder()->getColumnListing($tabela);
            if ($existentes !== []) {
                $colunas = array_values(array_intersect($colunas, $existentes));
            }
            if ($colunas === []) {
                return [];
            }
            $rows = $ctx->legado()->table($tabela)->get($colunas);
        } catch (\Throwable) {
            return [];
        }

        // Grupo de fallback: no legado parte das cidades é global (grupo_id
        // nulo), mas a coluna é NOT NULL no schema novo.
        $grupoPadrao = (int) ($ctx->novo()->table('grupos')->min('id') ?? 1);

        return $rows->map(function ($r) use ($grupoPadrao) {
            $linha = (array) $r;
            if (isset($linha['ativo'])) {
                $linha['ativo'] = (bool) $linha['ativo'];
            }
            if (array_key_exists('grupo_id', $linha)) {
                $linha['grupo_id'] = (int) ($linha['grupo_id'] ?? 0) ?: $grupoPadrao;
            }
            if (isset($linha['cod_ibge'])) {
                $linha['cod_ibge'] = $linha['cod_ibge'] !== null ? (int) $linha['cod_ibge'] : null;
            }

            return $linha;
        })->all();
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
}
