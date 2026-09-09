<?php

namespace Tests\Feature;

use App\Domain\Shared\PermissaoCatalogo;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O catálogo de permissões do código chega ao banco pelo caminho que o deploy
 * DE FATO executa.
 *
 * O defeito que este teste tranca: o deploy do erp-novo roda apenas
 * `php artisan migrate` — o `RbacSeeder`, que populava `permissions`, nunca é
 * executado em produção. Toda chave nova ficava só no código, o Gate negava
 * para todo mundo e a funcionalidade nascia inacessível até para o dono da
 * rede. Três chaves estavam nessa situação quando isto foi escrito
 * (`cliente.export.completo` e as duas de `produto.campo.custo`), e ninguém
 * percebeu porque nenhum teste comparava o catálogo com o banco.
 */
class CatalogoPermissoesSincronizadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_toda_chave_do_catalogo_existe_no_banco_apos_as_migrations(): void
    {
        $catalogo = array_keys(PermissaoCatalogo::comDescricoes());
        $banco = Permission::pluck('chave')->all();

        // Guardião do próprio teste: se o catálogo viesse vazio, o assertEmpty
        // abaixo passaria protegendo coisa nenhuma.
        $this->assertGreaterThan(100, count($catalogo), 'O catálogo deveria ter mais de 100 chaves.');

        $faltando = array_diff($catalogo, $banco);

        $this->assertEmpty(
            $faltando,
            'Chaves no código que não chegaram ao banco: '.implode(', ', $faltando)
            .'. O deploy só roda `migrate` — chave nova precisa entrar por migration, não pelo RbacSeeder.',
        );
    }

    public function test_a_migration_de_sincronia_e_idempotente(): void
    {
        // Rodar de novo não pode duplicar nem explodir: é exatamente o que
        // acontece a cada deploy num banco que já tem as chaves.
        $antes = Permission::count();

        $migration = require database_path('migrations/2026_09_09_000100_sincronizar_catalogo_de_permissoes.php');
        $migration->up();
        $migration->up();

        $this->assertSame($antes, Permission::count(), 'A migration duplicou permissões ao rodar duas vezes.');
        $this->assertSame(
            Permission::count(),
            Permission::distinct('chave')->count('chave'),
            'Há chave repetida na tabela.',
        );
    }
}
