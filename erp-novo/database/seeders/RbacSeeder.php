<?php

namespace Database\Seeders;

use App\Domain\Shared\PermissaoCatalogo;
use App\Models\Empresa;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * RBAC inicial (C1) — idempotente.
 *
 * 1. Popula `permissions` a partir do catálogo único (fonte da verdade).
 * 2. Cria papéis-base por grupo: Administrador (tudo), Gerente (tudo menos
 *    exclusões e config de empresa/grupo), Operador (operação do dia a dia),
 *    Entregador (apenas pedidos/monitora — espelha o app).
 * 3. NÃO mexe no usuário `support` (continua com bypass total).
 *
 * Roda após o DeployAdminSeeder (que cria grupo/empresa/admin).
 */
class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $this->sincronizarPermissions();

        // Cria os papéis-base para cada grupo existente (rede).
        foreach (Empresa::query()->distinct()->pluck('grupo_id')->filter() as $grupoId) {
            $this->papeisDoGrupo((int) $grupoId);
        }
    }

    /** Garante que TODA chave do catálogo existe na tabela. */
    private function sincronizarPermissions(): void
    {
        foreach (PermissaoCatalogo::comDescricoes() as $chave => $descricao) {
            Permission::query()->updateOrCreate(['chave' => $chave], ['descricao' => $descricao]);
        }
    }

    private function papeisDoGrupo(int $grupoId): void
    {
        $todas = Permission::query()->pluck('id', 'chave'); // chave => id

        // Administrador: tudo.
        $this->role($grupoId, 'Administrador', $todas->values()->all());

        // Gerente: tudo, exceto exclusões, administração de empresa/grupo e a
        // Central de Acessos (usuarios/papeis) — administração de acesso é privilégio
        // do Administrador (default-deny + menor privilégio).
        $gerente = $todas->reject(function ($id, string $chave) {
            return str_ends_with($chave, '.delete')
                || str_starts_with($chave, 'empresa.')
                || str_starts_with($chave, 'grupo.')
                || str_starts_with($chave, 'usuario.')
                || str_starts_with($chave, 'papel.')
                || str_starts_with($chave, 'unidade.')
                || str_starts_with($chave, 'departamento.')
                || str_starts_with($chave, 'setor.');
        });
        $this->role($grupoId, 'Gerente', $gerente->values()->all());

        // Operador: operação do dia a dia (sem fiscal/empresa/grupo/relatórios sensíveis).
        $operador = $todas->filter(function ($id, string $chave) {
            return preg_match('/^(cliente|produto|estoque|pedido|financeiro|caixa|convenio|valegas|comodato)\.(view|create|edit)$/', $chave) === 1;
        });
        $this->role($grupoId, 'Operador', $operador->values()->all());

        // Entregador: espelha o app (pedido + monitora, somente leitura/edição de status).
        $entregador = $todas->filter(function ($id, string $chave) {
            return in_array($chave, ['pedido.view', 'pedido.edit', 'monitora.view'], true);
        });
        $this->role($grupoId, 'Entregador', $entregador->values()->all());
    }

    /**
     * Cria/atualiza um papel do grupo e sincroniza suas permissões.
     *
     * @param  list<int>  $permissionIds
     */
    private function role(int $grupoId, string $nome, array $permissionIds): void
    {
        $role = Role::query()->updateOrCreate(
            ['grupo_id' => $grupoId, 'nome' => $nome],
            ['descricao' => "Papel {$nome} (gerado pelo RbacSeeder)"],
        );

        $role->permissions()->sync($permissionIds);
    }
}
