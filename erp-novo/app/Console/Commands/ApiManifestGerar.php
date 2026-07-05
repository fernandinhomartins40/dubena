<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

/**
 * api:manifest — gera o MANIFESTO do contrato de API (API-3 da auditoria).
 *
 * "OpenAPI vivo" na forma mais barata e à prova de drift: lista todo endpoint
 * `api/*` registrado (método + uri) num JSON versionado. O ApiContratoDriftTest
 * compara o manifesto salvo com as rotas em runtime — se um endpoint SUMIR, o CI
 * falha; se um NOVO for adicionado, o teste pede regenerar (mudança consciente do
 * contrato). Assim SPA/apps nunca perdem um endpoint por acidente.
 *
 * Uso: `php artisan api:manifest` (regrava database/api-manifest.json).
 */
class ApiManifestGerar extends Command
{
    protected $signature = 'api:manifest {--check : só valida (não grava) — retorna ≠0 se houver drift}';

    protected $description = 'Gera/valida o manifesto do contrato de API (endpoints api/*).';

    public const CAMINHO = 'database/api-manifest.json';

    public function handle(): int
    {
        $atual = self::coletar();
        $arquivo = base_path(self::CAMINHO);

        if ($this->option('check')) {
            $salvo = is_file($arquivo) ? json_decode((string) file_get_contents($arquivo), true) : [];
            $removidos = array_values(array_diff($salvo, $atual));
            $novos = array_values(array_diff($atual, $salvo));

            if ($removidos !== [] || $novos !== []) {
                $this->error('Drift de contrato de API detectado.');
                foreach ($removidos as $r) {
                    $this->line("  <fg=red>- {$r}</> (removido)");
                }
                foreach ($novos as $n) {
                    $this->line("  <fg=green>+ {$n}</> (novo — regenere: php artisan api:manifest)");
                }

                return self::FAILURE;
            }

            $this->info('Contrato de API íntegro ('.count($atual).' endpoints).');

            return self::SUCCESS;
        }

        file_put_contents($arquivo, json_encode($atual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        $this->info('Manifesto gravado: '.self::CAMINHO.' ('.count($atual).' endpoints).');

        return self::SUCCESS;
    }

    /**
     * Coleta "MÉTODO uri" de todas as rotas api/* (sem HEAD/OPTIONS), ordenado.
     *
     * @return list<string>
     */
    public static function coletar(): array
    {
        $itens = [];
        foreach (Route::getRoutes() as $rota) {
            $uri = $rota->uri();
            if (! str_starts_with($uri, 'api/')) {
                continue;
            }
            foreach ($rota->methods() as $metodo) {
                if (in_array($metodo, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }
                $itens[] = "{$metodo} {$uri}";
            }
        }

        $itens = array_values(array_unique($itens));
        sort($itens);

        return $itens;
    }
}
