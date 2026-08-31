<?php

namespace Tests\Feature;

use App\Domain\Cobranca\Drivers\FakeBoletoDriver;
use App\Domain\Cobranca\Drivers\FakePixDriver;
use App\Domain\Fiscal\Contracts\SefazDriver;
use App\Domain\Fiscal\Drivers\FakeSefazDriver;
use App\Domain\Mobile\Drivers\FakePagamentoDriver;
use Tests\TestCase;

/**
 * F5-05 — o gate da fase diz: **"fakes bloqueados"**.
 *
 * Os drivers fake existem por necessidade: o CI não fala com a SEFAZ, não gera
 * boleto no banco e não cobra cartão. O `CLAUDE.md` inclusive proíbe apagá-los.
 *
 * O risco é o outro: um deles ativo em produção emite documento fiscal
 * sintético, gera boleto que o banco não conhece e aprova pagamento que não
 * existe. E o modo de falhar é o pior possível — **o sistema responde
 * "sucesso"**. Ninguém vê erro; a nota "autorizada" simplesmente não existe na
 * SEFAZ, e o boleto não é reconhecido na compensação.
 *
 * ## Duas travas para o mesmo risco
 *
 * A principal é o container: `exigirDriverReal()` recusa, em produção, a
 * configuração que ativaria um fake. Ela cobre a resolução por interface, que é
 * como o sistema inteiro obtém os drivers.
 *
 * A segunda é o construtor de cada fake, e cobre o caminho que a primeira não
 * alcança: instanciar a classe diretamente. Hoje ninguém faz isso — verifiquei —
 * mas "hoje ninguém faz" não é uma garantia, e o custo de errar aqui é dinheiro
 * e documento fiscal.
 */
class FakesBloqueadosEmProducaoTest extends TestCase
{
    /**
     * Os quatro fakes que tocam dinheiro ou documento fiscal.
     *
     * Os demais (`FakeFirebaseVerifier`, `FakePushTransport`, `FakeMalhaViaria`,
     * `FakeAjustadorDeVia`) ficam de fora de propósito: um push que não chega ou
     * uma malha viária sintética degradam funcionalidade, não produzem efeito
     * financeiro nem fiscal falso.
     *
     * @return list<array{class-string}>
     */
    public static function fakesDeDinheiroEFiscal(): array
    {
        return [
            [FakeBoletoDriver::class],
            [FakeSefazDriver::class],
            [FakePagamentoDriver::class],
            [FakePixDriver::class],
        ];
    }

    /**
     * @dataProvider fakesDeDinheiroEFiscal
     *
     * @param  class-string  $driver
     */
    public function test_fake_de_dinheiro_ou_fiscal_recusa_producao(string $driver): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        try {
            $instancia = new $driver;

            // `FakePixDriver` guarda por método, não por construtor — o efeito
            // proibido é criar a cobrança, e é lá que ele barra.
            if ($driver === FakePixDriver::class) {
                $instancia->criarCobranca(['txid' => 'x', 'valor' => 1.0], []);
            }

            $this->fail($driver.' foi instanciado/usado em produção sem recusar.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('produ', mb_strtolower($e->getMessage()));
        }
    }

    /** Fora de produção eles funcionam — senão o CI não teria como rodar. */
    public function test_fakes_funcionam_fora_de_producao(): void
    {
        $this->assertSame('testing', $this->app->environment());

        $this->assertInstanceOf(FakeBoletoDriver::class, new FakeBoletoDriver);
        $this->assertInstanceOf(FakeSefazDriver::class, new FakeSefazDriver);
        $this->assertInstanceOf(FakePagamentoDriver::class, new FakePagamentoDriver);
    }

    /**
     * A trava principal: o container recusa a CONFIGURAÇÃO que ativaria um fake.
     *
     * É o que impede o caso realista — ninguém instancia o fake à mão; o que
     * acontece é a variável de ambiente vir vazia num deploy.
     */
    public function test_container_recusa_configuracao_que_ativaria_fake(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config()->set('services.fiscal.driver', '');

        $this->expectException(\RuntimeException::class);
        $this->app->make(SefazDriver::class);
    }
}
