<?php

namespace App\Domain\Apoio;

use App\Domain\Identidade\NormalizadorTexto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Detecção de inconsistências de cadastro (F11) — ruas/bairros DUPLICADOS por
 * similaridade de nome, dentro da mesma cidade. Substitui o
 * `UTL_MATCH.JARO_WINKLER_SIMILARITY` (Oracle-only) do legado por similaridade
 * AGNÓSTICA de banco (Levenshtein normalizado, em PHP) — funciona em
 * Postgres/MySQL/SQLite sem extensão. Escopo por grupo.
 *
 * Em produção (Postgres) pode-se trocar por pg_trgm/similarity para escala; aqui o
 * cálculo em PHP cobre os volumes típicos e é testável no CI.
 */
class InconsistenciaService
{
    /** Limiar de similaridade (0..1) para considerar duplicata provável. */
    private const LIMIAR = 0.85;

    /**
     * Pares de prováveis duplicatas de RUA por cidade (mesmo grupo).
     *
     * @return list<array{tipo:string, cidade_id:int, a:array{id:int,descricao:string}, b:array{id:int,descricao:string}, similaridade:float}>
     */
    public function ruas(int $grupoId): array
    {
        return $this->duplicatas('ruas', 'rua', $grupoId);
    }

    /** Pares de prováveis duplicatas de BAIRRO por cidade (mesmo grupo). */
    public function bairros(int $grupoId): array
    {
        return $this->duplicatas('bairros', 'bairro', $grupoId);
    }

    /** Tudo junto (ruas + bairros). */
    public function todas(int $grupoId): array
    {
        return array_merge($this->ruas($grupoId), $this->bairros($grupoId));
    }

    /**
     * Marca um par como NÃO-duplicado (T4.1) — a ação que fecha o ciclo.
     *
     * Sem ela, a tela de inconsistências é um relatório que repete os mesmos
     * falsos positivos para sempre: o operador olha, conclui "essas duas ruas
     * são mesmo diferentes", e não tem como registrar isso. A fila nunca esvazia.
     *
     * O par é gravado NORMALIZADO (menor id primeiro): sem isso, (A,B) e (B,A)
     * seriam linhas distintas e o mesmo par voltaria à fila pela ordem inversa.
     *
     * @param  'rua'|'bairro'  $tipo
     * @return bool false se o par já estava ignorado (idempotente)
     */
    public function ignorarPar(
        string $tipo,
        int $itemId,
        int $itemIgnoradoId,
        int $grupoId,
        ?int $empresaId = null,
        ?int $userId = null,
        ?string $motivo = null,
    ): bool {
        if (! in_array($tipo, ['rua', 'bairro'], true)) {
            throw new \InvalidArgumentException("Tipo inválido: {$tipo} (use 'rua' ou 'bairro').");
        }

        if ($itemId === $itemIgnoradoId) {
            throw new \InvalidArgumentException('Um registro não pode ser marcado como duplicata de si mesmo.');
        }

        [$a, $b] = $this->parNormalizado($itemId, $itemIgnoradoId);

        $tabela = $tipo === 'rua' ? 'ruas' : 'bairros';
        $existentes = DB::table($tabela)
            ->where('grupo_id', $grupoId)
            ->whereIn('id', [$a, $b])
            ->count();

        // Não aceita ignorar par de outro tenant nem id inexistente — a rota é
        // autorizada, mas o id vem do cliente.
        if ($existentes !== 2) {
            throw new \InvalidArgumentException(
                "Par inválido: {$tipo} {$a}/{$b} não existe neste grupo."
            );
        }

        return DB::transaction(function () use ($tipo, $a, $b, $grupoId, $empresaId, $userId, $motivo) {
            $jaExiste = DB::table('geo_pares_ignorados')
                ->where('grupo_id', $grupoId)
                ->where('tipo', $tipo)
                ->where('item_id', $a)
                ->where('item_ignorado_id', $b)
                ->exists();

            if ($jaExiste) {
                return false;
            }

            DB::table('geo_pares_ignorados')->insert([
                'tipo' => $tipo,
                'item_id' => $a,
                'item_ignorado_id' => $b,
                'grupo_id' => $grupoId,
                'empresa_id' => $empresaId,
                'user_id' => $userId,
                'motivo' => $motivo !== null ? mb_substr($motivo, 0, 255) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        });
    }

    /** Desfaz o "ignorar": o par volta para a fila de conferência. */
    public function reconsiderarPar(string $tipo, int $itemId, int $itemIgnoradoId, int $grupoId): bool
    {
        [$a, $b] = $this->parNormalizado($itemId, $itemIgnoradoId);

        return DB::table('geo_pares_ignorados')
            ->where('grupo_id', $grupoId)
            ->where('tipo', $tipo)
            ->where('item_id', $a)
            ->where('item_ignorado_id', $b)
            ->delete() > 0;
    }

    /** @return array{0:int,1:int} par com o menor id primeiro */
    private function parNormalizado(int $x, int $y): array
    {
        return $x <= $y ? [$x, $y] : [$y, $x];
    }

    /**
     * Pares já ignorados, indexados por "tipo|menor|maior" para busca O(1).
     *
     * @return array<string,true>
     */
    private function ignorados(int $grupoId): array
    {
        if (! Schema::hasTable('geo_pares_ignorados')) {
            return [];
        }

        $mapa = [];
        foreach (
            DB::table('geo_pares_ignorados')
                ->where('grupo_id', $grupoId)
                ->get(['tipo', 'item_id', 'item_ignorado_id']) as $linha
        ) {
            $mapa[$linha->tipo.'|'.$linha->item_id.'|'.$linha->item_ignorado_id] = true;
        }

        return $mapa;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function duplicatas(string $tabela, string $tipo, int $grupoId): array
    {
        $registros = DB::table($tabela)
            ->where('grupo_id', $grupoId)
            ->get(['id', 'cidade_id', 'descricao']);

        // Pares que o operador já conferiu e marcou como distintos (T4.1). É o
        // que transforma a tela de relatório em FILA DE TRABALHO: sem esta
        // exclusão, os mesmos falsos positivos voltam a cada consulta.
        $ignorados = $this->ignorados($grupoId);

        // Agrupa por cidade e compara cada par dentro da cidade.
        $porCidade = [];
        foreach ($registros as $r) {
            $porCidade[(int) $r->cidade_id][] = $r;
        }

        $pares = [];
        foreach ($porCidade as $cidadeId => $itens) {
            $n = count($itens);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    [$menor, $maior] = $this->parNormalizado((int) $itens[$i]->id, (int) $itens[$j]->id);
                    if (isset($ignorados["{$tipo}|{$menor}|{$maior}"])) {
                        continue;
                    }

                    $sim = $this->similaridade((string) $itens[$i]->descricao, (string) $itens[$j]->descricao);
                    if ($sim >= self::LIMIAR && $sim < 1.0) {
                        $pares[] = [
                            'tipo' => $tipo,
                            'cidade_id' => $cidadeId,
                            'a' => ['id' => (int) $itens[$i]->id, 'descricao' => (string) $itens[$i]->descricao],
                            'b' => ['id' => (int) $itens[$j]->id, 'descricao' => (string) $itens[$j]->descricao],
                            'similaridade' => round($sim, 3),
                        ];
                    }
                }
            }
        }

        // Mais similares primeiro.
        usort($pares, fn ($x, $y) => $y['similaridade'] <=> $x['similaridade']);

        return $pares;
    }

    /** Similaridade 0..1 por Levenshtein normalizado sobre strings normalizadas. */
    private function similaridade(string $a, string $b): float
    {
        $a = $this->normalizar($a);
        $b = $this->normalizar($b);
        if ($a === '' || $b === '') {
            return 0.0;
        }
        if ($a === $b) {
            return 1.0;
        }
        $max = max(strlen($a), strlen($b));

        return $max === 0 ? 0.0 : 1.0 - (levenshtein($a, $b) / $max);
    }

    /**
     * F6-06A — o normalizador canônico, e não uma cópia local com `iconv`.
     *
     * Fazia exatamente o que `NormalizadorTexto::basico()` já faz, e com o
     * defeito que ele existe para evitar: `iconv('ASCII//TRANSLIT')` depende do
     * locale e no Windows devolve "?" para acentuado.
     *
     * Aqui isso é pior que cosmético — este serviço **compara** textos para
     * achar cadastros duplicados. Com "?" no lugar do acento, "JOSÉ" e "JOSE"
     * deixam de casar em dev e casam na VPS: a mesma base produz listas de
     * inconsistência diferentes conforme onde roda.
     */
    private function normalizar(string $v): string
    {
        return NormalizadorTexto::basico($v);
    }
}
