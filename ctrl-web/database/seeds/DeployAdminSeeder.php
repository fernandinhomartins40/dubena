<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\User;

/**
 * Seeder de DEPLOY — garante um usuário de acesso de forma idempotente.
 *
 * Diferente do UserTableSeeder legado (ids fixos, quebra se rodar 2x e depende
 * de outros seeders), este usa updateOrCreate e garante a empresa/grupo mínimos.
 * Seguro para rodar a cada deploy. Senha vem de env (ADMIN_SEED_PASSWORD),
 * default 'admin1234' — TROCAR no primeiro acesso.
 */
class DeployAdminSeeder extends Seeder
{
    public function run()
    {
        $email = env('ADMIN_SEED_EMAIL', 'admin');
        $senha = env('ADMIN_SEED_PASSWORD', 'admin1234');

        // Garante grupo de empresa (id 1) e empresa (id 1) mínimos, se não houver.
        if (DB::table('empresas_grupos')->where('id', 1)->doesntExist()) {
            DB::table('empresas_grupos')->insert([
                'id' => 1, 'razao_social' => 'Grupo Padrão', 'ativo' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        if (DB::table('empresas')->where('id', 1)->doesntExist()) {
            DB::table('empresas')->insert([
                'id' => 1, 'grupo_id' => 1, 'razao_social' => 'Empresa Padrão',
                'nome_informal' => 'Empresa Padrão', 'ativo' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Usuário admin (idempotente).
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name'       => 'Administrador',
                'password'   => bcrypt($senha),
                'empresa_id' => 1,
                'grupo_id'   => 1,
                'ativo'      => 1,
                'support'    => 1,
            ]
        );

        // Vínculo empresa_user (idempotente).
        $exists = DB::table('empresa_user')
            ->where('empresa_id', 1)->where('user_id', $user->id)->exists();
        if (! $exists) {
            DB::table('empresa_user')->insert(['empresa_id' => 1, 'user_id' => $user->id]);
        }

        $this->command->info("Admin garantido: email='{$email}' (senha via ADMIN_SEED_PASSWORD).");
    }
}
