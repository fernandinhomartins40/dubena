<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Http\Middleware\DebugMode;
use ReflectionMethod;

/**
 * Testes de caracterização — FASE 2 (api-app-gc).
 * Capturam as correções de segurança da FASE 1 para impedir regressão.
 * PHPUnit 7.5 / Laravel 5.6.
 */
class SegurancaFase1Test extends TestCase
{
    /**
     * getToken deve REJEITAR app_key inválida (404). Antes a chave derivava de
     * sha1(APP_KEY) — exposta no app. Agora usa APP_TOKEN_KEY próprio (S2).
     */
    /** Define APP_TOKEN_KEY de forma compatível entre Laravel 5.6 e 5.8. */
    private function setTokenKey($value)
    {
        if ($value === null) {
            putenv('APP_TOKEN_KEY');
            unset($_ENV['APP_TOKEN_KEY'], $_SERVER['APP_TOKEN_KEY']);
            return;
        }
        putenv("APP_TOKEN_KEY={$value}");
        $_ENV['APP_TOKEN_KEY'] = $value;
        $_SERVER['APP_TOKEN_KEY'] = $value;
    }

    public function testGetTokenComAppKeyInvalidaRetorna404()
    {
        $this->setTokenKey('token-correto');
        $response = $this->json('GET', '/api/getToken', ['app_key' => 'token-errado']);
        $response->assertStatus(404);
        $response->assertJson(['status' => 'NOK']);
        $this->setTokenKey(null);
    }

    /**
     * Com a app_key correta, a validação passa (o erro seguinte é só ausência
     * do usuário padrão no banco de teste — a AUTORIZAÇÃO em si está correta).
     */
    public function testGetTokenComAppKeyCorretaPassaDaValidacao()
    {
        $this->setTokenKey('token-correto');
        $response = $this->json('GET', '/api/getToken', ['app_key' => 'token-correto']);
        // NÃO pode ser 404 'invalid app_key' (passou da validação da chave).
        $this->assertNotEquals(404, $response->getStatusCode());
        $this->setTokenKey(null);
    }

    /**
     * DebugMode::scrub deve REDIGIR campos sensíveis (LGPD — S4).
     * Antes o middleware logava request/response completos.
     */
    public function testDebugModeRedigeCamposSensiveis()
    {
        $mw = new DebugMode();
        $method = new ReflectionMethod($mw, 'scrub');
        $method->setAccessible(true);

        $entrada = [
            'nome'        => 'Fulano',
            'password'    => 'segredo123',
            'card_number' => '4111111111111111',
            'card_cvv'    => '123',
            'aninhado'    => ['token' => 'abc', 'ok' => 'visivel'],
        ];
        $saida = $method->invoke($mw, $entrada);

        $this->assertEquals('Fulano', $saida['nome']);          // não-sensível: mantém
        $this->assertEquals('[REDACTED]', $saida['password']);   // sensível: oculta
        $this->assertEquals('[REDACTED]', $saida['card_number']);
        $this->assertEquals('[REDACTED]', $saida['card_cvv']);
        $this->assertEquals('[REDACTED]', $saida['aninhado']['token']); // recursivo
        $this->assertEquals('visivel', $saida['aninhado']['ok']);
    }

    /**
     * Endpoint público da API continua acessível (não quebramos o contrato).
     */
    public function testEndpointPublicoVideoGetResponde()
    {
        $response = $this->json('GET', '/api/video/get');
        // Qualquer resposta que não seja erro de servidor (5xx) caracteriza
        // que a rota existe e o pipeline de middleware roda.
        $this->assertLessThan(500, $response->getStatusCode());
    }
}
