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
 *  - PAPEL (role) derivado do legado — ver `papelDe()`. Antes nenhum papel era
 *    atribuído e todo usuário migrado entrava sem permissão alguma (pendência
 *    consciente #2 da auditoria): 74 usuários exigiam ajuste manual um a um.
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

        // ── Papéis (RBAC) ──
        $papeis = $ctx->dryRun ? [0, []] : $this->atribuirPapeis($ctx, $grupoDaEmpresa);
        $gravados += $papeis[0];

        $avisos = [];
        if ($conflitos !== []) {
            $avisos[] = count($conflitos).' usuário(s) com id já ocupado no destino — '
                .'mantidos como estão: '.implode(', ', array_slice($conflitos, 0, 5));
        }
        foreach ($papeis[1] as $aviso) {
            $avisos[] = $aviso;
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

    /**
     * Atribui o papel (role) de cada usuário migrado.
     *
     * O legado NÃO tem RBAC por papel: a permissão vive em `menuusers` (menu ×
     * ação, um a um) mais o flag `support`. Como `menus`/`menuusers` não estão no
     * espelho, não dá para portar a permissão granular — mas dá para derivar o
     * PAPEL, que é o que o modelo novo usa. Duas fontes, ambas verificadas contra
     * os dados:
     *
     *  1. `users.support = 1` → Administrador. É o "vê tudo" do AuthorizeCustom
     *     legado (`User::podeNoMenu` devolve true direto).
     *
     *  2. `users.tipo_id`, cuja semântica foi confirmada cruzando com os 400 mil
     *     pedidos: dos 26 usuários com tipo_id=21, 23 aparecem como entregador e
     *     NENHUM como atendente; já tipo_id 1 e 2 concentram os atendentes. Logo
     *     21 → Entregador, 1/2 → Operador.
     *
     * Quem já tem papel no destino não é tocado (idempotência e respeito ao que o
     * administrador ajustou depois). Sem a role no grupo do usuário, avisa.
     *
     * @param  array<int,int>  $grupoDaEmpresa
     * @return array{0:int,1:list<string>}
     */
    private function atribuirPapeis(MigrationContext $ctx, array $grupoDaEmpresa): array
    {
        if (! $this->tabelaExiste($ctx, 'users')) {
            return [0, []];
        }

        // Entregadores confirmados pelos pedidos — o sinal mais forte que existe.
        $entregadores = [];
        if ($this->tabelaExiste($ctx, 'pedidos')) {
            foreach (
                $ctx->legado()->table('pedidos')
                    ->whereNotNull('entregadoruser_id')
                    ->distinct()->pluck('entregadoruser_id') as $id
            ) {
                $entregadores[(int) $id] = true;
            }
        }

        // roles por (grupo, nome) — o cadastro é por grupo econômico.
        $roles = [];
        foreach (DB::table('roles')->get(['id', 'grupo_id', 'nome']) as $r) {
            $roles[$r->grupo_id.'|'.mb_strtolower(trim((string) $r->nome))] = (int) $r->id;
        }

        $jaTemPapel = [];
        foreach (DB::table('role_user')->pluck('user_id') as $id) {
            $jaTemPapel[(int) $id] = true;
        }

        $usersDoDestino = [];
        foreach (DB::table('users')->get(['id', 'empresa_id']) as $u) {
            $usersDoDestino[(int) $u->id] = $u->empresa_id === null ? null : (int) $u->empresa_id;
        }

        $vinculos = [];
        $semRole = [];
        foreach ($ctx->legado()->table('users')->get(['id', 'tipo_id', 'support']) as $r) {
            $id = (int) $r->id;
            if (! array_key_exists($id, $usersDoDestino) || isset($jaTemPapel[$id])) {
                continue;
            }

            $empresa = $usersDoDestino[$id];
            $grupo = $empresa !== null ? ($grupoDaEmpresa[$empresa] ?? null) : null;
            if ($grupo === null) {
                continue;
            }

            $papel = $this->papelDe($r, isset($entregadores[$id]));
            $roleId = $roles[$grupo.'|'.mb_strtolower($papel)] ?? null;
            if ($roleId === null) {
                $semRole[$papel] = true;

                continue;
            }

            $vinculos[] = [
                'user_id' => $id,
                'role_id' => $roleId,
                'empresa_id' => $empresa,
                'herda_filhos' => true,
            ];
        }

        if ($vinculos !== []) {
            DB::table('role_user')->insert($vinculos);
        }

        $avisos = [];
        if ($vinculos !== []) {
            $avisos[] = count($vinculos).' usuário(s) receberam papel derivado do legado '
                .'(support→Administrador, tipo_id 21/entregas→Entregador, demais→Operador) — '
                .'conferir na tela de usuários';
        }
        if ($semRole !== []) {
            $avisos[] = 'papel(is) inexistente(s) no grupo: '.implode(', ', array_keys($semRole))
                .' — usuários correspondentes ficaram sem papel';
        }

        return [count($vinculos), $avisos];
    }

    /** Papel derivado das evidências do legado (ver atribuirPapeis). */
    private function papelDe(object $r, bool $entregaPedidos): string
    {
        if ((string) ($r->support ?? '0') === '1') {
            return 'Administrador';
        }
        if ((int) ($r->tipo_id ?? 0) === 21 || $entregaPedidos) {
            return 'Entregador';
        }

        return 'Operador';
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
