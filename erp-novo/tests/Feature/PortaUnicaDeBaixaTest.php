<?php

namespace Tests\Feature;

use App\Domain\Caixa\CaixaService;
use App\Domain\Financeiro\BaixaService;
use App\Domain\Tenant\TenantContext;
use App\Models\Empresa;
use App\Models\Financeiro\Financeiro;
use App\Models\Financeiro\FinanceiroParcela;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * F5-02 — porta única para liquidar **e estornar**.
 *
 * ## O buraco que a medição encontrou
 *
 * A baixa já entrava só por `BaixaService::baixar`: verifica a empresa, trava a
 * linha com `lockForUpdate`, e é idempotente quando a origem é externa.
 *
 * O **estorno não passava por ali**. `CaixaService::estornar` reabria a parcela
 * escrevendo `baixado => false` direto no model — e nessa escrita faltava
 * justamente a verificação de empresa que a baixa faz.
 *
 * A única proteção era o global scope de tenant. Ele existe e funciona numa
 * requisição HTTP autenticada, mas **não vale em job, comando de console nem
 * contexto de suporte** — que é exatamente onde um estorno em lote roda.
 *
 * ## Por que uma porta e não duas boas
 *
 * Mesmo que a segunda escrita fosse igualmente cuidadosa, qualquer regra nova
 * sobre reabrir uma parcela — registrar quem estornou, recusar estorno de
 * parcela já agrupada, avisar a cobrança — teria de ser escrita nos dois
 * lugares. E a segunda seria esquecida: foi assim que o `motivo` da trilha
 * nasceu vazio no ramo que absorve o `atualizado`.
 */
class PortaUnicaDeBaixaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Empresa, FinanceiroParcela, int} empresa, parcela aberta, conta */
    private function cenario(): array
    {
        $empresa = Empresa::factory()->create();
        app(TenantContext::class)->set($empresa->id, $empresa->grupo_id);

        $conta = app(CaixaService::class)->criarConta([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Caixa', 'saldo_inicial' => 0,
        ]);

        $titulo = Financeiro::create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'pagarreceber' => 'R', 'descricao' => 'Venda', 'valor' => 200.00,
            'data_emissao' => now(), 'cancelado' => false,
        ]);
        $parcela = FinanceiroParcela::create([
            'financeiro_id' => $titulo->id, 'numero' => 1,
            'vencimento' => now()->toDateString(), 'valor' => 200.00,
            'baixado' => false, 'valor_efetivado' => 0,
        ]);

        return [$empresa, $parcela, $conta->id];
    }

    /** O caminho de ida e volta, pela mesma porta. */
    public function test_baixa_e_reabertura_fecham_o_ciclo(): void
    {
        [$empresa, $parcela] = $this->cenario();
        $baixas = app(BaixaService::class);

        $this->assertTrue($baixas->baixar($parcela->id, $empresa->id, 200.00, 'teste'));
        $this->assertTrue($parcela->refresh()->baixado);

        $this->assertTrue($baixas->reabrir($parcela->id, $empresa->id, 'teste'));

        $parcela->refresh();
        $this->assertFalse($parcela->baixado);
        $this->assertSame('0.00', (string) $parcela->valor_efetivado);
        $this->assertNull($parcela->datahora_baixa);
    }

    /**
     * O defeito corrigido: reabrir parcela de OUTRA empresa tem de ser recusado
     * pelo serviço, não depender do scope estar ativo.
     */
    public function test_reabertura_recusa_parcela_de_outra_empresa(): void
    {
        [$empresa, $parcela] = $this->cenario();
        app(BaixaService::class)->baixar($parcela->id, $empresa->id, 200.00, 'teste');

        $intrusa = Empresa::factory()->create();

        $this->expectException(ValidationException::class);
        app(BaixaService::class)->reabrir($parcela->id, $intrusa->id, 'tentativa indevida');
    }

    /**
     * A fronteira vale **sem o global scope ativo** — que é a condição real de
     * um job ou comando de console, onde não há usuário nem tenant resolvido.
     *
     * Este é o teste que dá sentido à correção: com o scope ligado, a escrita
     * antiga também teria falhado, e o defeito ficaria invisível.
     */
    public function test_fronteira_vale_sem_tenant_resolvido(): void
    {
        [$empresa, $parcela] = $this->cenario();
        app(BaixaService::class)->baixar($parcela->id, $empresa->id, 200.00, 'teste');

        $intrusa = Empresa::factory()->create();

        // Simula o job: nenhum tenant no contexto.
        app(TenantContext::class)->clear();

        $this->expectException(ValidationException::class);
        app(BaixaService::class)->reabrir($parcela->id, $intrusa->id, 'job sem tenant');
    }

    /** Reabrir o que já está aberto não é erro — é reentrega. */
    public function test_reabrir_parcela_ja_aberta_e_idempotente(): void
    {
        [$empresa, $parcela] = $this->cenario();

        $this->assertFalse(
            app(BaixaService::class)->reabrir($parcela->id, $empresa->id, 'reentrega'),
            'reprocessar um estorno não pode explodir: a parcela já está no estado desejado',
        );
        $this->assertFalse($parcela->refresh()->baixado);
    }

    /**
     * O estorno de caixa passou a usar a porta — e continua reabrindo a parcela.
     *
     * Sem esta asserção a correção poderia ter quebrado o comportamento: trocar
     * a escrita direta por uma chamada que não reabre nada deixaria o dinheiro
     * estornado no caixa e o título fechado no financeiro.
     */
    public function test_estorno_de_caixa_reabre_a_parcela_pela_porta(): void
    {
        [$empresa, $parcela, $contaId] = $this->cenario();
        $caixa = app(CaixaService::class);

        $movimento = $caixa->baixarParcela($contaId, $parcela->id, $empresa->id);
        $this->assertTrue($parcela->refresh()->baixado);

        $caixa->estornar($movimento->id, $empresa->id);

        $parcela->refresh();
        $this->assertFalse($parcela->baixado, 'o estorno reabre o título');
        $this->assertNull($parcela->datahora_baixa);
    }

    /**
     * Guardião: nenhuma escrita nova de `baixado` fora da porta.
     *
     * A correção fecha o buraco de hoje; este teste é o que impede o próximo. E
     * a reincidência é provável — `->update(['baixado' => ...])` é a forma óbvia
     * de escrever, e o defeito não aparece em teste nenhum enquanto o scope
     * estiver ativo.
     */
    public function test_apenas_a_porta_unica_escreve_o_estado_de_baixa(): void
    {
        $achados = [];
        $varridos = 0;

        $arquivos = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($arquivos as $arquivo) {
            if ($arquivo->getExtension() !== 'php') {
                continue;
            }

            $caminho = str_replace('\\', '/', $arquivo->getPathname());

            // A própria porta escreve, obviamente.
            if (str_ends_with($caminho, 'Domain/Financeiro/BaixaService.php')) {
                continue;
            }

            // O ETL importa o estado HISTÓRICO do legado. Ali não há transição
            // de aberta para baixada — há uma parcela que já nasce baixada,
            // porque foi baixada anos atrás noutro sistema. Passar pela porta
            // exigiria baixar cada uma de novo, gerando trilha de uma operação
            // que nunca aconteceu.
            if (str_contains($caminho, '/Etl/')) {
                continue;
            }

            $varridos++;
            $conteudo = (string) file_get_contents($arquivo->getPathname());

            foreach (explode("\n", $conteudo) as $n => $linha) {
                // Só ESCRITA DE ESTADO com valor literal. `'baixado' => ...`
                // aparece também como rótulo de catálogo, cast de model e campo
                // de resposta JSON — acusar esses transformaria o guardião em
                // ruído, e guardião ruidoso é desligado no primeiro incômodo.
                if (preg_match("/'baixado'\s*=>\s*(true|false|[01])\b/", $linha)) {
                    $achados[] = basename($caminho).':'.($n + 1);
                }
            }
        }

        $this->assertGreaterThan(200, $varridos, 'a varredura precisa ter varrido algo');
        $this->assertSame([], $achados, 'baixa e reabertura passam por BaixaService');
    }
}
