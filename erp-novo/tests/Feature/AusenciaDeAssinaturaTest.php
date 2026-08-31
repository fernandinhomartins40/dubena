<?php

namespace Tests\Feature;

use App\Domain\Saas\LicencaService;
use App\Domain\Saas\RecursoCatalogo;
use App\Http\Middleware\RecursoPorRota;
use App\Models\Empresa;
use App\Models\User;
use Database\Factories\Support\FronteiraTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * F2-08 — ausência de assinatura nega nas PORTAS, não só no serviço.
 *
 * `LicencaService` já era fail-closed e havia teste provando isso. Mas um
 * serviço que decide certo e uma porta que não pergunta a ele produzem
 * exatamente o mesmo resultado que não ter licença nenhuma — foi assim que o
 * `recurso:` ficou em 0 de 604 rotas até F2-03.
 *
 * Este arquivo mede a outra ponta: a requisição HTTP de uma empresa sem contrato
 * é recusada com 402, e o usuário permanece autenticado e autorizado — o que
 * falta é entitlement, não identidade nem permissão. Confundir os três é como se
 * perde a capacidade de dizer ao cliente por que ele foi barrado.
 */
class AusenciaDeAssinaturaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, Empresa} usuário pleno numa empresa SEM assinatura */
    private function semContrato(): array
    {
        config()->set('saas_transformation.enforcement.licenca', true);

        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        FronteiraTenant::papelAdministrador($user, $empresa->id);

        // A fixture assina toda empresa da fronteira (F2-04); aqui o assunto é
        // justamente a ausência de contrato.
        FronteiraTenant::semLicenca($empresa);

        return [$user->fresh(), $empresa];
    }

    /** Módulo opcional: sem contrato, 402 — e não 403 nem 401. */
    public function test_modulo_opcional_responde_402_sem_assinatura(): void
    {
        [$user] = $this->semContrato();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/monitora/veiculos')
            ->assertStatus(402);
    }

    /**
     * 402 e não 403: a distinção é o que permite a tela dizer "seu plano não
     * inclui isto" em vez de "você não tem permissão". A segunda mensagem manda
     * o cliente pedir acesso a alguém que não pode concedê-lo.
     */
    public function test_o_status_distingue_entitlement_de_permissao(): void
    {
        [$user] = $this->semContrato();

        $resposta = $this->actingAs($user, 'sanctum')->getJson('/api/admin/monitora/veiculos');

        $resposta->assertStatus(402);
        $this->assertNotSame(403, $resposta->status(), 'não é falta de permissão');
        $this->assertNotSame(401, $resposta->status(), 'não é falta de autenticação');
    }

    /**
     * O núcleo do ERP continua de pé. Uma revenda sem contrato vigente não pode
     * ficar sem acesso ao próprio cadastro — isso transformaria uma pendência
     * comercial em perda de dados operacionais.
     */
    public function test_nucleo_do_erp_continua_respondendo(): void
    {
        [$user] = $this->semContrato();

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/clientes')->assertOk();
    }

    /** Sem contrato, nenhum recurso do catálogo é liberado — nem por engano. */
    public function test_nenhum_recurso_do_catalogo_fica_habilitado(): void
    {
        [, $empresa] = $this->semContrato();
        $licenca = app(LicencaService::class);

        foreach (RecursoCatalogo::chaves() as $chave) {
            $this->assertFalse(
                $licenca->recursoHabilitado($chave, $empresa->id),
                "o recurso {$chave} ficou habilitado sem assinatura",
            );
        }
    }

    /**
     * A varredura: TODA rota de módulo opcional nega sem assinatura.
     *
     * A pergunta é feita ao próprio `RecursoPorRota` — ele resolve o recurso por
     * PREFIXO de caminho, e não por parâmetro declarado na rota, então procurar
     * um middleware `recurso:` nas rotas não acharia nada. Perguntar a ele é o
     * que faz este teste acompanhar o mapa: prefixo novo lá passa a ser exigido
     * aqui, sem ninguém editar este arquivo.
     */
    public function test_toda_rota_de_modulo_opcional_nega_sem_assinatura(): void
    {
        [$user] = $this->semContrato();

        $liberadas = [];
        $testadas = 0;

        foreach (Route::getRoutes() as $rota) {
            $uri = $rota->uri();

            // Só GET sem parâmetro: o que se mede é a barreira da licença, e
            // montar corpo ou id para centenas de rotas mediria outra coisa.
            if (str_contains($uri, '{') || ! in_array('GET', $rota->methods(), true)) {
                continue;
            }

            if (RecursoPorRota::recursoDaRota($uri) === null) {
                continue;
            }

            $testadas++;
            $status = $this->actingAs($user, 'sanctum')->getJson('/'.$uri)->status();

            if ($status !== 402) {
                $liberadas[] = "{$uri} devolveu {$status} em vez de 402";
            }
        }

        $this->assertGreaterThan(0, $testadas, 'nenhuma rota de módulo opcional foi encontrada — o mapa sumiu?');
        $this->assertSame([], $liberadas, "Rotas passando sem assinatura:\n".implode("\n", $liberadas));
    }

    /** Assinar destrava — senão o teste acima passaria com tudo fora do ar. */
    public function test_assinar_destrava_o_modulo(): void
    {
        [$user, $empresa] = $this->semContrato();

        FronteiraTenant::licencaDeTransicao($empresa);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/monitora/veiculos')
            ->assertOk();
    }
}
