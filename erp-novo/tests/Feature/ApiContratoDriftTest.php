<?php

namespace Tests\Feature;

use App\Console\Commands\ApiManifestGerar;
use Tests\TestCase;

/**
 * API-3 (auditoria) — GUARD de contrato de API.
 *
 * O manifesto (database/api-manifest.json) é a fonte-da-verdade dos endpoints
 * api/* expostos. Este teste compara-o com as rotas em runtime:
 *  - endpoint REMOVIDO → falha (regressão silenciosa que quebraria SPA/apps);
 *  - endpoint NOVO → falha pedindo regenerar (o contrato mudou de propósito, então
 *    o autor confirma rodando `php artisan api:manifest` e commitando o diff).
 *
 * É o equivalente barato de um OpenAPI vivo: o diff do manifesto no PR mostra
 * exatamente o que entrou/saiu do contrato.
 */
class ApiContratoDriftTest extends TestCase
{
    public function test_contrato_de_api_sem_drift(): void
    {
        $arquivo = base_path(ApiManifestGerar::CAMINHO);
        $this->assertFileExists($arquivo, 'Manifesto ausente — rode `php artisan api:manifest`.');

        $salvo = json_decode((string) file_get_contents($arquivo), true);
        $atual = ApiManifestGerar::coletar();

        $removidos = array_values(array_diff($salvo, $atual));
        $novos = array_values(array_diff($atual, $salvo));

        $this->assertSame([], $removidos, 'Endpoints REMOVIDOS do contrato (quebra SPA/apps): '.implode(', ', $removidos));
        $this->assertSame([], $novos, 'Endpoints NOVOS não registrados no manifesto — rode `php artisan api:manifest` e commite: '.implode(', ', $novos));
    }
}
