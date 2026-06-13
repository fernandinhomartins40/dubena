<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\User;

/**
 * Seeder de DEPLOY — garante acesso ao ERP de forma idempotente.
 *
 * O banco de produção nasce vazio (nenhum dado base). Para permitir o primeiro
 * login, cria a cadeia mínima na ordem das FKs:
 *   grupo -> cidade -> bairro -> empresa -> usuário (+ vínculo empresa_user)
 * Usa as colunas REAIS do schema (verificadas). Idempotente (verifica antes
 * de inserir). Senha do admin via ADMIN_SEED_PASSWORD (default 'admin1234').
 */
class DeployAdminSeeder extends Seeder
{
    public function run()
    {
        $now = now();
        $email = env('ADMIN_SEED_EMAIL', 'admin');
        $senha = env('ADMIN_SEED_PASSWORD', 'admin1234');

        // 1) Grupo de empresas (obrigatório: descricao).
        if (DB::table('empresas_grupos')->where('id', 1)->doesntExist()) {
            DB::table('empresas_grupos')->insert([
                'id' => 1, 'descricao' => 'Grupo Padrão', 'ativo' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // 2) Cidade (obrigatório: descricao).
        if (DB::table('cidades')->where('id', 1)->doesntExist()) {
            DB::table('cidades')->insert([
                'id' => 1, 'descricao' => 'Cidade Padrão', 'uf' => 'PR',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // 3) Bairro (obrigatório: grupo_id, cidade_id, descricao).
        if (DB::table('bairros')->where('id', 1)->doesntExist()) {
            DB::table('bairros')->insert([
                'id' => 1, 'grupo_id' => 1, 'cidade_id' => 1,
                'descricao' => 'Centro',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // 4) Empresa (obrigatórios: grupo_id, razao_social, cidade_id, cep, bairro_id, uf).
        if (DB::table('empresas')->where('id', 1)->doesntExist()) {
            DB::table('empresas')->insert([
                'id' => 1, 'grupo_id' => 1,
                'razao_social'  => 'Empresa Padrão',
                'nome_informal' => 'Empresa Padrão',
                'cidade_id' => 1, 'bairro_id' => 1, 'cep' => '00000000', 'uf' => 'PR',
                'ativo' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // 5) Usuário admin (idempotente). support é NOT NULL.
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name'       => 'Administrador',
                'password'   => bcrypt($senha),
                'empresa_id' => 1,
                'ativo'      => 1,
                'support'    => 1,
            ]
        );

        // 6) Vínculo empresa_user.
        $vinc = DB::table('empresa_user')
            ->where('empresa_id', 1)->where('user_id', $user->id)->exists();
        if (! $vinc) {
            DB::table('empresa_user')->insert(['empresa_id' => 1, 'user_id' => $user->id]);
        }

        $this->command->info("Admin garantido: email='{$email}' (senha via ADMIN_SEED_PASSWORD).");
    }
}
