<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * M1.2 (RBAC) — semeia o modelo de papéis/permissões (spatie) a partir do legado.
 *
 * Idempotente. NÃO toca em `menuusers`/`menus` (que seguem funcionando durante a
 * transição) — apenas DERIVA o RBAC novo a partir deles:
 *
 *  1. PERMISSÕES: para cada menu-folha (menus.descricao = '<modulo>.index'), gera
 *     '<modulo>.view|create|edit|delete'. Guard 'web' (igual ERP/Filament).
 *  2. PAPÉIS iniciais (Admin/Gerente/Caixa/Vendedor/Estoquista/Fiscal). Admin recebe
 *     todas as permissões; os demais ficam vazios (a definir com o cliente).
 *  3. MIGRAÇÃO POR USUÁRIO: para cada usuário, converte suas linhas em `menuusers`
 *     (flags visualizar/criar/editar/deletar) em permissões DIRETAS — preserva
 *     exatamente o que cada um já podia. Usuário support=1 recebe o papel Admin.
 */
class RbacSeeder extends Seeder
{
    /** menus.descricao ('cliente.index') → módulo base ('cliente'). */
    private function moduloDoMenu(string $descricao): ?string
    {
        $descricao = trim($descricao);
        if ($descricao === '') {
            return null;
        }
        // pega o token antes do primeiro '.', ex.: 'cliente.index' → 'cliente'
        return explode('.', $descricao)[0] ?: null;
    }

    public function run()
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // --- 1. Permissões a partir dos menus-folha ---
        $modulos = DB::table('menus')
            ->whereNotNull('descricao')->where('descricao', '<>', '')
            ->pluck('descricao')
            ->map(fn ($d) => $this->moduloDoMenu($d))
            ->filter()->unique()->values();

        $acoes = ['view', 'create', 'edit', 'delete'];
        foreach ($modulos as $modulo) {
            foreach ($acoes as $acao) {
                Permission::findOrCreate("$modulo.$acao", 'web');
            }
        }
        $this->command->info('RBAC: permissões = ' . (count($modulos) * count($acoes)) . " ({$modulos->count()} módulos × 4 ações)");

        // --- 2. Papéis iniciais ---
        $papeis = ['Admin', 'Gerente', 'Caixa', 'Vendedor', 'Estoquista', 'Fiscal'];
        foreach ($papeis as $p) {
            Role::findOrCreate($p, 'web');
        }
        // Admin = todas as permissões.
        Role::findByName('Admin', 'web')->syncPermissions(Permission::all());
        $this->command->info('RBAC: papéis = ' . implode(', ', $papeis) . ' (Admin com todas as permissões)');

        // --- 3. Migração por usuário (menuusers → permissões diretas) ---
        // flag legada → ação RBAC
        $flagAcao = ['visualizar' => 'view', 'criar' => 'create', 'editar' => 'edit', 'deletar' => 'delete'];

        $users = DB::table('users')->select('id', 'support')->get();
        $migrados = 0;
        foreach ($users as $u) {
            $user = \App\User::find($u->id);
            if (! $user) {
                continue;
            }

            // support=1 vê tudo → papel Admin (e nada mais a fazer).
            if ((string) $u->support === '1') {
                $user->syncRoles(['Admin']);
                $migrados++;
                continue;
            }

            // demais: deriva permissões diretas das menuusers (qualquer empresa).
            $linhas = DB::table('menuusers')
                ->join('menus', 'menuusers.menu_id', '=', 'menus.id')
                ->where('menuusers.user_id', $u->id)
                ->whereNotNull('menus.descricao')->where('menus.descricao', '<>', '')
                ->select('menus.descricao', 'menuusers.visualizar', 'menuusers.criar', 'menuusers.editar', 'menuusers.deletar')
                ->get();

            $perms = [];
            foreach ($linhas as $l) {
                $modulo = $this->moduloDoMenu($l->descricao);
                if (! $modulo) {
                    continue;
                }
                foreach ($flagAcao as $flag => $acao) {
                    if ((int) $l->{$flag} === 1) {
                        $perms[] = "$modulo.$acao";
                    }
                }
            }
            $perms = array_values(array_unique($perms));
            // só sincroniza permissões que existem (evita erro se algum módulo não gerou)
            $existentes = Permission::whereIn('name', $perms)->pluck('name')->all();
            $user->syncPermissions($existentes);
            $migrados++;
        }
        $this->command->info("RBAC: usuários migrados = $migrados");

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
