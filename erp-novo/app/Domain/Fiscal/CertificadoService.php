<?php

namespace App\Domain\Fiscal;

use App\Models\EmpresaConfig;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Certificado digital A1 (.pfx/.p12) — C2.
 *
 * Valida o certificado contra a senha (openssl_pkcs12_read), extrai metadados
 * (titular, CNPJ, validade) e guarda o arquivo no disco PRIVADO. A senha do .pfx
 * vai encriptada na EmpresaConfig (cast 'encrypted'); o arquivo nunca em public/.
 *
 * NÃO depende de NFePHP — usa só openssl nativo. A emissão fiscal (C7) lê daqui.
 */
class CertificadoService
{
    /** Disco privado e pasta dos certificados. */
    private const DISCO = 'local';

    private const PASTA = 'certificados';

    /**
     * Valida + armazena o A1 para a empresa. Retorna os metadados (sem segredos).
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException se o .pfx/senha forem inválidos ou estiver expirado.
     */
    public function armazenar(EmpresaConfig $config, string $conteudoPfx, string $senha): array
    {
        $info = $this->lerPfx($conteudoPfx, $senha);

        // Apaga o anterior (se houver) — uma empresa, um certificado vigente.
        if ($config->cert_path && Storage::disk(self::DISCO)->exists($config->cert_path)) {
            Storage::disk(self::DISCO)->delete($config->cert_path);
        }

        $caminho = self::PASTA.'/empresa_'.$config->empresa_id.'_'.now()->format('YmdHis').'.pfx';
        Storage::disk(self::DISCO)->put($caminho, $conteudoPfx);

        $config->forceFill([
            'cert_path' => $caminho,
            'cert_senha' => $senha,
            'cert_cnpj' => $info['cnpj'],
            'cert_titular' => $info['titular'],
            'cert_validade' => $info['validade'],
            'cert_uploaded_at' => now(),
        ])->save();

        return $this->status($config->refresh());
    }

    /**
     * Status do certificado para a SPA (sem o arquivo nem a senha).
     *
     * @return array<string, mixed>
     */
    public function status(EmpresaConfig $config): array
    {
        if (! $config->cert_path) {
            return ['tem_certificado' => false];
        }

        $validade = $config->cert_validade;
        $expirado = $validade !== null && $validade->isPast();
        $diasRestantes = $validade !== null ? (int) now()->startOfDay()->diffInDays($validade->startOfDay(), false) : null;

        return [
            'tem_certificado' => true,
            'titular' => $config->cert_titular,
            'cnpj' => $config->cert_cnpj,
            'validade' => $validade?->toIso8601String(),
            'expirado' => $expirado,
            'dias_restantes' => $diasRestantes,
            'uploaded_at' => $config->cert_uploaded_at?->toIso8601String(),
        ];
    }

    /**
     * Lê e valida o .pfx. Lança ValidationException com mensagem amigável se
     * a senha estiver errada, o arquivo for inválido ou já tiver expirado.
     *
     * @return array{titular: ?string, cnpj: ?string, validade: Carbon}
     */
    private function lerPfx(string $conteudo, string $senha): array
    {
        $certs = [];
        if (! openssl_pkcs12_read($conteudo, $certs, $senha)) {
            throw ValidationException::withMessages([
                'certificado' => 'Não foi possível abrir o certificado. Verifique o arquivo (.pfx/.p12) e a senha.',
            ]);
        }

        $dados = openssl_x509_parse($certs['cert'] ?? '');
        if ($dados === false) {
            throw ValidationException::withMessages(['certificado' => 'Certificado inválido.']);
        }

        $validade = Carbon::createFromTimestamp($dados['validTo_time_t'] ?? 0);
        if ($validade->isPast()) {
            throw ValidationException::withMessages([
                'certificado' => "Certificado expirado em {$validade->format('d/m/Y')}.",
            ]);
        }

        return [
            'titular' => $this->extrairTitular($dados),
            'cnpj' => $this->extrairCnpj($dados),
            'validade' => $validade,
        ];
    }

    /** CN do titular (ex.: "EMPRESA LTDA:12345678000199"). */
    private function extrairTitular(array $dados): ?string
    {
        $cn = $dados['subject']['CN'] ?? null;
        if (! is_string($cn)) {
            return null;
        }

        // O CN do e-CNPJ costuma vir "RAZAO SOCIAL:CNPJ" — fica só a razão.
        return trim(explode(':', $cn)[0]);
    }

    /** CNPJ embutido no CN (após ":") ou nulo. */
    private function extrairCnpj(array $dados): ?string
    {
        $cn = $dados['subject']['CN'] ?? '';
        if (is_string($cn) && preg_match('/:(\d{14})/', $cn, $m)) {
            return $m[1];
        }

        return null;
    }
}
