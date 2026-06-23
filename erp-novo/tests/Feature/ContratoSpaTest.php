<?php

namespace Tests\Feature;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * GATE da FASE C1 — contrato SPA × backend.
 *
 * Extrai TODO endpoint que a SPA chama (frontend/src/features/*) e garante que
 * existe uma rota registrada no backend para ele. Foi a auditoria forense que
 * revelou 36/75 endpoints sem rota; este teste impede a regressão: se a SPA
 * passar a chamar algo que o backend não expõe, o CI falha aqui.
 *
 * Endpoints com stub 501 CONTAM como existentes (a rota existe; o módulo é de
 * uma fase futura) — o objetivo do contrato é "sem 404", não "tudo implementado".
 */
class ContratoSpaTest extends TestCase
{
    /** Prefixo da API admin no backend (a SPA monta baseURL .../api/admin). */
    private const PREFIXO_ADMIN = 'api/admin/';

    public function test_todo_endpoint_da_spa_tem_rota_no_backend(): void
    {
        $endpointsSpa = $this->endpointsDaSpa();
        $this->assertNotEmpty($endpointsSpa, 'Não foi possível extrair endpoints da SPA.');

        $rotas = $this->rotasAdminNormalizadas();

        $faltantes = [];
        foreach ($endpointsSpa as $ep) {
            [$metodo, $path] = $ep;
            if (! $this->casaComAlgumaRota($metodo, $path, $rotas)) {
                $faltantes[] = strtoupper($metodo).' '.$path;
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($faltantes)),
            "Endpoints chamados pela SPA sem rota no backend:\n - ".implode("\n - ", array_unique($faltantes)),
        );
    }

    /**
     * Casa um endpoint da SPA com alguma rota do backend. Um segmento {p} da SPA
     * (vindo de ${var}) casa tanto com {p} do backend quanto com um segmento
     * ESTÁTICO na mesma posição — porque helpers como `/estoque/${rota}` resolvem,
     * em runtime, para valores literais ('transferencias', 'requisicoes', ...) que
     * têm rota estática própria.
     *
     * @param  list<string>  $rotas
     */
    private function casaComAlgumaRota(string $metodo, string $pathSpa, array $rotas): bool
    {
        $metodo = strtoupper($metodo);
        $segSpa = explode('/', $pathSpa);

        foreach ($rotas as $rota) {
            [$mRota, $pRota] = explode(' ', $rota, 2);
            if ($mRota !== $metodo) {
                continue;
            }
            $segRota = explode('/', $pRota);
            if (count($segRota) !== count($segSpa)) {
                continue;
            }

            $combina = true;
            foreach ($segSpa as $i => $s) {
                $r = $segRota[$i];
                // {p} casa com qualquer coisa; senão exige igualdade literal.
                if ($s === '{p}' || $r === '{p}') {
                    continue;
                }
                if ($s !== $r) {
                    $combina = false;
                    break;
                }
            }
            if ($combina) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extrai pares [metodo, path-normalizado] das chamadas api.get/post/put/delete
     * nos arquivos da SPA.
     *
     * @return list<array{0:string,1:string}>
     */
    private function endpointsDaSpa(): array
    {
        $base = base_path('frontend/src/features');
        if (! is_dir($base)) {
            $this->markTestSkipped('Frontend não disponível neste ambiente.');
        }

        $arquivos = $this->arquivosTs($base);
        $endpoints = [];

        foreach ($arquivos as $arquivo) {
            $conteudo = (string) file_get_contents($arquivo);
            // Captura api.<metodo>(`/path...` ou api.<metodo>('/path...', incluindo
            // o generic TS opcional: api.get<Tipo>('/path').
            if (preg_match_all(
                '/api\.(get|post|put|patch|delete)\s*(?:<[^>]*>)?\(\s*[`\'"]([^`\'"]+)/i',
                $conteudo,
                $m,
                PREG_SET_ORDER,
            )) {
                foreach ($m as $match) {
                    $metodo = strtoupper($match[1]);
                    $path = $this->normalizarPath($match[2]);
                    if ($path !== null) {
                        $endpoints[] = [$metodo, $path];
                    }
                }
            }
        }

        return $endpoints;
    }

    /** @return list<string> */
    private function arquivosTs(string $dir): array
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        $arquivos = [];
        foreach ($it as $f) {
            if ($f->isFile() && in_array($f->getExtension(), ['ts', 'tsx'], true)) {
                $arquivos[] = $f->getPathname();
            }
        }

        return $arquivos;
    }

    /**
     * Normaliza o path da SPA para casar com a rota Laravel:
     * - remove query/template tail; troca ${x} por {param}; tira barra inicial.
     * Retorna null se não for um path de API utilizável.
     */
    private function normalizarPath(string $raw): ?string
    {
        // Corta no primeiro caractere que encerra o literal de path.
        $path = preg_replace('/[`\'"].*$/s', '', $raw) ?? $raw;
        // ${...} (template) → {p}
        $path = preg_replace('/\$\{[^}]+\}/', '{p}', $path) ?? $path;
        $path = ltrim(trim($path), '/');

        if ($path === '' || str_starts_with($path, 'http')) {
            return null;
        }

        // Cada segmento dinâmico {p} vira {p}; segmentos estáticos ficam.
        $segmentos = array_map(
            fn (string $s) => str_contains($s, '{') ? '{p}' : $s,
            explode('/', $path),
        );

        return implode('/', $segmentos);
    }

    /**
     * Rotas admin do backend, normalizadas no mesmo formato "METODO prefixo/path"
     * com parâmetros uniformizados para {p}.
     *
     * @return list<string>
     */
    private function rotasAdminNormalizadas(): array
    {
        $rotas = [];
        /** @var RoutingRoute $route */
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, self::PREFIXO_ADMIN)) {
                continue;
            }
            $path = substr($uri, strlen(self::PREFIXO_ADMIN));
            $path = preg_replace('/\{[^}]+\}/', '{p}', $path) ?? $path;

            foreach ($route->methods() as $metodo) {
                if (in_array($metodo, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }
                $rotas[] = strtoupper($metodo).' '.$path;
            }
        }

        return array_values(array_unique($rotas));
    }
}
