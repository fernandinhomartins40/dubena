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

    /**
     * F2-01 — rota `api/admin/*` autenticada precisa declarar permissão.
     *
     * Saber que a rota existe não basta: é preciso saber o que ela exige. Uma
     * rota autenticada sem `autorizar()` é aberta a qualquer usuário logado, e
     * foi assim que `/lookups/{tipo}` entregou a lista de clientes e de contas a
     * quem a listagem do módulo negava com 403.
     *
     * A lista de exceções é fechada e justificada no comando: 2FA e sessões do
     * próprio usuário, assinatura da própria empresa, dashboard e troca de
     * empresa. Rota nova fora dela reprova aqui — que é o ponto.
     */
    public function test_toda_rota_admin_autenticada_declara_permissao(): void
    {
        $semPermissao = ApiManifestGerar::rotasSemPermissaoDeclarada();

        // Só o recorte admin: `api/app/v1/*` tem outra fronteira (o papel do
        // token, via middleware `approle`) e as pontes legadas têm a sua.
        $admin = array_values(array_filter(
            $semPermissao,
            fn (string $linha): bool => str_contains($linha, 'api/admin/'),
        ));

        // O detector anota o controller entre parênteses; a exceção é por rota.
        $novas = array_values(array_filter($admin, function (string $linha): bool {
            $rota = trim(explode('(', $linha)[0]);

            return ! in_array($rota, ApiManifestGerar::ADMIN_SEM_PERMISSAO_APROVADAS, true);
        }));

        $this->assertSame(
            [],
            $novas,
            "Rota admin autenticada sem permissão declarada:\n - ".implode("\n - ", $novas)
                ."\nChame `\$this->autorizar(\$request, 'modulo.acao')` ou justifique em "
                .'ApiManifestGerar::ADMIN_SEM_PERMISSAO_APROVADAS.',
        );
    }
}
