<?php

namespace Database\Seeders;

use App\Models\Saas\PlatformAdmin;
use Database\Seeders\Concerns\ResolveSenhaSeed;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * SuperAdmin inicial (P4) — idempotente. Cria o operador da plataforma a partir de
 * env (SUPERADMIN_SEED_EMAIL / SUPERADMIN_SEED_PASSWORD), obrigatórias em produção
 * (ver ResolveSenhaSeed). 2FA NÃO vem habilitado: o admin configura no 1º acesso.
 *
 * Identidade separada dos `users` de tenant — não há flag de superadmin em users.
 * Este acesso é cross-tenant: senha fraca aqui vaza TODAS as revendas.
 */
class SuperAdminSeeder extends Seeder
{
    use ResolveSenhaSeed;

    public function run(): void
    {
        $email = $this->emailSeed('SUPERADMIN_SEED_EMAIL', 'superadmin@gasemcasa.com');
        $senha = $this->senhaSeed('SUPERADMIN_SEED_PASSWORD', 'SuperAdmin da plataforma');

        $admin = PlatformAdmin::firstOrNew(['email' => $email]);
        $admin->nome = $admin->nome ?: 'Super Administrador';
        $admin->ativo = true;
        if (! $admin->exists) {
            $admin->password = Hash::make($senha);
        }
        $admin->save();
    }
}
