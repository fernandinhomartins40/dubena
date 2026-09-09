<?php

use App\Domain\Shared\PermissaoCatalogo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sincroniza a tabela `permissions` com o catálogo do código — e devolve as
 * chaves novas ao papel Administrador.
 *
 * POR QUE UMA MIGRATION E NÃO O SEEDER: o deploy do erp-novo roda **somente**
 * `php artisan migrate` (ver .github/workflows/deploy-erp-novo-*.yml). O
 * `RbacSeeder`, que era quem populava `permissions` a partir do catálogo, NUNCA
 * é executado em produção. O efeito: toda permissão nova ficava só no código, o
 * Gate negava para todo mundo e a funcionalidade nascia inacessível — inclusive
 * para o dono da rede. Foi exatamente o que aconteceu com
 * `cliente.export.completo`: a opção não aparecia no menu porque a chave não
 * existia no banco.
 *
 * O comentário do RbacSeeder ("chaves novas entram aqui") descrevia uma
 * intenção que o pipeline não cumpria. Esta migration passa a ser o ponto que
 * de fato executa, e a correção vale para toda chave futura.
 *
 * É IDEMPOTENTE e ADITIVA: insere o que falta, atualiza descrição, e NUNCA
 * remove permissão nem revoga concessão existente. Remover aqui seria perigoso
 * — uma chave retirada do catálogo por engano derrubaria acesso em produção sem
 * ninguém perceber.
 */
return new class extends Migration
{
    public function up(): void
    {
        $catalogo = PermissaoCatalogo::comDescricoes();
        $agora = now();

        // 1) Toda chave do catálogo precisa existir na tabela.
        foreach ($catalogo as $chave => $descricao) {
            DB::table('permissions')->updateOrInsert(
                ['chave' => $chave],
                ['descricao' => $descricao, 'updated_at' => $agora, 'created_at' => $agora],
            );
        }

        // 2) O Administrador é "tudo" por definição (RbacSeeder). Como o seeder
        //    não roda no deploy, quem já tinha o papel ficava sem as chaves
        //    criadas depois — o papel dizia "tudo" e o banco discordava.
        $admins = DB::table('roles')->where('nome', 'Administrador')->pluck('id');
        if ($admins->isEmpty()) {
            return;
        }

        $todas = DB::table('permissions')->pluck('id');
        $novos = [];
        foreach ($admins as $roleId) {
            // Só o que falta: `permission_role` tem PK composta e reinserir
            // um par existente violaria a chave primária.
            $jaTem = DB::table('permission_role')->where('role_id', $roleId)->pluck('permission_id');
            foreach ($todas->diff($jaTem) as $permissionId) {
                $novos[] = ['role_id' => $roleId, 'permission_id' => $permissionId];
            }
        }

        foreach (array_chunk($novos, 500) as $lote) {
            DB::table('permission_role')->insert($lote);
        }
    }

    public function down(): void
    {
        // Sem volta de propósito: reverter significaria decidir QUAIS chaves
        // apagar e de quem revogar acesso — e um rollback nunca deve ser o que
        // tranca o dono da rede fora do próprio sistema. A migration é aditiva
        // e idempotente; rodá-la de novo é seguro.
    }
};
