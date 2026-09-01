<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\Concerns\ResolveSenhaSeed;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Acessos da REDE Dubena, como o cliente vai operar de verdade.
 *
 * Contexto do modelo: `grupo` = rede (o dono da revenda) e `empresa` = cada
 * estabelecimento. A Dubena é uma rede só: as 7 empresas vindas do Oracle têm
 * CNPJ de mesma raiz (04.190.715/000X-XX), ou seja, matriz + filiais. As
 * empresas vindas do Monitora (Central Gás, QTI, Dubena Particular) são frota
 * de TERCEIROS que a Dubena monitora — continuam na mesma rede, não são
 * clientes do SaaS.
 *
 * Por que não basta o `support`: um usuário com `support = true` ignora todo o
 * RBAC e enxerga qualquer empresa, o que esconde exatamente o que se quer
 * testar (papel por filial, troca de empresa, negativa de acesso). Aqui o dono
 * é um usuário COMUM com papel Administrador em cada filial da sua rede.
 *
 * Idempotente. Senhas vêm do ambiente; os defaults são para homologação.
 */
class AcessoRedeDubenaSeeder extends Seeder
{
    // F7-11 — a senha vem do ambiente, com o mesmo tratamento dos seeders de
    // deploy: obrigatoria em producao, gerada e ANUNCIADA fora dela.
    //
    // Antes o default era `dono@2026` / `gerente@2026`: senha conhecida que
    // entrava sozinha se a variavel nao estivesse definida — e o dono da rede
    // enxerga TODAS as filiais.
    use ResolveSenhaSeed;

    public function run(): void
    {
        $matriz = $this->matrizDaRede();
        if ($matriz === null) {
            $this->command?->warn('Nenhuma empresa com dados — rode o ETL antes.');

            return;
        }

        $rede = (int) $matriz->grupo_id;
        $filiais = Empresa::where('grupo_id', $rede)->orderBy('id')->get();

        $this->command?->info(
            "Rede {$rede}: {$filiais->count()} empresa(s); matriz = [{$matriz->id}] {$matriz->razao_social}"
        );

        // ── Dono da rede: enxerga TODAS as filiais e alterna entre elas ──
        $dono = $this->usuario(
            email: 'dono@dubena.com.br',
            nome: 'Vilso Dubena (dono da rede)',
            empresaPadrao: $matriz,
            senha: $this->senhaSeed('DONO_SEED_PASSWORD', 'Dono da rede'),
            support: false,
        );

        // Vincula TODAS, inclusive a empresa padrão do usuário.
        //
        // `podeAcessarEmpresa` aceita a empresa padrão OU uma da pivot. Deixar a
        // matriz de fora funcionava só enquanto ela era a padrão: ao trocar para
        // outra filial, `users.empresa_id` muda, a matriz deixa de ser padrão,
        // não está na pivot — e o dono fica preso na filial, com 403 ao tentar
        // voltar.
        foreach ($filiais as $filial) {
            $dono->empresas()->syncWithoutDetaching([$filial->id]);
        }

        // Papel do dono é GLOBAL na rede (`role_user.empresa_id` nulo): vale em
        // qualquer filial que ele acesse. Um papel por filial não caberia — o
        // pivot é único por (user_id, role_id) e o papel pertence ao grupo.
        $this->papelNaEmpresa($dono, $matriz, 'Administrador', global: true);

        // ── Gerente de UMA filial: serve para provar o isolamento entre irmãs ──
        $filialSecundaria = $filiais->firstWhere('id', '!=', $matriz->id);
        if ($filialSecundaria !== null) {
            $gerente = $this->usuario(
                email: 'gerente.filial@dubena.com.br',
                nome: 'Gerente da Filial',
                empresaPadrao: $filialSecundaria,
                senha: $this->senhaSeed('GERENTE_SEED_PASSWORD', 'Gerente da filial'),
                support: false,
            );
            $this->papelNaEmpresa($gerente, $filialSecundaria, 'Gerente');
        }

        // ── Operador da matriz: papel mais restrito, para ver o RBAC negando ──
        $operador = User::where('email', 'operador@dubena.com.br')->first();
        if ($operador !== null) {
            $operador->support = false;
            $operador->save();
            $this->papelNaEmpresa($operador, $matriz, 'Operador');
        }

        $this->command?->info('Acessos da rede prontos.');
    }

    /** A empresa que concentra os dados migrados é a matriz operacional. */
    private function matrizDaRede(): ?Empresa
    {
        $id = DB::table('clientes')
            ->select('empresa_id')
            ->groupBy('empresa_id')
            ->orderByRaw('COUNT(*) DESC')
            ->value('empresa_id');

        return $id !== null ? Empresa::find($id) : Empresa::orderBy('id')->first();
    }

    private function usuario(
        string $email, string $nome, Empresa $empresaPadrao, string $senha, bool $support
    ): User {
        $user = User::firstOrNew(['email' => $email]);
        $user->name = $user->name ?: $nome;
        $user->empresa_id = $empresaPadrao->id;
        $user->grupo_id = $empresaPadrao->grupo_id;
        $user->support = $support;
        $user->ativo = true;
        if (! $user->exists) {
            $user->password = Hash::make($senha);
        }
        $user->save();

        return $user;
    }

    /**
     * Vincula um papel do grupo ao usuário.
     *
     * `global: true` grava `empresa_id` nulo no pivot — o papel passa a valer em
     * TODA empresa da rede (é como `User::temPermissao` avalia). Use para o dono
     * da rede. Com `global: false`, o papel vale só na empresa informada, que é
     * o caso do gerente de uma filial.
     */
    private function papelNaEmpresa(
        User $user, Empresa $empresa, string $nomePapel, bool $global = false
    ): void {
        $role = Role::where('grupo_id', $empresa->grupo_id)
            ->where('nome', $nomePapel)
            ->first();

        if ($role === null) {
            $this->command?->warn("Papel '{$nomePapel}' não existe — rode o RbacSeeder.");

            return;
        }

        $jaTem = DB::table('role_user')
            ->where('user_id', $user->id)
            ->where('role_id', $role->id)
            ->exists();

        if (! $jaTem) {
            DB::table('role_user')->insert([
                'user_id' => $user->id,
                'role_id' => $role->id,
                'empresa_id' => $global ? null : $empresa->id,
                'herda_filhos' => true,
            ]);
        }
    }
}
