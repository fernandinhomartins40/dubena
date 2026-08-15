<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\IntegrityInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Etl\Support\PreservaIdsDoLegado;
use Illuminate\Support\Facades\DB;

/**
 * Migra os USUÁRIOS do ERP legado (+ vínculo empresa_user).
 *
 * Por que existe: nenhum migrator lia `users` — e o PedidosMigrator anula
 * `atendente_user_id`/`entregador_user_id` de todo pedido cujo user não existe
 * no destino. Sem esta carga, os 400 mil pedidos perdem atendente e entregador
 * (comissões e relatórios de entrega ficam vazios). Achado P0 da auditoria
 * (AUDITORIA_MIGRACAO_DADOS_LEGADOS.md).
 *
 * Decisões:
 *  - ids preservados (pedidos/contausers referenciam o user pelo id legado);
 *  - `email` do legado é um LOGIN ("acir07"), não e-mail — migra como está;
 *  - hash de senha do legado é bcrypt (Laravel) — compatível, login preservado;
 *  - id já ocupado no destino (seed/admin) NÃO é sobrescrito: vira aviso.
 *    Sobrescrever poderia trocar a senha do admin criado na instalação;
 *  - nenhum papel (role) é atribuído: usuário migrado entra ativo porém sem
 *    permissões — quem define o papel é o administrador, na tela de usuários.
 */
final class UsersMigrator implements Migrator
{
    use PreservaIdsDoLegado;

    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'users';
    }

    public function dependeDe(): array
    {
        return ['empresas'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->ctxAtual = $ctx;

        if (! $this->tabelaExiste($ctx, 'users')) {
            return new MigrationResult($this->nome(), 0, 0, 0,
                ['tabela `users` ausente no espelho do legado — usuários NÃO migrados '
                    .'(pedidos ficarão sem atendente/entregador)']);
        }

        $grupoDaEmpresa = [];
        foreach (DB::table('empresas')->get(['id', 'grupo_id']) as $e) {
            $grupoDaEmpresa[(int) $e->id] = (int) $e->grupo_id;
        }

        $existentes = [];
        foreach (DB::table('users')->get(['id', 'email']) as $u) {
            $existentes[(int) $u->id] = (string) $u->email;
        }

        $lidos = 0;
        $pulados = 0;
        $conflitos = [];
        $lote = [];

        foreach ($ctx->legado()->table('users')->orderBy('id')->get() as $r) {
            $lidos++;
            $id = (int) $r->id;
            $email = trim((string) $r->email);

            if (isset($existentes[$id]) && $existentes[$id] !== $email) {
                // id ocupado por outro usuário (seed/admin): não sobrescrever.
                $pulados++;
                $conflitos[] = "{$id} ({$email})";

                continue;
            }

            $empresa = (int) ($r->empresa_id ?? 0);
            $lote[] = [
                'id' => $id,
                'name' => trim((string) ($r->name ?? $email)) ?: $email,
                'email' => $email,
                'password' => (string) $r->password,
                'empresa_id' => isset($grupoDaEmpresa[$empresa]) ? $empresa : null,
                'grupo_id' => $grupoDaEmpresa[$empresa] ?? null,
                'support' => (bool) ($r->support ?? false),
                'ativo' => (bool) ($r->ativo ?? true),
                'created_at' => $r->created_at ?? null,
            ];
        }

        $gravados = 0;
        if (! $ctx->dryRun && $lote !== []) {
            $gravados += $this->gravarPreservandoId('users', $lote);
        }

        // ── Vínculo empresa × usuário (multiempresa do legado) ──
        // A tabela legada NÃO tem coluna id (é pivot pura): dedup por par e
        // insert só do que falta — sem preservação de id aqui.
        if ($this->tabelaExiste($ctx, 'empresa_user')) {
            $idsUser = [];
            foreach (DB::table('users')->pluck('id') as $id) {
                $idsUser[(int) $id] = true;
            }
            $paresExistentes = [];
            foreach (DB::table('empresa_user')->get(['user_id', 'empresa_id']) as $p) {
                $paresExistentes[$p->user_id.':'.$p->empresa_id] = true;
            }

            $vinculos = [];
            foreach ($ctx->legado()->table('empresa_user')->get(['user_id', 'empresa_id']) as $r) {
                $lidos++;
                $user = (int) $r->user_id;
                $empresa = (int) $r->empresa_id;
                $par = $user.':'.$empresa;
                if (! isset($idsUser[$user]) || ! isset($grupoDaEmpresa[$empresa])
                    || isset($paresExistentes[$par])) {
                    $pulados++;

                    continue;
                }
                $paresExistentes[$par] = true;
                $vinculos[] = [
                    'user_id' => $user,
                    'empresa_id' => $empresa,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if (! $ctx->dryRun && $vinculos !== []) {
                DB::table('empresa_user')->insert($vinculos);
                $gravados += count($vinculos);
            }
        }

        $avisos = [];
        if ($conflitos !== []) {
            $avisos[] = count($conflitos).' usuário(s) com id já ocupado no destino — '
                .'mantidos como estão: '.implode(', ', array_slice($conflitos, 0, 5));
        }
        if ($gravados > 0) {
            $avisos[] = 'usuários migrados SEM papel (role): atribuir permissões na tela de usuários';
        }

        return new MigrationResult(
            migrator: $this->nome(),
            lidos: $lidos,
            gravados: $ctx->dryRun ? 0 : $gravados,
            pulados: $pulados,
            avisos: $avisos,
        );
    }

    public function invariantes(): array
    {
        $ctx = $this->ctxAtual ?? new MigrationContext();
        if (! $this->tabelaExiste($ctx, 'users')) {
            return [];
        }

        return [
            new IntegrityInvariant($ctx, 'users', 'empresa_id', 'empresas'),
            new IntegrityInvariant($ctx, 'empresa_user', 'user_id', 'users'),
        ];
    }

    private function tabelaExiste(MigrationContext $ctx, string $tabela): bool
    {
        try {
            return $ctx->legado()->getSchemaBuilder()->hasTable($tabela);
        } catch (\Throwable) {
            return false;
        }
    }
}
