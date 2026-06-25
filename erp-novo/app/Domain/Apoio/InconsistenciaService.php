<?php

namespace App\Domain\Apoio;

use Illuminate\Support\Facades\DB;

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
     * @return list<array<string,mixed>>
     */
    private function duplicatas(string $tabela, string $tipo, int $grupoId): array
    {
        $registros = DB::table($tabela)
            ->where('grupo_id', $grupoId)
            ->get(['id', 'cidade_id', 'descricao']);

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

    /** Normaliza: minúsculo, sem acento, sem pontuação, espaços colapsados. */
    private function normalizar(string $v): string
    {
        $v = mb_strtolower(trim($v));
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT', $v);
        if ($t !== false) {
            $v = $t;
        }
        $v = preg_replace('/[^a-z0-9 ]/', '', $v);

        return trim(preg_replace('/\s+/', ' ', $v));
    }
}
