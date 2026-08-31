<?php

namespace Tests\Feature;

use App\Domain\Acesso\CamposPermitidos;
use App\Domain\Shared\PermissaoCatalogo;
use App\Domain\Tenant\TenantContext;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\User;
use Database\Factories\Support\FronteiraTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F2-07 — campos sensíveis derivam do CATÁLOGO, não de listas repetidas.
 *
 * O mecanismo field-level estava correto, mas a lista de quais campos são
 * sensíveis vivia em dois lugares: no catálogo de permissões e, de novo, numa
 * constante dentro de cada resource/controller que filtra.
 *
 * A consequência é silenciosa e vale para sempre: declarar
 * `cliente.campo.documento.view` no catálogo NÃO protege nada até alguém
 * lembrar de acrescentar `'documento'` à constante do `ClienteResource`. Uma
 * permissão que existe, aparece na tela de papéis, pode ser negada a um
 * usuário — e não esconde o campo. É pior do que não ter a permissão, porque
 * afirma uma proteção que não acontece.
 *
 * A correção é fazer o catálogo ser a única fonte: o filtro pergunta ao
 * catálogo quais campos daquele módulo são controlados, em vez de receber a
 * lista pronta de quem chama.
 */
class CamposSensiveisCatalogoTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, Empresa} usuário SEM as permissões granulares */
    private function usuarioComum(): array
    {
        $empresa = Empresa::factory()->create();
        // `semPapel`: a factory dá papel de administrador por padrão (F2-08), e
        // aqui o assunto é justamente NÃO ter a permissão granular.
        $user = User::factory()->semPapel()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        FronteiraTenant::papelComPermissoes($user, ['cliente.view', 'produto.view']);
        app(TenantContext::class)->set($empresa->id, $empresa->grupo_id);

        return [$user, $empresa];
    }

    /**
     * O ponto: o filtro descobre os campos pelo catálogo, sem lista repetida.
     */
    public function test_campos_controlados_saem_do_catalogo(): void
    {
        $doCatalogo = app(CamposPermitidos::class)->camposControlados('cliente');

        $this->assertContains('credito_limite', $doCatalogo);
        $this->assertContains('convenio_limite', $doCatalogo);
        $this->assertNotContains('nome', $doCatalogo, 'campo livre não é controlado');
    }

    /** Cada chave `modulo.campo.X.acao` do catálogo tem de virar controle real. */
    public function test_toda_chave_granular_do_catalogo_vira_campo_controlado(): void
    {
        $campos = app(CamposPermitidos::class);

        foreach (array_keys(PermissaoCatalogo::GRANULARES) as $chave) {
            if (! preg_match('/^([a-z_]+)\.campo\.([a-z_]+)\.(view|edit)$/', $chave, $m)) {
                continue;
            }

            $this->assertContains(
                $m[2],
                $campos->camposControlados($m[1]),
                "a chave {$chave} existe no catálogo mas o campo não é controlado",
            );
        }
    }

    /** Sem a permissão granular, o campo não chega ao payload. */
    public function test_campo_sensivel_e_removido_de_quem_nao_tem_a_permissao(): void
    {
        [$user, $empresa] = $this->usuarioComum();
        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        $resposta = $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/clientes/{$cliente->id}")
            ->assertOk();

        $this->assertArrayNotHasKey('credito_limite', $resposta->json('data'));
        $this->assertArrayHasKey('nome', $resposta->json('data'), 'campo livre continua visível');
    }

    /** Com a permissão, o campo aparece — senão o controle seria só uma parede. */
    public function test_campo_sensivel_aparece_para_quem_tem_a_permissao(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->semPapel()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        FronteiraTenant::papelComPermissoes($user, [
            'cliente.view', 'cliente.campo.credito_limite.view',
        ]);
        app(TenantContext::class)->set($empresa->id, $empresa->grupo_id);

        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/clientes/{$cliente->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['credito_limite']]);
    }

    /**
     * A escrita segue a mesma fonte: sem `edit`, o campo é descartado da
     * gravação em vez de recusar a requisição inteira.
     */
    public function test_campo_sem_permissao_de_edicao_e_ignorado_na_gravacao(): void
    {
        [$user, $empresa] = $this->usuarioComum();
        FronteiraTenant::papelComPermissoes($user, ['cliente.view', 'cliente.edit']);

        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'credito_limite' => 100,
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/clientes/{$cliente->id}", [
                'nome' => 'Nome novo', 'credito_limite' => 999999,
            ])
            ->assertOk();

        $cliente->refresh();

        $this->assertSame('Nome novo', $cliente->nome, 'o campo livre foi gravado');
        $this->assertSame(
            '100.00',
            (string) $cliente->credito_limite,
            'o campo sensível foi descartado, não gravado',
        );
    }
}
