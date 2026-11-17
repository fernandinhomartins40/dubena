<?php

namespace Tests\Feature;

use App\Http\Middleware\RecursoPorRota;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PIE P2-A-04 — o menu não promete o que a licença nega.
 *
 * O menu da SPA (`layouts/AppShell.tsx`) esconde item por `feature`; o backend
 * barra por prefixo (`RecursoPorRota::MAPA`). Eram duas listas escritas à mão:
 * Pós-venda, Metas, Checklists, Notas fiscais e Cupons apareciam no menu e o
 * clique devolvia 402. Este teste amarra as duas pela API que cada tela chama
 * primeiro: o recurso que o backend exige tem de ser o `feature` do menu.
 *
 * Item novo no menu sem linha aqui reprova — é o esquecimento que se quer pegar.
 */
class MenuLicencaContratoTest extends TestCase
{
    /** Destino do menu => endpoint que a tela consulta ao abrir (sem `api/admin/`). */
    private const API_DA_TELA = [
        '/' => 'dashboard',
        '/alertas' => 'alertas',
        '/relatorios' => 'relatorios/vendas',
        '/pedidos' => 'pedidos',
        '/clientes' => 'clientes',
        '/clientes/revisoes' => 'clientes/revisoes',
        '/central-vendas' => 'central-vendas/solicitacoes',
        '/alcadas' => 'alcadas-desconto',
        '/central' => 'central/fila',
        '/comodatos' => 'comodatos',
        '/missoes' => 'missoes',
        '/monitora' => 'monitora/veiculos',
        '/geografico' => 'geo/cidades',
        '/produtos' => 'produtos',
        '/estoque' => 'estoque/saldos',
        '/mcmm' => 'mcmm',
        '/bens' => 'bens',
        '/financeiro' => 'financeiro/titulos',
        '/cartoes' => 'cartoes',
        '/vale-gas' => 'vale-gas',
        '/gas-do-povo' => 'gas-do-povo',
        '/convenios' => 'convenios',
        '/fiscal' => 'fiscal/nfe',
        '/cupons-fiscais' => 'cupons-fiscais',
        '/pos-venda' => 'pos-vendas',
        '/promocoes' => 'promocoes',
        '/sorteios' => 'sorteios',
        '/metas' => 'metas',
        '/colaboradores' => 'colaboradores',
        '/veiculos' => 'veiculos',
        '/checklists' => 'checklists',
        '/documentos' => 'documentos',
        '/empresas' => 'empresas',
        '/acessos' => 'usuarios',
        '/auditoria' => 'auditoria',
        '/configuracoes' => 'config-global',
    ];

    /** @return array<string, array{to:string, feature:?string}> */
    private static function menu(): array
    {
        $fonte = file_get_contents(base_path('frontend/src/layouts/AppShell.tsx'));
        preg_match_all("/\\{ label: '[^']+', to: '([^']+)'[^\\n]*\\}/", $fonte, $linhas, PREG_SET_ORDER);

        $itens = [];
        foreach ($linhas as [$linha, $to]) {
            $feature = preg_match("/feature: '([^']+)'/", $linha, $m) ? $m[1] : null;
            $itens[$to] = ['to' => $to, 'feature' => $feature];
        }

        return $itens;
    }

    public function test_todo_item_do_menu_tem_api_declarada(): void
    {
        $menu = self::menu();
        // Piso: se o regex parar de casar, o teste não pode passar vazio.
        $this->assertGreaterThan(30, count($menu));
        $this->assertSame([], array_values(array_diff(array_keys($menu), array_keys(self::API_DA_TELA))),
            'Item de menu sem API declarada em MenuLicencaContratoTest::API_DA_TELA.');
    }

    /** @return iterable<string, array{0:string}> */
    public static function itens(): iterable
    {
        foreach (array_keys(self::API_DA_TELA) as $to) {
            yield $to => [$to];
        }
    }

    #[DataProvider('itens')]
    public function test_feature_do_menu_e_o_recurso_que_o_backend_exige(string $to): void
    {
        $menu = self::menu();
        $this->assertArrayHasKey($to, $menu, "{$to} saiu do menu: tire a linha do teste.");

        $exigido = RecursoPorRota::recursoDaRota('api/admin/'.self::API_DA_TELA[$to]);
        $this->assertSame($exigido, $menu[$to]['feature'],
            "Menu {$to}: o backend exige '".($exigido ?? 'nada')."', o menu esconde por '".($menu[$to]['feature'] ?? 'nada')."'.");
    }
}
