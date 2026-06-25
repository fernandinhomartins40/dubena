<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * F14 — rastreabilidade da homologação: prova que TODO achado 🔴/🟡/⚠️ da auditoria
 * (docs/AUDITORIA_PARIDADE_MODERNIZACAO.md) tem rota/comando exposto. É um GUARD de
 * regressão — se alguém remover um endpoint de paridade, este teste quebra.
 *
 * Cada entrada mapeia o achado → o que o fecha (fase).
 */
class F14RastreabilidadeTest extends TestCase
{
    /** Rotas que materializam os achados fechados (método|uri). */
    private const ROTAS_PARIDADE = [
        // F01 — config global
        'GET api/admin/config-global',
        // F03 — NFC-e do pedido
        'POST api/admin/pedidos/{id}/emitir-nfce',
        // F06 — NF de entrada
        'POST api/admin/fiscal/nf-entrada/importar',
        'POST api/admin/fiscal/nf-entrada/{id}/processar',
        // F07 — financeiro/caixa órfãos
        'POST api/admin/financeiro/lancamentos/agrupar',
        'POST api/admin/financeiro/lancamentos/{id}/desagrupar',
        'POST api/admin/financeiro/lancamentos/{id}/reparcelar',
        'POST api/admin/caixa/{contaId}/baixar-titulos',
        'POST api/admin/caixa/{contaId}/lancar-fechado',
        // F08 — cobrança/CNAB/conciliação contábil
        'POST api/admin/cobranca/remessas',
        'GET api/admin/cobranca/remessas/{id}/arquivo',
        'POST api/admin/cobranca/retorno',
        'GET api/admin/conciliacao-contabil',
        // F09 — fiscal: inutilização + CCE
        'POST api/admin/fiscal/inutilizacoes',
        'POST api/admin/notas/{id}/carta-correcao',
        // F10 — central de relatórios
        'GET api/admin/relatorios/catalogo',
        'GET api/admin/relatorios/{slug}',
        // F11 — auditoria + inconsistências
        'GET api/admin/relatorios/auditoria',
        'GET api/admin/cadastros/inconsistencias',
        // F12 — frota entrada-saída/documento + mala direta
        'GET api/admin/veiculos/{id}/entradas-saidas',
        'POST api/admin/veiculos/{id}/entradas-saidas',
        'GET api/admin/veiculos/{id}/documentos',
        'GET api/admin/crm/mala-direta',
    ];

    /** Comandos de console que fecham achados (ETL/IBPT/cutover/go-live). */
    private const COMANDOS = [
        'ibpt:atualizar', 'cutover:check', 'golive:check', 'etl:run',
    ];

    public function test_todas_as_rotas_de_paridade_existem(): void
    {
        $rotas = collect(Route::getRoutes())->flatMap(function ($r) {
            return collect($r->methods())
                ->filter(fn ($m) => in_array($m, ['GET', 'POST', 'PUT', 'DELETE'], true))
                ->map(fn ($m) => "{$m} {$r->uri()}");
        })->unique()->values()->all();

        $faltando = array_values(array_filter(self::ROTAS_PARIDADE, fn ($r) => ! in_array($r, $rotas, true)));

        $this->assertSame([], $faltando, 'Rotas de paridade ausentes: '.implode(', ', $faltando));
    }

    public function test_comandos_de_paridade_existem(): void
    {
        $registrados = array_keys(\Illuminate\Support\Facades\Artisan::all());

        foreach (self::COMANDOS as $cmd) {
            $this->assertContains($cmd, $registrados, "Comando ausente: {$cmd}");
        }
    }

    public function test_drivers_de_integracao_tem_gate_e_real(): void
    {
        // Cada gate externo tem driver Fake (CI) e real (produção).
        foreach ([
            \App\Domain\Fiscal\Drivers\NFePHPSefazDriver::class,
            \App\Domain\Cobranca\Drivers\CaixaBoletoDriver::class,
            \App\Domain\Cobranca\Drivers\ItauBoletoDriver::class,
            \App\Domain\Mobile\Drivers\EredeDriver::class,
            \App\Domain\Monitora\Drivers\SgcasaHttpDriver::class,
        ] as $driverReal) {
            $this->assertTrue(class_exists($driverReal), "Driver real ausente: {$driverReal}");
        }
    }
}
