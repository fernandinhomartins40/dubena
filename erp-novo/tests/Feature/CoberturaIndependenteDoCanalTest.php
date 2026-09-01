<?php

namespace Tests\Feature;

use App\Domain\Mobile\MarketplaceService;
use App\Domain\Mobile\PedidoMobileService;
use App\Domain\Pedido\EfeitoPedido;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * F6-05 — cobertura geográfica é independente da flag de canal.
 *
 * ## O defeito
 *
 * `validarCobertura` só rodava quando `app_marketplace_ativo` era verdadeiro:
 *
 * ```php
 * $marketplaceAtivo = (bool) Empresa::query()->whereKey($empresaId)->value('app_marketplace_ativo');
 * if (! $marketplaceAtivo) {
 *     return; // sai sem validar nada
 * }
 * ```
 *
 * Ou seja: a flag de **canal** governava a cobertura **geográfica**. Uma revenda
 * que não aderiu ao marketplace aceitava pedido do app de **qualquer endereço**,
 * inclusive de outra cidade — e a cerca que ela mesma desenhou era ignorada.
 *
 * São perguntas diferentes:
 *
 *  - **canal**: apareço na descoberta pública do app? → `app_marketplace_ativo`;
 *  - **cobertura**: entrego neste endereço? → cerca ou raio configurados.
 *
 * ## Por que não bastou remover o `if`
 *
 * `empresaAtendePonto` devolve `false` tanto para "está fora da minha área"
 * quanto para "não configurei área nenhuma" — e a maioria das revendas ainda não
 * desenhou cerca. Validar sem distinguir os dois derrubaria a operação atual
 * inteira, o que é pior que o defeito que se conserta.
 *
 * Daí a segunda pergunta: **quem declarou área, respeita a área; quem não
 * declarou não é restringido**. É a configuração que decide, não a flag.
 */
class CoberturaIndependenteDoCanalTest extends TestCase
{
    use RefreshDatabase;

    /** Empresa com raio de entrega declarado, na posição da matriz. */
    private function revendaComRaio(bool $marketplace, float $raioKm = 5.0): Empresa
    {
        return Empresa::factory()->create([
            'app_marketplace_ativo' => $marketplace,
            'latitude' => -25.3935,
            'longitude' => -51.4562,
            'raio_entrega_km' => $raioKm,
            'ativo' => true,
        ]);
    }

    /** Ponto a ~40 km da matriz — fora de qualquer raio urbano. */
    private const LONGE_LAT = -25.7500;

    private const LONGE_LNG = -51.4562;

    /**
     * O caso do defeito: revenda FORA do marketplace, com área declarada,
     * recebendo pedido de endereço fora dela.
     */
    public function test_revenda_fora_do_marketplace_respeita_a_propria_area(): void
    {
        $empresa = $this->revendaComRaio(marketplace: false);

        $this->assertFalse(
            app(MarketplaceService::class)
                ->empresaAtendePonto($empresa->id, self::LONGE_LAT, self::LONGE_LNG),
            'o ponto está fora do raio declarado',
        );
        $this->assertTrue(
            app(MarketplaceService::class)->empresaTemCoberturaDeclarada($empresa->id),
            'e a revenda declarou área — então a cobertura vale, marketplace ou não',
        );
    }

    /** Dentro da área, atende — com ou sem marketplace. */
    public function test_ponto_dentro_da_area_e_atendido_nos_dois_canais(): void
    {
        $servico = app(MarketplaceService::class);

        $comCanal = $this->revendaComRaio(marketplace: true);
        $semCanal = $this->revendaComRaio(marketplace: false);

        // ~1 km da matriz.
        foreach ([$comCanal, $semCanal] as $empresa) {
            $this->assertTrue(
                $servico->empresaAtendePonto($empresa->id, -25.4000, -51.4562),
                'a cobertura não muda porque a revenda aparece (ou não) na descoberta',
            );
        }
    }

    /**
     * Quem NÃO declarou área não é bloqueado.
     *
     * É a parte que impede a correção de derrubar a operação atual: a maioria
     * das revendas ainda não desenhou cerca nem configurou raio.
     */
    public function test_revenda_sem_area_declarada_nao_tem_cobertura_a_respeitar(): void
    {
        $empresa = Empresa::factory()->create([
            'app_marketplace_ativo' => false,
            'latitude' => null, 'longitude' => null, 'raio_entrega_km' => null,
        ]);

        $this->assertFalse(
            app(MarketplaceService::class)->empresaTemCoberturaDeclarada($empresa->id),
            'sem cerca e sem raio não há área a respeitar',
        );
    }

    /** Raio sem coordenada da matriz não é área declarada — não dá para medir de onde. */
    public function test_raio_sem_coordenada_nao_conta_como_area_declarada(): void
    {
        $empresa = Empresa::factory()->create([
            'raio_entrega_km' => 10.0,
            'latitude' => null, 'longitude' => null,
        ]);

        $this->assertFalse(
            app(MarketplaceService::class)->empresaTemCoberturaDeclarada($empresa->id),
        );
    }

    // ── O fluxo completo: criar pedido pelo app ───────────────────────────

    /** @return array{Empresa, Cliente, Produto} */
    private function cenarioDePedido(bool $marketplace): array
    {
        $empresa = $this->revendaComRaio($marketplace);

        Setor::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)
            ->create(['grupo_id' => $empresa->grupo_id, 'ativo' => true]);

        $produto = Produto::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'preco_venda' => 100,
        ]);
        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'latitude' => self::LONGE_LAT, 'longitude' => self::LONGE_LNG,
        ]);

        return [$empresa, $cliente, $produto];
    }

    /**
     * O pedido de endereço fora da área é recusado mesmo sem marketplace.
     *
     * Este é o teste que prova a correção de ponta a ponta: antes, a mesma
     * chamada passava — e a revenda recebia um pedido para 40 km de distância.
     */
    public function test_pedido_fora_da_area_e_recusado_sem_marketplace(): void
    {
        [$empresa, $cliente, $produto] = $this->cenarioDePedido(marketplace: false);

        $this->expectException(ValidationException::class);

        app(PedidoMobileService::class)->criarDoApp($empresa->id, $empresa->grupo_id, [
            'cliente_id' => $cliente->id,
            'itens' => [['produto_id' => $produto->id, 'quantidade' => 1]],
        ]);
    }

    /** E continua recusado com marketplace — o comportamento que já existia. */
    public function test_pedido_fora_da_area_continua_recusado_com_marketplace(): void
    {
        [$empresa, $cliente, $produto] = $this->cenarioDePedido(marketplace: true);

        $this->expectException(ValidationException::class);

        app(PedidoMobileService::class)->criarDoApp($empresa->id, $empresa->grupo_id, [
            'cliente_id' => $cliente->id,
            'itens' => [['produto_id' => $produto->id, 'quantidade' => 1]],
        ]);
    }

    /**
     * Guardião: a flag de canal não volta a decidir cobertura.
     *
     * O sinal é `app_marketplace_ativo` lido dentro do serviço de pedido — que é
     * exatamente onde estava, e onde a tentação de recolocá-lo mora ("é só
     * validar quando for marketplace").
     */
    public function test_a_flag_de_canal_nao_decide_cobertura(): void
    {
        $fonte = (string) file_get_contents(app_path('Domain/Mobile/PedidoMobileService.php'));

        $linhas = explode("\n", $fonte);
        $achados = [];

        foreach ($linhas as $n => $linha) {
            $semEspaco = ltrim($linha);

            // Comentários explicando a decisão são o que se quer preservar.
            if (str_starts_with($semEspaco, '*') || str_starts_with($semEspaco, '//')) {
                continue;
            }

            if (str_contains($linha, 'app_marketplace_ativo')) {
                $achados[] = 'PedidoMobileService.php:'.($n + 1);
            }
        }

        $this->assertGreaterThan(100, count($linhas), 'o arquivo precisa ter sido lido');
        $this->assertSame([], $achados, 'cobertura é decidida pela ÁREA declarada, não pelo canal');
    }
}
