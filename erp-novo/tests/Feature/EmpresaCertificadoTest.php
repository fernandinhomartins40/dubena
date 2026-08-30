<?php

namespace Tests\Feature;

use App\Domain\Fiscal\CertificadoService;
use App\Models\Empresa;
use App\Models\EmpresaConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * FASE C2 — Empresa/Config: certificado A1, NFC-e (CSC), testar e-mail.
 *
 * Garante: upload de A1 válido extrai validade/CNPJ e guarda em disco privado;
 * .pfx/senha inválidos retornam 422 amigável; segredos (senha, token, path)
 * nunca voltam na API; NFC-e token grava; testar-email valida pré-requisito.
 */
class EmpresaCertificadoTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Empresa} */
    private function suporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);

        return [$user, $empresa];
    }

    public function test_status_sem_certificado(): void
    {
        [$user, $empresa] = $this->suporte();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/empresas/{$empresa->id}/certificado")
            ->assertOk()
            ->assertJsonPath('data.tem_certificado', false);
    }

    public function test_upload_certificado_invalido_retorna_422(): void
    {
        [$user, $empresa] = $this->suporte();

        $arquivo = UploadedFile::fake()->createWithContent('cert.pfx', 'conteudo-que-nao-e-pfx');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/empresas/{$empresa->id}/certificado", [
                'certificado' => $arquivo,
                'senha' => 'qualquer',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('certificado');
    }

    public function test_upload_certificado_valido_extrai_metadados_e_guarda_privado(): void
    {
        $pfx = $this->gerarPfx('senha123', 'EMPRESA TESTE LTDA:12345678000199');
        if ($pfx === null) {
            $this->markTestSkipped('openssl não consegue gerar PKCS#12 neste ambiente.');
        }

        Storage::fake('local');
        [$user, $empresa] = $this->suporte();
        $arquivo = UploadedFile::fake()->createWithContent('cert.pfx', $pfx);

        $resp = $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/empresas/{$empresa->id}/certificado", [
                'certificado' => $arquivo,
                'senha' => 'senha123',
            ])
            ->assertOk();

        $resp->assertJsonPath('data.tem_certificado', true)
            ->assertJsonPath('data.cnpj', '12345678000199')
            ->assertJsonPath('data.expirado', false);

        // Segredos NÃO voltam.
        $this->assertArrayNotHasKey('cert_senha', $resp->json('data'));
        $this->assertArrayNotHasKey('cert_path', $resp->json('data'));

        // Arquivo gravado no disco privado; senha persistida ENCRIPTADA.
        $config = EmpresaConfig::where('empresa_id', $empresa->id)->first();
        $this->assertNotNull($config->cert_path);
        Storage::disk('local')->assertExists($config->cert_path);
        $this->assertSame('senha123', $config->cert_senha); // cast 'encrypted' descriptografa
        // Valor CRU no banco (DB::table ignora os casts) deve estar encriptado.
        $raw = DB::table('empresa_configs')->where('empresa_id', $empresa->id)->value('cert_senha');
        $this->assertNotSame('senha123', $raw, 'A senha deve estar encriptada no banco.');
    }

    public function test_nfce_token_grava_e_nao_vaza_token(): void
    {
        [$user, $empresa] = $this->suporte();

        $resp = $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/empresas/{$empresa->id}/nfce-token", [
                'nfce_csc_id' => '000001',
                'nfce_csc_token' => 'SEGREDO-CSC-NFCE',
            ])
            ->assertOk()
            ->assertJsonPath('data.nfce_csc_id', '000001');

        $this->assertStringNotContainsString('SEGREDO-CSC-NFCE', $resp->getContent());

        // Valor CRU no banco (sem casts) deve estar encriptado.
        $raw = DB::table('empresa_configs')->where('empresa_id', $empresa->id)->value('nfce_csc_token');
        $this->assertNotSame('SEGREDO-CSC-NFCE', $raw, 'Token CSC deve estar encriptado.');
    }

    public function test_testar_email_sem_smtp_configurado_retorna_422(): void
    {
        [$user, $empresa] = $this->suporte();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/empresas/{$empresa->id}/config/testar-email", [
                'to' => 'destino@teste.com',
            ])
            ->assertStatus(422);
    }

    public function test_testar_email_com_smtp_envia(): void
    {
        [$user, $empresa] = $this->suporte();

        // Seam de teste: faz o controller usar o coletor 'array' (sem rede).
        config()->set('mail.empresa_test_transport', true);

        EmpresaConfig::firstOrCreate(['empresa_id' => $empresa->id])->update([
            'email_host' => 'smtp.teste.com',
            'email_port' => 587,
            'email_username' => 'envio@teste.com',
            'email_password' => 'segredo',
            'email_encryption' => 'tls',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/empresas/{$empresa->id}/config/testar-email", [
                'to' => 'destino@teste.com',
                'subject' => 'Olá',
            ])
            ->assertOk();

        // O transport 'array' do mailer dedicado acumula a mensagem enviada.
        $coletadas = app('mail.manager')->mailer('empresa_smtp')->getSymfonyTransport()->messages();
        $this->assertCount(1, $coletadas);
        $this->assertStringContainsString('destino@teste.com', $coletadas[0]->getMessage()->toString());
    }

    public function test_status_calcula_dias_restantes_e_expirado(): void
    {
        $service = new CertificadoService;
        $config = new EmpresaConfig([
            'empresa_id' => 1,
        ]);
        $config->cert_path = 'certificados/x.pfx';
        $config->cert_validade = now()->subDay();
        $config->cert_titular = 'EMPRESA';
        $config->cert_cnpj = '12345678000199';

        $status = $service->status($config);
        $this->assertTrue($status['tem_certificado']);
        $this->assertTrue($status['expirado']);
    }

    /** Gera um PKCS#12 self-signed para teste; null se o ambiente não suportar. */
    private function gerarPfx(string $senha, string $cn): ?string
    {
        // openssl.cnf mínimo — alguns ambientes (Windows/CI) não têm um default,
        // o que faz openssl_csr_sign falhar sem este config explícito.
        $cnf = tempnam(sys_get_temp_dir(), 'osl').'.cnf';
        file_put_contents($cnf, "[req]\ndistinguished_name=dn\n[dn]\n");
        $args = ['config' => $cnf, 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'digest_alg' => 'sha256'];

        try {
            $pkey = @openssl_pkey_new($args);
            if ($pkey === false) {
                return null;
            }
            $csr = @openssl_csr_new(['commonName' => $cn], $pkey, $args);
            if ($csr === false) {
                return null;
            }
            $x509 = @openssl_csr_sign($csr, null, $pkey, 365, $args);
            if ($x509 === false) {
                return null;
            }
            $out = '';
            if (! @openssl_pkcs12_export($x509, $out, $pkey, $senha, ['config' => $cnf])) {
                return null;
            }

            return $out;
        } finally {
            @unlink($cnf);
        }
    }
}
