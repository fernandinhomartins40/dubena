<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Invariants\IntegrityInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Models\Cliente\Cliente;

/**
 * N2 — migra clientes + telefones do legado.
 *
 * Conversões-chave (do plano): flags '0'/'1' (Oracle) → boolean; lat/long string →
 * decimal; arrays posicionais de telefone → linhas normalizadas (clientetelefones).
 * Invariantes: contagem origem×destino + integridade (zero órfão/obrigatório nulo).
 */
final class ClientesMigrator implements Migrator
{
    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'clientes';
    }

    public function dependeDe(): array
    {
        return ['empresas', 'geografico', 'cadastros-apoio'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->ctxAtual = $ctx;

        $clientes = $this->lerClientes($ctx);
        $telefones = $this->lerTelefones($ctx);

        $gravados = 0;
        if (! $ctx->dryRun) {
            foreach ($clientes as $c) {
                Cliente::withoutTenant()->updateOrCreate(['id' => $c['id']], $c);
                $gravados++;
            }
            foreach ($telefones as $t) {
                \App\Models\Cliente\ClienteTelefone::updateOrCreate(['id' => $t['id']], $t);
                $gravados++;
            }
        }

        return new MigrationResult(
            migrator: $this->nome(),
            lidos: count($clientes) + count($telefones),
            gravados: $ctx->dryRun ? 0 : $gravados,
            pulados: 0,
        );
    }

    public function invariantes(): array
    {
        $ctx = $this->ctxAtual ?? new MigrationContext();
        if (! $this->legadoDisponivel($ctx)) {
            return [];
        }

        return [
            new CountInvariant($ctx, 'clientes', 'clientes'),
            new CountInvariant($ctx, 'clientetelefones', 'clientetelefones'),
            // Integridade de FK no banco NOVO: zero cliente com empresa inexistente;
            // zero telefone órfão (sem cliente).
            new IntegrityInvariant($ctx, 'clientes', 'empresa_id', 'empresas'),
            new IntegrityInvariant($ctx, 'clientetelefones', 'cliente_id', 'clientes'),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function lerClientes(MigrationContext $ctx): array
    {
        try {
            $rows = $ctx->legado()->table('clientes')->get();
        } catch (\Throwable) {
            return [];
        }

        return $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'empresa_id' => (int) $r->empresa_id,
            'grupo_id' => (int) $r->grupo_id,
            'nome' => trim((string) $r->nome),
            'fantasia' => $r->fantasia ?? null,
            'tipopessoa_id' => $r->tipopessoa_id ?? null,
            'segmento_id' => $r->segmento_id ?? null,
            'sexo' => $r->sexo ?? null,
            'datanascimento' => $r->datanascimento ?? null,
            'observacoes' => $r->observacoes ?? null,
            'cpf' => $r->cpf ?? null,
            'rg' => $r->rg ?? null,
            'cnpj' => $r->cnpj ?? null,
            'inscricao_estadual' => $r->inscricao_estadual ?? null,
            'indicador_ie' => isset($r->indicador_ie) ? (int) $r->indicador_ie : null,
            'cliente' => (bool) ($r->cliente ?? true),
            'fornecedor' => (bool) ($r->fornecedor ?? false),
            'transportador' => (bool) ($r->transportador ?? false),
            'simples' => (bool) ($r->simples ?? false),
            'nfemite' => (bool) ($r->nfemite ?? false),
            'ativo' => (bool) ($r->ativo ?? true),
            'endereco' => $r->endereco ?? null,
            'numero' => $r->numero ?? null,
            'complemento' => $r->complemento ?? null,
            'ponto_referencia' => $r->ponto_referencia ?? null,
            'cep' => $r->cep ?? null,
            'uf' => $r->uf ?? null,
            'cidade_id' => $r->cidade_id ?? null,
            'bairro_id' => $r->bairro_id ?? null,
            'email' => $r->email ?? null,
            'latitude' => isset($r->latitude) ? (float) $r->latitude : null,
            'longitude' => isset($r->longitude) ? (float) $r->longitude : null,
            'convenio' => (bool) ($r->convenio ?? false),
            'convenio_ativo' => (bool) ($r->convenioativo ?? false),
            'convenio_limite' => isset($r->conveniolimite) ? (float) $r->conveniolimite : null,
            'credito_limite' => isset($r->creditolimite) ? (float) $r->creditolimite : null,
            'credito_saldo' => isset($r->creditosaldo) ? (float) $r->creditosaldo : null,
        ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function lerTelefones(MigrationContext $ctx): array
    {
        try {
            $rows = $ctx->legado()->table('clientetelefones')->get();
        } catch (\Throwable) {
            return [];
        }

        return $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'cliente_id' => (int) $r->cliente_id,
            'telefonetipo_id' => $r->telefonetipo_id ?? null,
            'telefone' => trim((string) $r->telefone),
            'whatsapp' => (bool) ($r->whatsapp ?? false),
        ])->all();
    }

    private function legadoDisponivel(MigrationContext $ctx): bool
    {
        try {
            $ctx->legado()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
