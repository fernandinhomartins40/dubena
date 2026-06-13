<?php

/**
 * Testes de caracterização — FASE 2.
 * Capturam o comportamento das correções de segurança da FASE 1 no
 * monitoramento-veiculos, para detectar regressão nas fases seguintes
 * (migração de banco, upgrade de framework).
 *
 * PHPUnit 5.7 (Laravel 5.4). Não dependem de dados no banco.
 */
class SegurancaFase1Test extends TestCase
{
    /**
     * encodeSecret NÃO deve mais usar a chave literal 'secret' (achado S7).
     * Caracteriza: a assinatura muda quando a chave HMAC muda.
     */
    public function testEncodeSecretNaoUsaChaveLiteralSecret()
    {
        putenv('SECRET_HMAC_KEY=chave-A');
        $comA = encodeSecret('payload');

        putenv('SECRET_HMAC_KEY=chave-B');
        $comB = encodeSecret('payload');

        // Chaves diferentes => assinaturas diferentes (a chave é realmente usada).
        $this->assertNotEquals($comA, $comB);

        // E nenhuma delas é igual à assinatura com a chave antiga 'secret'.
        $legado = base64_encode(hash_hmac('sha256', 'payload', 'secret', true));
        $this->assertNotEquals($legado, $comA);
        $this->assertNotEquals($legado, $comB);

        putenv('SECRET_HMAC_KEY'); // limpa
    }

    /**
     * encodeSecret é determinístico para a mesma chave/entrada (compatível com
     * verificação de assinatura). Caracteriza o formato base64 de 32 bytes.
     */
    public function testEncodeSecretDeterministicoEFormato()
    {
        putenv('SECRET_HMAC_KEY=chave-fixa');
        $a = encodeSecret('mesma-entrada');
        $b = encodeSecret('mesma-entrada');
        $this->assertEquals($a, $b);
        // SHA-256 => 32 bytes => 44 chars em base64.
        $this->assertEquals(44, strlen($a));
        putenv('SECRET_HMAC_KEY');
    }

    /**
     * /savePosition (rota pública de integração) deve REJEITAR sem token (S3).
     * Antes respondia 200 e gravava posição de qualquer veículo.
     */
    public function testSavePositionSemTokenRetorna401()
    {
        $response = $this->call('GET', '/api/savePosition', [
            'id' => 1, 'latitude' => -25.4, 'longitude' => -51.4,
            'altitude' => 0, 'speed' => 0, 'course' => 0, 'fixTime' => 1700000000,
        ]);
        $this->assertEquals(401, $response->getStatusCode());
    }

    /**
     * Com token válido mas device inexistente, NÃO grava e retorna 404.
     */
    public function testSavePositionComTokenDeviceInexistenteRetorna404()
    {
        // Injeta o token tanto via putenv quanto $_ENV/$_SERVER para compatibilidade
        // entre Laravel 5.4 e 5.8 (a resolução de env() em teste mudou entre versões).
        putenv('INTEGRATION_TOKEN=token-de-teste');
        $_ENV['INTEGRATION_TOKEN'] = 'token-de-teste';
        $_SERVER['INTEGRATION_TOKEN'] = 'token-de-teste';

        $response = $this->call('GET', '/api/savePosition', [
            'id' => 999999, 'latitude' => -25.4, 'longitude' => -51.4,
            'altitude' => 0, 'speed' => 0, 'course' => 0, 'fixTime' => 1700000000,
            'token' => 'token-de-teste', // o controller também aceita via parâmetro
        ]);
        $this->assertEquals(404, $response->getStatusCode());

        putenv('INTEGRATION_TOKEN');
        unset($_ENV['INTEGRATION_TOKEN'], $_SERVER['INTEGRATION_TOKEN']);
    }
}
