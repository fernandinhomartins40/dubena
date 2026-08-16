<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Role;
use App\Models\Saas\PlatformAdmin;
use App\Models\User;
use Database\Seeders\Concerns\ResolveSenhaSeed;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Usuários de acesso para o ambiente carregado com os DADOS MIGRADOS.
 *
 * Diferença para o DeployAdminSeeder: aquele cria "Grupo Padrão"/"Empresa
 * Matriz" do zero, o que não serve aqui — depois da migração o tenant real é a
 * empresa do legado (Distribuidora Dubena, id 2), que concentra os clientes e
 * pedidos. Este seeder amarra os usuários A ESSA empresa, para quem entrar já
 * cair na base de verdade em vez de num tenant vazio.
 *
 * Idempotente: pode rodar de novo sem duplicar nem sobrescrever senha trocada.
 *
 * Senhas vêm do ambiente: obrigatórias em produção, geradas e ecoadas no
 * console fora dela (ver ResolveSenhaSeed).
 */
class AcessoMigracaoSeeder extends Seeder
{
    use ResolveSenhaSeed;

    public function run(): void
    {
        $empresa = $this->empresaComMaisDados();
        if ($empresa === null) {
            $this->command?->warn('Nenhuma empresa encontrada — rode o ETL antes.');

            return;
        }

        $this->command?->info("Empresa alvo: [{$empresa->id}] {$empresa->razao_social}");

        $senhaAdmin = $this->senhaSeed('ADMIN_SEED_PASSWORD', 'Admin Dubena');
        $senhaOperador = $this->senhaSeed('OPERADOR_SEED_PASSWORD', 'Operador Dubena');
        $senhaSuper = $this->senhaSeed('SUPERADMIN_SEED_PASSWORD', 'SuperAdmin da plataforma');

        // 1) Admin do tenant — `support` dá bypass de RBAC (acesso total),
        //    garantindo entrada mesmo se os papéis não estiverem sincronizados.
        $admin = $this->usuario(
            'admin@dubena.com.br', 'Administrador Dubena', $empresa, $senhaAdmin, support: true
        );

        // 2) Usuário comum, para exercitar o RBAC de verdade (sem bypass):
        //    é com ele que se testa o que cada papel enxerga.
        $operador = $this->usuario(
            'operador@dubena.com.br', 'Operador Dubena', $empresa, $senhaOperador, support: false
        );

        $this->vincularPapel($operador, $empresa, 'Operador');
        $this->vincularPapel($admin, $empresa, 'Administrador');

        // 3) SuperAdmin da plataforma (identidade separada) — é quem acessa a
        //    ferramenta de migração em /superadmin.
        $super = PlatformAdmin::firstOrNew(['email' => 'superadmin@gasemcasa.com']);
        $super->nome = $super->nome ?: 'Super Administrador';
        $super->ativo = true;
        if (! $super->exists) {
            $super->password = Hash::make($senhaSuper);
        }
        $super->save();

        $this->command?->info('Acessos prontos.');
    }

    /** A empresa que recebeu a migração (a com mais clientes), não uma nova. */
    private function empresaComMaisDados(): ?Empresa
    {
        $id = DB::table('clientes')
            ->select('empresa_id')
            ->groupBy('empresa_id')
            ->orderByRaw('COUNT(*) DESC')
            ->value('empresa_id');

        return $id !== null
            ? Empresa::find($id)
            : Empresa::orderBy('id')->first();
    }

    private function usuario(
        string $email, string $nome, Empresa $empresa, string $senha, bool $support
    ): User {
        $user = User::firstOrNew(['email' => $email]);
        $user->name = $user->name ?: $nome;
        $user->empresa_id = $empresa->id;
        $user->grupo_id = $empresa->grupo_id;
        $user->support = $support;
        $user->ativo = true;
        // Só define a senha na criação: não sobrescreve uma trocada manualmente.
        if (! $user->exists) {
            $user->password = Hash::make($senha);
        }
        $user->save();

        return $user;
    }

    /** Vincula o papel do grupo ao usuário, no escopo da empresa. */
    private function vincularPapel(User $user, Empresa $empresa, string $nomePapel): void
    {
        $role = Role::where('grupo_id', $empresa->grupo_id)
            ->where('nome', $nomePapel)
            ->first();

        if ($role === null) {
            $this->command?->warn("Papel '{$nomePapel}' não existe — rode o RbacSeeder.");

            return;
        }

        DB::table('role_user')->updateOrInsert(
            ['user_id' => $user->id, 'role_id' => $role->id],
            ['empresa_id' => $empresa->id],
        );
    }
}
