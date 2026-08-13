<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\IntegrityInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Models\Cliente\Cliente;
use App\Models\Cliente\ClienteEndereco;
use App\Models\Pedido\PedidoAvaliacao;
use Illuminate\Support\Facades\DB;

/**
 * Migra o banco do APP "Gás em Casa" (MySQL `sgcm_api`) para o schema novo.
 *
 * Diferença em relação aos demais migrators: o app NÃO é uma fonte independente
 * de clientes/pedidos — ele é um CANAL do ERP. Todo pedido do app nasceu no ERP
 * (`pedidos.erp_id`, 100% preenchido no dump analisado) e todo cliente do app
 * aponta para o cliente do ERP (`clientes.api_id` no lado Oracle). Portanto aqui
 * NÃO se recria cliente nem pedido: correlaciona-se com o que o ETL do ERP já
 * gravou e migra-se apenas o que só existe no app:
 *
 *   - endereços de entrega do app  → cliente_enderecos
 *   - avaliações de pedido          → pedido_avaliacoes
 *   - vínculo usuário-do-app        → clientes.user_id (quando houver)
 *
 * Migrar cliente/pedido daqui duplicaria a base do ERP — é o erro que este
 * desenho evita.
 *
 * Fonte: conexão `app_legado` (MySQL). Sem ela configurada: 0 lidos/gravados.
 */
final class AppGasEmCasaMigrator implements Migrator
{
    private ?MigrationContext $ctxAtual = null;

    /** Mapa api_id (cliente do app) => id do cliente no ERP novo. */
    private array $mapaClientes = [];

    /** Mapa id do pedido no app => id do pedido no ERP novo. */
    private array $mapaPedidos = [];

    public function nome(): string
    {
        return 'app-gasemcasa';
    }

    public function dependeDe(): array
    {
        return ['clientes', 'pedidos'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->ctxAtual = $ctx;

        if (! $this->appDisponivel()) {
            return new MigrationResult($this->nome(), 0, 0, 0,
                ['conexão `app_legado` indisponível — nada a migrar']);
        }

        $this->montarCorrelacoes($ctx);

        $avisos = [];
        if ($this->mapaClientes === []) {
            $avisos[] = 'nenhum cliente do ERP tem api_id preenchido: '
                .'endereços/avaliações do app não têm âncora e serão pulados';
        }

        $enderecos = $this->lerEnderecos();
        $avaliacoes = $this->lerAvaliacoes();

        $gravados = 0;
        $pulados = 0;

        if (! $ctx->dryRun) {
            foreach ($enderecos as $e) {
                ClienteEndereco::withoutTenant()->updateOrCreate(
                    ['cliente_id' => $e['cliente_id'], 'endereco' => $e['endereco'], 'numero' => $e['numero']],
                    $e
                );
                $gravados++;
            }
            foreach ($avaliacoes as $a) {
                PedidoAvaliacao::withoutTenant()->updateOrCreate(['pedido_id' => $a['pedido_id']], $a);
                $gravados++;
            }
        }

        $pulados = $this->puladosEnderecos + $this->puladosAvaliacoes;
        if ($this->puladosEnderecos) {
            $avisos[] = "{$this->puladosEnderecos} endereço(s) do app sem cliente correspondente no ERP";
        }
        if ($this->puladosAvaliacoes) {
            $avisos[] = "{$this->puladosAvaliacoes} avaliação(ões) sem pedido correspondente no ERP";
        }

        return new MigrationResult(
            migrator: $this->nome(),
            lidos: count($enderecos) + count($avaliacoes) + $pulados,
            gravados: $ctx->dryRun ? 0 : $gravados,
            pulados: $pulados,
            avisos: $avisos,
        );
    }

    public function invariantes(): array
    {
        $ctx = $this->ctxAtual ?? new MigrationContext();
        if (! $this->appDisponivel()) {
            return [];
        }

        return [
            new IntegrityInvariant($ctx, 'cliente_enderecos', 'cliente_id', 'clientes'),
            new IntegrityInvariant($ctx, 'pedido_avaliacoes', 'pedido_id', 'pedidos'),
        ];
    }

    private int $puladosEnderecos = 0;

    private int $puladosAvaliacoes = 0;

    /**
     * Correlaciona as chaves do app com as linhas já migradas do ERP.
     * `clientes.api_id` e `pedidos.apipedido_id` são as pontes gravadas pelo
     * próprio ERP legado quando o pedido entrava pelo app.
     */
    private function montarCorrelacoes(MigrationContext $ctx): void
    {
        $legado = $ctx->legado();

        try {
            $this->mapaClientes = $legado->table('clientes')
                ->whereNotNull('api_id')
                ->pluck('id', 'api_id')
                ->map(fn ($v) => (int) $v)
                ->all();
        } catch (\Throwable) {
            $this->mapaClientes = [];
        }

        try {
            $this->mapaPedidos = $legado->table('pedidos')
                ->whereNotNull('apipedido_id')
                ->pluck('id', 'apipedido_id')
                ->map(fn ($v) => (int) $v)
                ->all();
        } catch (\Throwable) {
            $this->mapaPedidos = [];
        }
    }

    /** @return list<array<string,mixed>> */
    private function lerEnderecos(): array
    {
        $this->puladosEnderecos = 0;

        try {
            $rows = $this->app()->table('clienteenderecos')->where('ativo', 1)->get();
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $clienteId = $this->mapaClientes[(int) $r->cliente_id] ?? null;
            if ($clienteId === null) {
                $this->puladosEnderecos++;

                continue;
            }

            $out[] = [
                'cliente_id' => $clienteId,
                'empresa_id' => $this->empresaDoCliente($clienteId),
                'titulo' => $this->limita((string) ($r->titulo ?: 'Casa'), 100),
                'endereco' => $this->limita((string) $r->rua, 255),
                'numero' => $this->limita((string) $r->numero, 20),
                'complemento' => $this->limita((string) ($r->complemento ?? ''), 120) ?: null,
                'ponto_referencia' => $this->limita((string) ($r->pontoreferencia ?? ''), 160) ?: null,
                'bairro' => $this->limita((string) ($r->bairro ?? ''), 120) ?: null,
                'cidade' => $this->limita((string) ($r->cidade ?? ''), 120) ?: null,
                'cep' => $this->limita(preg_replace('/\D/', '', (string) ($r->cep ?? '')), 12) ?: null,
                'uf' => $this->limita((string) ($r->uf ?? ''), 2) ?: null,
                'latitude' => $r->latitude !== null ? (float) $r->latitude : null,
                'longitude' => $r->longitude !== null ? (float) $r->longitude : null,
                'favorito' => false,
            ];
        }

        return $out;
    }

    /** @return list<array<string,mixed>> */
    private function lerAvaliacoes(): array
    {
        $this->puladosAvaliacoes = 0;

        try {
            $rows = $this->app()->table('pedidoavaliacoes')->get();
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        $vistos = [];
        foreach ($rows as $r) {
            $pedidoId = $this->mapaPedidos[(int) $r->pedido_id] ?? null;
            if ($pedidoId === null || isset($vistos[$pedidoId])) {
                $this->puladosAvaliacoes++;

                continue;
            }
            $vistos[$pedidoId] = true;

            $rating = $r->rating === null ? null : (int) round((float) $r->rating);
            if ($rating !== null) {
                $rating = max(1, min(5, $rating));
            }

            $out[] = [
                'pedido_id' => $pedidoId,
                'empresa_id' => $this->empresaDoPedido($pedidoId),
                'rating' => $rating,
                'mensagem' => $this->limita((string) ($r->mensagem ?? ''), 140) ?: null,
                'ignorado' => $rating === null,
            ];
        }

        return $out;
    }

    private function empresaDoCliente(int $clienteId): ?int
    {
        static $cache = [];

        return $cache[$clienteId] ??= Cliente::withoutTenant()
            ->whereKey($clienteId)->value('empresa_id');
    }

    private function empresaDoPedido(int $pedidoId): ?int
    {
        static $cache = [];

        return $cache[$pedidoId] ??= DB::table('pedidos')
            ->where('id', $pedidoId)->value('empresa_id');
    }

    /** Corta no limite da coluna preservando UTF-8 (o legado tem acento). */
    private function limita(string $v, int $max): string
    {
        $v = trim($v);

        return mb_substr($v, 0, $max);
    }

    private function app()
    {
        return DB::connection('app_legado');
    }

    private function appDisponivel(): bool
    {
        try {
            $this->app()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
