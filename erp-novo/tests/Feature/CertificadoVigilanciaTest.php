<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\EmpresaConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F5-06 — o certificado A1 passa a ser vigiado, não só consultável.
 *
 * ## O que a medição mostrou
 *
 * A parte difícil já estava feita e bem feita: certificado por empresa, `.pfx`
 * em storage privado, senha cifrada, validade lida do próprio arquivo, ambiente
 * (produção/homologação) explícito na config.
 *
 * O que faltava era o verbo da tarefa — **monitorado**. `status()` responde
 * quando alguém abre a tela fiscal, e ninguém abre a tela fiscal para conferir
 * uma data que só importa uma vez por ano.
 *
 * Um A1 vale 12 meses. Quando vence, a emissão para, e a revenda descobre pela
 * primeira nota recusada — com o cliente na porta e a mercadoria no caminhão.
 * Renovar leva dias.
 *
 * Num SaaS o silêncio custa mais: com N revendas, alguma está sempre a poucas
 * semanas de vencer, e nenhuma vai lembrar sozinha.
 */
class CertificadoVigilanciaTest extends TestCase
{
    use RefreshDatabase;

    private function empresaComCertificado(?string $validade, string $nome = 'Revenda'): Empresa
    {
        $empresa = Empresa::factory()->create(['nome_fantasia' => $nome]);

        EmpresaConfig::query()->updateOrCreate(
            ['empresa_id' => $empresa->id],
            [
                'cert_path' => 'certificados/'.$empresa->id.'.pfx',
                'cert_validade' => $validade,
                'cert_titular' => $nome,
            ],
        );

        return $empresa;
    }

    /** Vencido é falha: serve de portão em deploy e de alarme no cron. */
    public function test_certificado_vencido_reprova(): void
    {
        $this->empresaComCertificado(now()->subDays(3)->toDateTimeString(), 'Gas Vencido');

        $this->artisan('fiscal:certificado-vigilancia')
            ->expectsOutputToContain('VENCIDO')
            ->assertFailed();
    }

    /**
     * Vencendo AVISA mas não reprova.
     *
     * A distinção importa: reprovar um certificado que ainda vale 20 dias
     * travaria o deploy de quem está operando normalmente, e o alarme seria
     * desligado na mesma semana — que é como um alarme morre.
     */
    public function test_certificado_vencendo_avisa_sem_reprovar(): void
    {
        $this->empresaComCertificado(now()->addDays(20)->toDateTimeString(), 'Gas Quase');

        $this->artisan('fiscal:certificado-vigilancia')
            ->expectsOutputToContain('vence em')
            ->assertSuccessful();
    }

    /** Certificado com folga não gera ruído. */
    public function test_certificado_com_folga_nao_gera_ruido(): void
    {
        $this->empresaComCertificado(now()->addMonths(8)->toDateTimeString(), 'Gas Tranquilo');

        $this->artisan('fiscal:certificado-vigilancia')
            ->expectsOutputToContain('Nenhum certificado vencido')
            ->assertSuccessful();
    }

    /** A régua é configurável: quem quer 90 dias de antecedência pode pedir. */
    public function test_a_regua_de_antecedencia_e_ajustavel(): void
    {
        $this->empresaComCertificado(now()->addDays(60)->toDateTimeString(), 'Gas Medio');

        $this->artisan('fiscal:certificado-vigilancia')
            ->expectsOutputToContain('Nenhum certificado vencido')
            ->assertSuccessful();

        $this->artisan('fiscal:certificado-vigilancia --dias=90')
            ->expectsOutputToContain('vence em')
            ->assertSuccessful();
    }

    /**
     * Arquivo sem validade lida é achado, não silêncio.
     *
     * Significa que o upload não passou pela leitura do `.pfx`, ou que a coluna
     * foi preenchida por fora. Não dá para afirmar que está bom nem que está
     * ruim — e no fiscal a dúvida merece ser dita.
     */
    public function test_certificado_sem_validade_lida_e_reportado(): void
    {
        $this->empresaComCertificado(null, 'Gas Sem Data');

        $this->artisan('fiscal:certificado-vigilancia')
            ->expectsOutputToContain('sem validade lida')
            ->assertSuccessful();
    }

    /** Empresa sem certificado não aparece: nada a vigiar. */
    public function test_empresa_sem_certificado_nao_entra_na_conta(): void
    {
        Empresa::factory()->create(['nome_fantasia' => 'Sem Fiscal']);

        $this->artisan('fiscal:certificado-vigilancia')
            ->expectsOutputToContain('0 empresa(s) com certificado')
            ->assertSuccessful();
    }

    /**
     * O ponto do SaaS: a vigilância enxerga TODAS as revendas.
     *
     * Este é o teste que protege contra o modo de falhar mais perigoso — uma
     * consulta recortada por tenant sairia vazia no cron, e "nenhum certificado
     * vencendo" é exatamente o que se espera ler. Ninguém desconfiaria.
     */
    public function test_a_vigilancia_atravessa_as_revendas(): void
    {
        $this->empresaComCertificado(now()->subDays(1)->toDateTimeString(), 'Revenda A');
        $this->empresaComCertificado(now()->addDays(10)->toDateTimeString(), 'Revenda B');
        $this->empresaComCertificado(now()->addYear()->toDateTimeString(), 'Revenda C');

        $this->artisan('fiscal:certificado-vigilancia')
            ->expectsOutputToContain('3 empresa(s) com certificado')
            ->expectsOutputToContain('Revenda A')
            ->expectsOutputToContain('Revenda B')
            ->assertFailed();
    }

    /** Dá para olhar uma revenda só, sem o ruído das outras. */
    public function test_filtro_por_empresa(): void
    {
        $vencida = $this->empresaComCertificado(now()->subDays(5)->toDateTimeString(), 'Revenda Vencida');
        $this->empresaComCertificado(now()->addYear()->toDateTimeString(), 'Revenda Ok');

        $this->artisan('fiscal:certificado-vigilancia --empresa='.$vencida->id)
            ->expectsOutputToContain('1 empresa(s) com certificado')
            ->assertFailed();
    }
}
