<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\User;

/**
 * Seeder de DEPLOY — garante acesso ao ERP de forma idempotente.
 *
 * O banco de produção nasce vazio. Cria a cadeia mínima de dados respeitando
 * TODAS as foreign keys NOT NULL (verificadas no schema real), nesta ordem:
 *
 *   estados(uf) -> cidades(uf) -> empresas_grupos -> bairros(grupo,cidade)
 *               -> empresas(grupo,cidade,bairro,uf) -> users -> empresa_user
 *
 * Colunas obrigatórias confirmadas:
 *   estados:         uf (PK), descricao
 *   cidades:         descricao, uf (FK->estados.uf)
 *   empresas_grupos: descricao
 *   bairros:         grupo_id, cidade_id, descricao
 *   empresas:        grupo_id, razao_social, cidade_id, cep, bairro_id, uf
 *   users:           email, password, support
 *
 * Idempotente (verifica antes de inserir). Senha via ADMIN_SEED_PASSWORD.
 */
class DeployAdminSeeder extends Seeder
{
    public function run()
    {
        $now = now();
        $email = env('ADMIN_SEED_EMAIL', 'admin');
        $senha = env('ADMIN_SEED_PASSWORD', 'admin1234');
        $uf = 'PR';

        // 1) Estado (PK = uf). Raiz da cadeia geográfica.
        if (DB::table('estados')->where('uf', $uf)->doesntExist()) {
            DB::table('estados')->insert([
                'uf' => $uf, 'descricao' => 'Paraná',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // 2) Grupo de empresas.
        if (DB::table('empresas_grupos')->where('id', 1)->doesntExist()) {
            DB::table('empresas_grupos')->insert([
                'id' => 1, 'descricao' => 'Grupo Padrão', 'ativo' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // 3) Cidade (uf -> estados.uf).
        if (DB::table('cidades')->where('id', 1)->doesntExist()) {
            DB::table('cidades')->insert([
                'id' => 1, 'descricao' => 'Cidade Padrão', 'uf' => $uf,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // 4) Bairro (grupo_id, cidade_id).
        if (DB::table('bairros')->where('id', 1)->doesntExist()) {
            DB::table('bairros')->insert([
                'id' => 1, 'grupo_id' => 1, 'cidade_id' => 1, 'descricao' => 'Centro',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // 5) Empresa (grupo_id, razao_social, cidade_id, cep, bairro_id, uf).
        if (DB::table('empresas')->where('id', 1)->doesntExist()) {
            DB::table('empresas')->insert([
                'id' => 1, 'grupo_id' => 1,
                'razao_social'  => 'Empresa Padrão',
                'nome_informal' => 'Empresa Padrão',
                'cidade_id' => 1, 'bairro_id' => 1, 'cep' => '00000000', 'uf' => $uf,
                'ativo' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // 6) Usuário admin (idempotente). support é NOT NULL.
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

        // 7) Vínculo empresa_user.
        $vinc = DB::table('empresa_user')
            ->where('empresa_id', 1)->where('user_id', $user->id)->exists();
        if (! $vinc) {
            DB::table('empresa_user')->insert(['empresa_id' => 1, 'user_id' => $user->id]);
        }

        $this->command->info("Admin garantido: email='{$email}' (senha via ADMIN_SEED_PASSWORD).");
    }
}
