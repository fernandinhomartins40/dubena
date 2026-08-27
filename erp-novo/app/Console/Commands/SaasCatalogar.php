<?php

namespace App\Console\Commands;

use App\Domain\Saas\RecursoCatalogo;
use App\Domain\Shared\PermissaoCatalogo;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/** Gera uma evidência reexecutável do schema PostgreSQL e da superfície SaaS. */
class SaasCatalogar extends Command
{
    protected $signature = 'saas:catalogar
        {--connection=pgsql_owner : Conexão PostgreSQL que enxerga o catálogo}
        {--output=../docs/01-vigente/implementacao-saas/CATALOGO_VIVO.json : Caminho relativo à raiz da aplicação}
        {--check : Falha se o arquivo salvo divergir da coleta atual}';

    protected $description = 'Gera ou valida o catálogo vivo SaaS a partir do banco e código efetivos.';

    public function handle(): int
    {
        $connection = DB::connection((string) $this->option('connection'));
        if ($connection->getDriverName() !== 'pgsql') {
            $this->error('saas:catalogar exige PostgreSQL; SQLite/MySQL não comprovam schema, owner ou RLS efetivos.');

            return self::FAILURE;
        }

        $catalogo = [
            'format' => 1,
            'database' => [
                'driver' => 'pgsql',
                'connection' => (string) $this->option('connection'),
                'current_user' => (string) $connection->scalar('select current_user'),
            ],
            'schema' => $this->schema($connection),
            'models' => $this->models(),
            'jobs' => $this->phpFiles('app', 'ShouldQueue'),
            'routes' => $this->routes(),
            'capabilities' => [
                'permissions' => PermissaoCatalogo::comDescricoes(),
                'resources' => RecursoCatalogo::comDescricoes(),
            ],
            'integrations' => $this->integrations(),
        ];
        $json = json_encode($catalogo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
        if ($json === false) {
            $this->error('Não foi possível serializar o catálogo.');

            return self::FAILURE;
        }

        $output = base_path((string) $this->option('output'));
        if ($this->option('check')) {
            if (! is_file($output) || file_get_contents($output) !== $json) {
                $this->error("Drift no catálogo vivo: {$output}. Regenere conscientemente com php artisan saas:catalogar.");

                return self::FAILURE;
            }
            $this->info('Catálogo vivo íntegro.');

            return self::SUCCESS;
        }

        if (! is_dir(dirname($output))) {
            mkdir(dirname($output), 0755, true);
        }
        file_put_contents($output, $json);
        $this->info("Catálogo vivo gravado: {$output}");

        return self::SUCCESS;
    }

    /** @return list<array<string, mixed>> */
    private function schema(ConnectionInterface $connection): array
    {
        $tables = $connection->select(<<<'SQL'
            select c.relname as name, pg_get_userbyid(c.relowner) as owner,
                   c.relrowsecurity as rls_enabled, c.relforcerowsecurity as rls_forced
            from pg_class c
            join pg_namespace n on n.oid = c.relnamespace
            where n.nspname = 'public' and c.relkind in ('r', 'p')
            order by c.relname
        SQL);
        $columns = $connection->select(<<<'SQL'
            select table_name, column_name, data_type, is_nullable, column_default
            from information_schema.columns
            where table_schema = 'public'
            order by table_name, ordinal_position
        SQL);
        $policies = $connection->select(<<<'SQL'
            select tablename, policyname, permissive, roles, cmd, qual, with_check
            from pg_policies where schemaname = 'public' order by tablename, policyname
        SQL);
        $byTable = [];
        foreach ($tables as $table) {
            $byTable[$table->name] = [
                'name' => $table->name,
                'owner' => $table->owner,
                'rls_enabled' => (bool) $table->rls_enabled,
                'rls_forced' => (bool) $table->rls_forced,
                'columns' => [],
                'policies' => [],
            ];
        }
        foreach ($columns as $column) {
            if (isset($byTable[$column->table_name])) {
                $byTable[$column->table_name]['columns'][] = (array) $column;
            }
        }
        foreach ($policies as $policy) {
            if (isset($byTable[$policy->tablename])) {
                $byTable[$policy->tablename]['policies'][] = (array) $policy;
            }
        }

        return array_values($byTable);
    }

    /** @return list<array<string, mixed>> */
    private function models(): array
    {
        $models = [];
        foreach ($this->phpFiles('app/Models') as $file) {
            $class = 'App\\'.str_replace('/', '\\', substr($file, 4, -4));
            if (! class_exists($class) || ! is_subclass_of($class, Model::class) || (new \ReflectionClass($class))->isAbstract()) {
                continue;
            }
            /** @var Model $model */
            $model = new $class;
            $models[] = ['class' => $class, 'table' => $model->getTable()];
        }
        usort($models, fn (array $a, array $b) => $a['class'] <=> $b['class']);

        return $models;
    }

    /** @return list<string> */
    private function phpFiles(string $directory, ?string $contains = null): array
    {
        $root = base_path($directory);
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));
            if ($contains !== null && ! str_contains((string) file_get_contents($file->getPathname()), $contains)) {
                continue;
            }
            $files[] = $relative;
        }
        sort($files);

        return $files;
    }

    /** @return list<array<string, mixed>> */
    private function routes(): array
    {
        $routes = [];
        foreach (Route::getRoutes() as $route) {
            $methods = array_values(array_diff($route->methods(), ['HEAD', 'OPTIONS']));
            if ($methods === []) {
                continue;
            }
            $routes[] = [
                'methods' => $methods,
                'uri' => $route->uri(),
                'action' => $route->getActionName(),
                'middleware' => $route->gatherMiddleware(),
            ];
        }
        usort($routes, fn (array $a, array $b) => [$a['uri'], implode(',', $a['methods'])] <=> [$b['uri'], implode(',', $b['methods'])]);

        return $routes;
    }

    /** @return array<string, list<string>> */
    private function integrations(): array
    {
        $source = (string) file_get_contents(config_path('services.php'));
        preg_match_all("/env\\('([A-Z0-9_]+)'/", $source, $matches);
        $variables = array_values(array_unique($matches[1]));
        sort($variables);

        return [
            'service_config_keys' => array_keys((array) config('services')),
            'environment_variable_names' => $variables,
            'database_connections' => array_keys((array) config('database.connections')),
        ];
    }
}
