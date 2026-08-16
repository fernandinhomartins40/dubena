<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Grupo;
use App\Models\User;
use Database\Seeders\Concerns\ResolveSenhaSeed;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder de bootstrap do erp-novo (idempotente) — roda no deploy.
 *
 * O banco novo nasce vazio; sem um usuário não há login na SPA. Cria um grupo +
 * empresa-matriz + usuário admin (support = bypass de RBAC). Credenciais via env
 * (ADMIN_SEED_EMAIL / ADMIN_SEED_PASSWORD) — obrigatórias em produção, geradas
 * fora dela (ver ResolveSenhaSeed).
 *
 * É o admin que recebe a massa de demonstração (DemoGuarapuavaSeeder popula a
 * empresa-matriz).
 */
class DeployAdminSeeder extends Seeder
{
    use ResolveSenhaSeed;

    public function run(): void
    {
        $grupo = Grupo::firstOrCreate(
            ['descricao' => 'Grupo Padrão'],
            ['ativo' => true],
        );

        $empresa = Empresa::firstOrCreate(
            ['grupo_id' => $grupo->id, 'razao_social' => 'Empresa Matriz'],
            ['nome_fantasia' => 'Matriz', 'uf' => 'SP', 'matriz' => true, 'ativo' => true],
        );

        $email = $this->emailSeed('ADMIN_SEED_EMAIL', 'admin@gasemcasa.com');
        // Resolvida sempre (mesmo quando o admin já existe): em produção a
        // ausência da env var precisa falhar o deploy, não passar em silêncio.
        $senha = $this->senhaSeed('ADMIN_SEED_PASSWORD', 'Admin do tenant');

        $admin = User::firstOrNew(['email' => $email]);
        $admin->name = $admin->name ?: 'Administrador';
        $admin->empresa_id = $empresa->id;
        $admin->grupo_id = $grupo->id;
        $admin->support = true;   // bypass de RBAC (acesso total)
        $admin->ativo = true;
        // Só define a senha ao CRIAR (não sobrescreve uma trocada manualmente).
        if (! $admin->exists) {
            $admin->password = Hash::make($senha);
        }
        $admin->save();
    }
}
