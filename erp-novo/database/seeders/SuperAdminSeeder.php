<?php

namespace Database\Seeders;

use App\Models\Saas\PlatformAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * SuperAdmin inicial (P4) — idempotente. Cria o operador da plataforma a partir de
 * env (SUPERADMIN_SEED_EMAIL / SUPERADMIN_SEED_PASSWORD). 2FA NÃO vem habilitado:
 * o admin configura no 1º acesso (recomendado/obrigatório por política operacional).
 *
 * Identidade separada dos `users` de tenant — não há flag de superadmin em users.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPERADMIN_SEED_EMAIL', 'superadmin@gasemcasa.com');
        $senha = env('SUPERADMIN_SEED_PASSWORD', 'superadmin1234');

        $admin = PlatformAdmin::firstOrNew(['email' => $email]);
        $admin->nome = $admin->nome ?: 'Super Administrador';
        $admin->ativo = true;
        if (! $admin->exists) {
            $admin->password = Hash::make($senha);
        }
        $admin->save();
    }
}
