<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder de DEPLOY do módulo Monitoramento — garante acesso ao painel /monitora
 * de forma idempotente. Opera no schema 'monitora' (conexão 'monitora').
 *
 * Cria a cadeia mínima: empresas_grupos -> empresas -> users -> empresa_user.
 * Senha via MONITORA_SEED_PASSWORD (default 'monitora123' — trocar depois).
 */
class DeployMonitoraAdminSeeder extends Seeder
{
    public function run()
    {
        $c = DB::connection('monitora');
        $now = now();
        $email = env('MONITORA_SEED_EMAIL', 'admin');
        $senha = env('MONITORA_SEED_PASSWORD', 'monitora123');

        if ($c->table('empresas_grupos')->where('id', 1)->doesntExist()) {
            $c->table('empresas_grupos')->insert([
                'id' => 1, 'descricao' => 'Grupo Padrão', 'ativo' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        if ($c->table('empresas')->where('id', 1)->doesntExist()) {
            $c->table('empresas')->insert([
                'id' => 1, 'grupo_id' => 1,
                'razao_social' => 'Empresa Padrão', 'nome_informal' => 'Empresa Padrão',
                'ativo' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $uid = $c->table('users')->where('email', $email)->value('id');
        if (! $uid) {
            $uid = $c->table('users')->insertGetId([
                'name' => 'Administrador', 'email' => $email,
                'password' => bcrypt($senha), 'empresa_id' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        } else {
            $c->table('users')->where('id', $uid)->update([
                'password' => bcrypt($senha), 'empresa_id' => 1, 'updated_at' => $now,
            ]);
        }

        if ($c->table('empresa_user')->where('user_id', $uid)->where('empresa_id', 1)->doesntExist()) {
            $c->table('empresa_user')->insert(['empresa_id' => 1, 'user_id' => $uid]);
        }

        $this->command->info("Admin do Monitoramento garantido: email='{$email}' (senha via MONITORA_SEED_PASSWORD).");
    }
}
