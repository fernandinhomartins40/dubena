<?php

namespace App\Domain\Integracao;

use App\Domain\Tenant\TenantContext;
use App\Models\ConfigGlobal;
use App\Models\EmpresaConfig;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Resolvedor central de CREDENCIAIS por tenant (spec: docs/01-vigente/INTEGRACOES_MULTITENANT.md).
 *
 * Ponto único onde os drivers (PIX, cartão, Maps) buscam a credencial da EMPRESA
 * ou do GRUPO ativos, com fallback para o env da plataforma. Fecha o buraco em que
 * PIX/cartão/Maps liam `config()` global — o que faria todas as empresas cobrarem
 * pelo MESMO credenciamento numa plataforma multi-tenant.
 *
 * Ordem de resolução: EMPRESA → GRUPO → PLATAFORMA (env). Para dinheiro/fiscal, o
 * nível empresa é OBRIGATÓRIO (o fallback env só serve dev/homolog).
 *
 * Segredos ficam em `empresa_configs.dados['integracoes'][<serviço>]`, com os campos
 * sensíveis cifrados por-valor (Crypt) no WRITE — o GET nunca devolve o segredo.
 */
class IntegracaoTenant
{
    public function __construct(private TenantContext $tenant) {}

    /**
     * Credenciais PIX da empresa. Null se a empresa não configurou (aí o gate real
     * não deve operar; em dev, o Fake ignora credencial).
     *
     * @return array{psp:string, client_id:?string, client_secret:?string, chave:?string, webhook_hmac_secret:?string, ambiente:string}|null
     */
    public function pix(?int $empresaId = null): ?array
    {
        $c = $this->integracao('pix', $empresaId);
        if ($c === null) {
            return null;
        }

        return [
            'psp' => (string) ($c['psp'] ?? config('services.pix.psp', 'itau')),
            'client_id' => $c['client_id'] ?? null,
            'client_secret' => $this->decifra($c['client_secret'] ?? null),
            'chave' => $c['chave'] ?? null, // chave PIX recebedora (e-mail/cnpj/aleatória)
            'webhook_hmac_secret' => $this->decifra($c['webhook_hmac_secret'] ?? null),
            'ambiente' => (string) ($c['ambiente'] ?? config('services.pix.ambiente', 'homologacao')),
        ];
    }

    /** A empresa tem PIX próprio configurado? (sem decifrar). */
    public function pixConfigurado(?int $empresaId = null): bool
    {
        $c = $this->integracao('pix', $empresaId);

        return $c !== null && ! empty($c['client_id']) && ! empty($c['client_secret']);
    }

    /**
     * Credenciais do gateway de cartão da empresa (eRede: PV+token).
     *
     * FAIL-CLOSED (FASE 2): em produção a credencial da PRÓPRIA empresa é
     * obrigatória — herdar o env cobraria no credenciamento de outra entidade.
     * O fallback env existe SÓ fora de produção (conta de teste de dev/homolog).
     *
     * @return array{gateway:string, pv:?string, token:?string, url:?string}
     *
     * @throws CredencialNaoConfiguradaException produção sem credencial da empresa
     */
    public function cartao(?int $empresaId = null): array
    {
        $c = $this->integracao('cartao', $empresaId) ?? [];

        $pv = $c['pv'] ?? null;
        $token = $this->decifra($c['token'] ?? null);

        if ($pv === null || $pv === '' || $token === null) {
            if (app()->isProduction()) {
                throw CredencialNaoConfiguradaException::cartao($empresaId ?? $this->tenant->empresaId());
            }
            $pv = ($pv === null || $pv === '') ? config('services.erede.pv') : $pv;
            $token ??= config('services.erede.token');
        }

        return [
            'gateway' => (string) ($c['gateway'] ?? config('services.pagamento.driver', 'fake')),
            'pv' => $pv,
            'token' => $token,
            'url' => (string) ($c['url'] ?? config('services.erede.url')),
        ];
    }

    public function cartaoConfigurado(?int $empresaId = null): bool
    {
        $c = $this->integracao('cartao', $empresaId);

        return $c !== null && ! empty($c['pv']) && ! empty($c['token']);
    }

    /**
     * Google Maps/Routes key do GRUPO ativo (config_globais) com fallback env.
     * Maps é por rede (não por revenda): uma chave por grupo é o comum.
     *
     * Fora de request (job/cron), passe o grupoId EXPLÍCITO do recurso — o
     * TenantContext ambient está vazio lá e a resolução cairia no env da
     * plataforma em silêncio (F5: logamos warning quando isso acontece).
     */
    public function googleMapsKey(?int $grupoId = null): ?string
    {
        $grupoId ??= $this->tenant->grupoId();
        if ($grupoId !== null) {
            $key = ConfigGlobal::withoutGrupo()
                ->where('grupo_id', $grupoId)
                ->value('google_maps_key');
            if (! empty($key)) {
                return (string) $key;
            }
        }

        $env = config('services.geocoding.key');

        if ($grupoId === null && ! empty($env)) {
            // Sem grupo resolvido (nem explícito, nem no contexto): consumo vai
            // para a key da PLATAFORMA. Legítimo em dev/CLI; em produção indica
            // chamada fora de request sem id explícito — fica visível no log.
            \Illuminate\Support\Facades\Log::warning('integracao: googleMapsKey sem grupo resolvido — usando key da plataforma (env)');
        }

        return ! empty($env) ? (string) $env : null;
    }

    /**
     * Lê o bloco de uma integração da empresa ativa (JSON dados['integracoes'][x]).
     *
     * @return array<string,mixed>|null
     */
    private function integracao(string $servico, ?int $empresaId): ?array
    {
        $empresaId ??= $this->tenant->empresaId();
        if ($empresaId === null) {
            return null;
        }

        $dados = EmpresaConfig::query()->where('empresa_id', $empresaId)->value('dados');
        $bloco = $dados['integracoes'][$servico] ?? null;

        return is_array($bloco) ? $bloco : null;
    }

    /** Decifra um valor guardado com Crypt; tolera valor já em claro/ausente. */
    private function decifra(?string $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        try {
            return Crypt::decryptString($valor);
        } catch (DecryptException) {
            // Valor não cifrado (ex.: migração/manual) — devolve como está.
            return $valor;
        }
    }

    /**
     * Cifra os campos sensíveis de um bloco de integração para persistir em
     * `dados['integracoes'][x]`. Usado pelo controller ao salvar. Campos vazios
     * são preservados (não sobrescreve o segredo existente com vazio).
     *
     * @param  array<string,mixed>  $entrada
     * @param  list<string>  $camposSensiveis
     * @param  array<string,mixed>  $atual  bloco já salvo (p/ preservar segredo não reenviado)
     * @return array<string,mixed>
     */
    public static function cifrarBloco(array $entrada, array $camposSensiveis, array $atual = []): array
    {
        $saida = array_merge($atual, array_diff_key($entrada, array_flip($camposSensiveis)));

        foreach ($camposSensiveis as $campo) {
            if (! array_key_exists($campo, $entrada)) {
                continue; // não enviado → mantém o que estava
            }
            $valor = (string) $entrada[$campo];
            if ($valor === '') {
                continue; // vazio → não apaga o segredo existente
            }
            $saida[$campo] = Crypt::encryptString($valor);
        }

        return $saida;
    }
}
