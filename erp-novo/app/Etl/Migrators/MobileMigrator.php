<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Invariants\IntegrityInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Etl\Support\PreservaIdsDoLegado;
use Illuminate\Support\Facades\DB;

/**
 * N10 — migra os DEVICES do legado (tablets/celulares dos entregadores).
 *
 * REESCRITO após a auditoria 2026-08-14: a versão anterior lia `app_devices` e
 * `pagamentos_online` — tabelas que NUNCA existiram no legado (zero linhas em
 * silêncio). A fonte real dos devices é `ANDROIDS` (espelhada como `androids`);
 * pagamentos online do app vivem no sgcm_api e são migrados pelo
 * AppGasEmCasaMigrator (transacoesonline).
 */
final class MobileMigrator implements Migrator
{
    use PreservaIdsDoLegado;

    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'mobile';
    }

    public function dependeDe(): array
    {
        return ['empresas', 'users'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->ctxAtual = $ctx;

        if (! $this->tabelaExiste($ctx, 'androids')) {
            return new MigrationResult($this->nome(), 0, 0, 0,
                ['tabela `androids` ausente no espelho do legado — devices NÃO migrados '
                    .'(re-rodar espelhar_oracle.py)']);
        }

        $idsUser = [];
        foreach (DB::table('users')->pluck('id') as $id) {
            $idsUser[(int) $id] = true;
        }
        $idsEmpresa = [];
        foreach (DB::table('empresas')->pluck('id') as $id) {
            $idsEmpresa[(int) $id] = true;
        }

        $lidos = 0;
        $pulados = 0;
        $lote = [];

        foreach ($ctx->legado()->table('androids')->orderBy('id')->get() as $r) {
            $lidos++;
            $user = (int) ($r->user_id ?? 0);
            if (! isset($idsUser[$user])) {
                $pulados++; // user_id é NOT NULL no destino: device sem dono não entra

                continue;
            }
            $empresa = (int) ($r->empresa_id ?? 0);
            $lote[] = [
                'id' => (int) $r->id,
                'user_id' => $user,
                'empresa_id' => isset($idsEmpresa[$empresa]) ? $empresa : null,
                'plataforma' => 'android',
                'push_token' => ($r->registrationid ?? null) !== null
                    ? mb_substr((string) $r->registrationid, 0, 255) : null,
                'device_id' => mb_substr((string) ($r->androidid ?? $r->serie ?? ''), 0, 100) ?: null,
                'ativo' => $this->booleano($r->ativo ?? '1'),
                'created_at' => $r->created_at ?? null,
            ];
        }

        $gravados = 0;
        if (! $ctx->dryRun && $lote !== []) {
            $gravados = $this->gravarPreservandoId('app_devices', $lote);
        }

        $avisos = [];
        if ($pulados > 0) {
            $avisos[] = "{$pulados} device(s) sem user vinculado no legado — não migrados "
                .'(user_id é obrigatório no destino)';
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
        $ctx = $this->ctxAtual ?? new MigrationContext;
        if (! $this->tabelaExiste($ctx, 'androids')) {
            return [];
        }

        return [
            new CountInvariant($ctx, 'androids', 'app_devices',
                whereLegado: 'user_id IS NOT NULL'),
            new IntegrityInvariant($ctx, 'app_devices', 'user_id', 'users'),
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

    private function booleano(mixed $v): bool
    {
        return in_array(mb_strtoupper(trim((string) ($v ?? ''))), ['1', 'S', 'T', 'TRUE', 'Y'], true);
    }
}
