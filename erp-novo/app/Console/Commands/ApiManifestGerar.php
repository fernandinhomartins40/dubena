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

    /**
     * Rotas `api/admin/*` que legitimamente não exigem permissão de módulo.
     *
     * Cada uma tem um motivo que vale escrever, porque a lista existe para NÃO
     * crescer por descuido:
     *
     *  - 2FA e sessões: o usuário administra a PRÓPRIA credencial. Exigir
     *    permissão de módulo impediria alguém sem papel de proteger a conta.
     *  - `assinatura`: o admin da empresa vê o que a PRÓPRIA empresa contratou.
     *  - `dashboard/resumo`: home de qualquer usuário logado; o serviço já
     *    escopa pela empresa ativa.
     *  - `vale-gas/situacoes`: devolve os valores de um enum, sem dado de empresa.
     *  - `empresas/{id}/ativar`: a fronteira é `podeAcessarEmpresa`, não RBAC —
     *    trocar de empresa não é operação de módulo.
     *
     * @var list<string>
     */
    public const ADMIN_SEM_PERMISSAO_APROVADAS = [
        'DELETE api/admin/seguranca/sessoes/{id}',
        'GET api/admin/assinatura',
        'GET api/admin/dashboard/resumo',
        'GET api/admin/seguranca/2fa',
        'GET api/admin/seguranca/sessoes',
        'GET api/admin/vale-gas/situacoes',
        'POST api/admin/empresas/{id}/ativar',
        'POST api/admin/seguranca/2fa/confirmar',
        'POST api/admin/seguranca/2fa/desabilitar',
        'POST api/admin/seguranca/2fa/setup',
        'POST api/admin/seguranca/sessoes/revogar-outras',
    ];

    /**
     * Rotas autenticadas que NÃO declaram permissão no método do controller.
     *
     * F2-01: o manifesto sabia quais rotas existem, mas não o que cada uma
     * exige. Uma rota sem `autorizar()` é aberta a qualquer autenticado — foi
     * assim que `/lookups/{tipo}` entregou clientes e contas a quem a listagem
     * do módulo negava com 403.
     *
     * A leitura é do CÓDIGO-FONTE do método, não do runtime: é análise estática
     * barata e não depende de exercitar a rota.
     *
     * @return list<string> "MÉTODO uri (Controller@metodo)"
     */
    public static function rotasSemPermissaoDeclarada(): array
    {
        $achados = [];

        foreach (Route::getRoutes() as $rota) {
            $uri = $rota->uri();
            if (! str_starts_with($uri, 'api/')) {
                continue;
            }
            // Rota pública (webhook, login, health) não tem o que autorizar: a
            // fronteira dela é outra, e exigir permissão aqui seria falso.
            $middleware = $rota->gatherMiddleware();
            if (! in_array('auth:sanctum', $middleware, true)) {
                continue;
            }

            $acao = $rota->getActionName();
            if (! str_contains($acao, '@')) {
                continue; // closure: sem corpo de método para inspecionar
            }

            [$classe, $metodo] = explode('@', $acao, 2);
            if (self::metodoAutoriza($classe, $metodo)) {
                continue;
            }

            foreach ($rota->methods() as $verbo) {
                if (in_array($verbo, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }
                $achados[] = "{$verbo} {$uri} (".class_basename($classe)."@{$metodo})";
            }
        }

        $achados = array_values(array_unique($achados));
        sort($achados);

        return $achados;
    }

    /**
     * O método autoriza — direto ou por um helper da própria classe?
     *
     * A checagem nao pode olhar so o corpo do metodo: `GeoController` autoriza
     * dentro de `cfg()`, um helper privado que recebe a permissao por parametro.
     * Considerar isso falta de autorizacao seria falso positivo, e um detector
     * que grita errado logo passa a ser ignorado.
     *
     * Por isso a busca segue os metodos privados/protegidos da mesma classe
     * chamados pelo metodo (um nivel de profundidade, que cobre o padrao usado).
     */
    private static function metodoAutoriza(string $classe, string $metodo, int $profundidade = 1): bool
    {
        $corpo = self::corpoDoMetodo($classe, $metodo);
        if ($corpo === null) {
            return false;
        }

        if (str_contains($corpo, 'autorizar')) {
            return true;
        }

        if ($profundidade <= 0) {
            return false;
        }

        // `$this->helper(` — segue apenas helpers da propria classe.
        if (! preg_match_all('/\$this->([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $corpo, $m)) {
            return false;
        }

        foreach (array_unique($m[1]) as $chamado) {
            if ($chamado !== $metodo && self::metodoAutoriza($classe, $chamado, $profundidade - 1)) {
                return true;
            }
        }

        return false;
    }

    /** Codigo-fonte do metodo, ou null se nao for inspecionavel. */
    private static function corpoDoMetodo(string $classe, string $metodo): ?string
    {
        try {
            $reflexao = new \ReflectionMethod($classe, $metodo);
        } catch (\ReflectionException) {
            return null;
        }

        $arquivo = $reflexao->getFileName();
        if ($arquivo === false || ! is_file($arquivo)) {
            return null;
        }

        $linhas = file($arquivo) ?: [];

        return implode('', array_slice(
            $linhas,
            $reflexao->getStartLine() - 1,
            $reflexao->getEndLine() - $reflexao->getStartLine() + 1,
        ));
    }
}
