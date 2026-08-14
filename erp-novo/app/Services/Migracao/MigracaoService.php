<?php

namespace App\Services\Migracao;

use App\Etl\MigratorRegistry;
use App\Etl\Support\MigrationContext;
use App\Models\Migracao\Migracao;
use App\Models\Migracao\MigracaoDescarte;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Motor da ferramenta de migração do painel.
 *
 * O fluxo tem três fases porque foi assim que a migração real do Dubena se
 * mostrou tratável:
 *
 *  1. `conectar`   — valida credenciais e descobre o que há na origem.
 *  2. `diagnosticar` — conta as entidades e levanta os problemas ANTES de
 *     gravar qualquer coisa, incluindo as empresas legadas que não têm tenant
 *     correspondente (a única decisão que exige humano).
 *  3. `executar`   — roda os migradores do ETL na ordem de dependência.
 *
 * Nada é descartado em silêncio: o que não entra vira `migracao_descartes` com
 * o dado original.
 */
class MigracaoService
{
    /** Tipos de origem suportados => conexão do ETL que os lê. */
    public const ORIGENS = [
        'erp_pg' => 'legado',
        'app_mysql' => 'app_legado',
        'monitora_mysql' => 'monitora_legado',
    ];

    /**
     * Registra a conexão da origem em runtime, para o ETL enxergá-la sem
     * depender do .env — a ferramenta recebe as credenciais pela tela.
     */
    public function configurarConexao(Migracao $migracao): string
    {
        $conexao = self::ORIGENS[$migracao->origem_tipo] ?? null;
        if ($conexao === null) {
            throw new \InvalidArgumentException("Origem não suportada: {$migracao->origem_tipo}");
        }

        $cfg = $migracao->config ?? [];
        $driver = $migracao->origem_tipo === 'erp_pg' ? 'pgsql' : 'mysql';

        Config::set("database.connections.{$conexao}", array_filter([
            'driver' => $driver,
            'host' => $cfg['host'] ?? '127.0.0.1',
            'port' => $cfg['port'] ?? ($driver === 'pgsql' ? '5432' : '3306'),
            'database' => $cfg['database'] ?? '',
            'username' => $cfg['username'] ?? '',
            'password' => $cfg['password'] ?? '',
            'charset' => $driver === 'pgsql' ? 'utf8' : 'utf8mb4',
            'search_path' => $driver === 'pgsql' ? ($cfg['schema'] ?? 'public') : null,
            'prefix' => '',
            'strict' => false,
        ], fn ($v) => $v !== null));

        DB::purge($conexao);

        return $conexao;
    }

    /** Testa a conexão e devolve as tabelas encontradas. */
    public function conectar(Migracao $migracao): array
    {
        $conexao = $this->configurarConexao($migracao);
        DB::connection($conexao)->getPdo();

        return [
            'ok' => true,
            'tabelas' => $this->tabelas($conexao),
        ];
    }

    /**
     * Levanta contagens e problemas SEM gravar nada.
     *
     * O item mais importante do retorno é `empresas`: as empresas da origem que
     * não têm tenant correspondente. É a decisão que o usuário precisa tomar
     * (mapear para um tenant existente, criar novo, ou ignorar) e que não dá
     * para adivinhar — na migração real do Dubena, 3 de 4 empresas do Monitora
     * não existiam no ERP.
     */
    public function diagnosticar(Migracao $migracao): array
    {
        $conexao = $this->configurarConexao($migracao);
        $origem = DB::connection($conexao);
        $tabelas = $this->tabelas($conexao);

        $contagens = [];
        foreach ($this->entidadesDe($migracao->origem_tipo) as $rotulo => $tabela) {
            if (in_array($tabela, $tabelas, true)) {
                $contagens[$rotulo] = (int) $origem->table($tabela)->count();
            }
        }

        return [
            'tabelas_encontradas' => count($tabelas),
            'contagens' => $contagens,
            'empresas' => $this->empresasDaOrigem($origem, $migracao->origem_tipo, $tabelas),
            'alertas' => $this->alertas($origem, $migracao->origem_tipo, $tabelas),
        ];
    }

    /**
     * Roda os migradores. `$apenas` limita a um subconjunto (retomada).
     *
     * @param  list<string>  $apenas
     */
    public function executar(Migracao $migracao, array $apenas = [], bool $dryRun = false): array
    {
        $conexao = $this->configurarConexao($migracao);

        $migradores = MigratorRegistry::resolved();
        if ($apenas !== []) {
            $migradores = array_values(array_filter(
                $migradores,
                fn ($m) => in_array($m->nome(), $apenas, true)
            ));
        }

        $ctx = new MigrationContext(dryRun: $dryRun, conexaoLegado: $conexao);
        $resultado = [];
        $total = max(count($migradores), 1);

        foreach ($migradores as $i => $m) {
            $migracao->update([
                'etapa_atual' => $m->nome(),
                'progresso' => (int) round($i / $total * 100),
            ]);

            try {
                $r = $m->migrar($ctx);
                $resultado[$m->nome()] = [
                    'lidos' => $r->lidos,
                    'gravados' => $r->gravados,
                    'pulados' => $r->pulados,
                    'avisos' => $r->avisos,
                ];

                // Aviso de descarte vira registro consultável, não só texto.
                foreach ($r->avisos as $aviso) {
                    if ($r->pulados > 0) {
                        MigracaoDescarte::create([
                            'migracao_id' => $migracao->id,
                            'migrador' => $m->nome(),
                            'entidade' => $m->nome(),
                            'motivo' => mb_substr($aviso, 0, 255),
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                // Um migrador que falha não derruba os demais: o que já entrou
                // fica, e o erro é reportado por etapa.
                $resultado[$m->nome()] = [
                    'lidos' => 0, 'gravados' => 0, 'pulados' => 0,
                    'erro' => mb_substr($e->getMessage(), 0, 500),
                ];
            }
        }

        $migracao->update(['progresso' => 100, 'etapa_atual' => null]);

        return $resultado;
    }

    /** Valida as invariantes (contagem/integridade) dos migradores. */
    public function validar(Migracao $migracao): array
    {
        $conexao = $this->configurarConexao($migracao);
        $ctx = new MigrationContext(conexaoLegado: $conexao);

        $out = [];
        foreach (MigratorRegistry::resolved() as $m) {
            foreach ($m->invariantes() as $inv) {
                $r = $inv->verificar();
                $out[] = [
                    'migrador' => $m->nome(),
                    'invariante' => $r->nome ?? $inv->nome(),
                    'ok' => $r->ok,
                    'resumo' => $r->resumo(),
                ];
            }
        }

        return $out;
    }

    /** @return list<string> */
    private function tabelas(string $conexao): array
    {
        try {
            return array_map(
                fn ($t) => is_array($t) ? ($t['name'] ?? '') : $t->name,
                DB::connection($conexao)->getSchemaBuilder()->getTables()
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<string,string> rótulo => tabela na origem */
    private function entidadesDe(string $tipo): array
    {
        return match ($tipo) {
            'erp_pg' => [
                'Empresas' => 'empresas',
                'Clientes' => 'clientes',
                'Telefones' => 'clientetelefones',
                'Produtos' => 'produtos',
                'Pedidos' => 'pedidos',
                'Itens de pedido' => 'pedidoprodutos',
                'Cidades' => 'cidades',
                'Bairros' => 'bairros',
                'Ruas' => 'ruas',
            ],
            'app_mysql' => [
                'Usuários do app' => 'clienteimportacoes',
                'Endereços' => 'clienteenderecos',
                'Telefones' => 'clientetelefones',
                'Pedidos' => 'pedidos',
                'Itens de pedido' => 'pedidoitens',
                'Avaliações' => 'pedidoavaliacoes',
            ],
            'monitora_mysql' => [
                'Empresas' => 'empresas',
                'Veículos' => 'veiculos',
                'Cercas' => 'cercas',
                'Posições GPS' => 'positions',
            ],
            default => [],
        };
    }

    /**
     * Empresas da origem + sugestão de tenant (casada por nome normalizado).
     *
     * @return list<array<string,mixed>>
     */
    private function empresasDaOrigem($origem, string $tipo, array $tabelas): array
    {
        if (! in_array('empresas', $tabelas, true)) {
            // O app não tem tabela de empresa: opera com uma revenda só.
            return [];
        }

        $doErp = [];
        foreach (DB::table('empresas')->get(['id', 'razao_social', 'cnpj']) as $e) {
            $doErp[$this->normalizar((string) $e->razao_social)] ??= (int) $e->id;
            if (! empty($e->cnpj)) {
                $doErp['cnpj:'.preg_replace('/\D/', '', (string) $e->cnpj)] ??= (int) $e->id;
            }
        }

        $out = [];
        foreach ($origem->table('empresas')->get() as $e) {
            $nome = (string) ($e->razao_social ?? $e->nome_fantasia ?? '');
            $cnpj = preg_replace('/\D/', '', (string) ($e->cnpj ?? ''));
            $sugestao = $doErp['cnpj:'.$cnpj] ?? $doErp[$this->normalizar($nome)] ?? null;

            $out[] = [
                'id_origem' => (int) $e->id,
                'nome' => $nome,
                'cnpj' => $cnpj ?: null,
                'tenant_sugerido' => $sugestao,
                // Sem sugestão, o padrão é CRIAR — nunca descartar.
                'acao_sugerida' => $sugestao !== null ? 'mapear' : 'criar',
            ];
        }

        return $out;
    }

    /** Problemas conhecidos que valem avisar antes de migrar. */
    private function alertas($origem, string $tipo, array $tabelas): array
    {
        $out = [];

        if (in_array('clientes', $tabelas, true)) {
            try {
                $comMascara = $origem->table('clientes')
                    ->where(fn ($q) => $q->where('cpf', 'like', '%.%')->orWhere('cnpj', 'like', '%.%'))
                    ->count();
                if ($comMascara > 0) {
                    $out[] = [
                        'tipo' => 'documento_mascarado',
                        'mensagem' => "{$comMascara} documento(s) com máscara — serão normalizados para dígitos.",
                    ];
                }
            } catch (\Throwable) {
                // coluna ausente nesta origem: não é alerta, é só ausência.
            }
        }

        if (in_array('cidades', $tabelas, true)) {
            try {
                $dup = $origem->table('cidades')
                    ->selectRaw('COUNT(*) - COUNT(DISTINCT LOWER(descricao) || uf) AS n')
                    ->value('n');
                if ((int) $dup > 0) {
                    $out[] = [
                        'tipo' => 'cidades_duplicadas',
                        'mensagem' => "{$dup} cidade(s) repetida(s) no cadastro — serão unificadas.",
                    ];
                }
            } catch (\Throwable) {
                // sintaxe varia por driver; ausência do alerta não impede migrar.
            }
        }

        if ($tipo === 'monitora_mysql' && in_array('positions', $tabelas, true)) {
            $out[] = [
                'tipo' => 'volume',
                'mensagem' => 'O histórico de posições GPS pode ter milhões de linhas; '
                    .'a carga roda em segundo plano.',
            ];
        }

        return $out;
    }

    private function normalizar(string $v): string
    {
        $v = mb_strtolower(trim($v));
        $v = strtr($v, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e',
            'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c',
        ]);
        $v = preg_replace('/\b(ltda|s\.?a\.?|me|epp|eireli)\b\.?/', '', $v);

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $v));
    }
}
