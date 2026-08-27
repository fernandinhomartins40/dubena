<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Models\EmpresaConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * golive:check (F16) — PORTÃO de prontidão para PRODUÇÃO.
 *
 * Complementa o cutover:check (que valida os DADOS migrados): aqui validamos a
 * INFRAESTRUTURA e a CONFIGURAÇÃO mínima para operar de verdade (faturar/cobrar/
 * rastrear) em produção multi-tenant. Cada verificação é PASS / WARN / FAIL:
 *  - FAIL  bloqueia o go-live (exit≠0);
 *  - WARN  não bloqueia, mas exige decisão consciente (ex.: gate em modo Fake);
 *  - PASS  ok.
 *
 * Use `--strict` para tratar WARN como bloqueio (go-live "produção plena").
 */
class GoliveCheck extends Command
{
    protected $signature = 'golive:check {--strict : trata avisos (WARN) como falha}';

    protected $description = 'Valida a prontidão de produção (config, gates, RLS, tenant) — portão do go-live.';

    private int $fail = 0;

    private int $warn = 0;

    public function handle(): int
    {
        $this->info('== golive:check — prontidão de produção ==');
        $this->newLine();

        $this->verificarApp();
        $this->verificarBanco();
        $this->verificarTenantRls();
        $this->verificarInfraAssincrona();
        $this->verificarGatesFiscalCobranca();
        $this->verificarConfigPorEmpresa();

        $this->newLine();
        $this->line("Resultado: {$this->fail} FALHA(s), {$this->warn} aviso(s).");

        $strict = (bool) $this->option('strict') || app()->isProduction();
        if ($this->fail > 0 || ($strict && $this->warn > 0)) {
            $this->error('PORTÃO FECHADO — resolva os itens acima antes do go-live'.($strict ? ' (modo strict).' : '.'));

            return self::FAILURE;
        }

        $this->info($this->warn > 0
            ? 'PORTÃO LIBERADO COM AVISOS — go-live possível, mas confirme os WARN.'
            : 'PORTÃO LIBERADO — produção pronta.');

        return self::SUCCESS;
    }

    private function verificarApp(): void
    {
        $this->item('APP_KEY definida', (bool) config('app.key'));
        $env = (string) config('app.env');
        $this->item("APP_ENV = {$env}", $env === 'production', $env === 'production' ? null
            : "ambiente não é 'production' (atual: {$env})", aviso: true);
        $this->item('APP_DEBUG desligado', config('app.debug') === false,
            'APP_DEBUG está ligado — desligue em produção');
    }

    private function verificarBanco(): void
    {
        $driver = DB::connection()->getDriverName();
        $this->item("Banco = {$driver}", $driver === 'pgsql', $driver === 'pgsql' ? null
            : "driver é '{$driver}'; produção usa pgsql", aviso: true);

        try {
            DB::connection()->getPdo();
            $this->item('Conexão com o banco', true);
        } catch (\Throwable $e) {
            $this->item('Conexão com o banco', false, $e->getMessage());
        }
    }

    private function verificarTenantRls(): void
    {
        // Middleware tenant registrado.
        $aliases = app('router')->getMiddleware();
        $this->item('Middleware "tenant" registrado', isset($aliases['tenant']));

        // RLS (só pgsql): a policy tenant_isolation deve existir em tabelas escopadas.
        if (DB::connection()->getDriverName() === 'pgsql') {
            try {
                $policies = DB::table('pg_policies')->where('policyname', 'tenant_isolation')->count();
                $this->item("RLS ativo (policies tenant_isolation = {$policies})", $policies > 0,
                    'nenhuma policy RLS encontrada — rode as migrations da F02');
            } catch (\Throwable $e) {
                $this->item('RLS verificável', false, $e->getMessage(), aviso: true);
            }

            $this->verificarRoleRuntime();
        } else {
            $this->item('RLS (pgsql)', true, 'não-pgsql: RLS é no-op (ok p/ dev/CI)', aviso: true);
        }
    }

    /**
     * MT-1/MT-2 (auditoria) — a RLS só protege se o RUNTIME conectar com uma role
     * SEM superuser/BYPASSRLS: conectado como owner/superuser, o PostgreSQL ignora
     * TODAS as policies silenciosamente e a 2ª barreira deixa de existir. Aqui isso
     * vira FAIL (bloqueia o go-live). A ausência da role `erp_app` também é FAIL —
     * sinal de que RLS_APP_DB_PASSWORD não foi definido e a migration da role foi
     * NO-OP (o furo silencioso apontado como MT-2).
     */
    private function verificarRoleRuntime(): void
    {
        try {
            $atual = DB::selectOne(
                'SELECT current_user AS usuario, rolsuper, rolbypassrls
                 FROM pg_roles WHERE rolname = current_user',
            );
            $restrita = $atual !== null && ! $atual->rolsuper && ! $atual->rolbypassrls;
            $this->item(
                'Role de runtime restrita (current_user='.($atual->usuario ?? '?').')',
                $restrita,
                'a conexão da app tem SUPERUSER/BYPASSRLS — o PostgreSQL IGNORA a RLS; conecte com a role restrita (DB_USERNAME=erp_app)',
            );

            // Runtime já restrito = invariante satisfeito (o nome da role não
            // importa). A existência da erp_app só é cobrada quando o runtime está
            // ERRADO — aí a ausência dela indica a causa raiz (MT-2): a migration
            // rls_role_app_sem_bypass foi NO-OP por falta de RLS_APP_DB_PASSWORD.
            if (! $restrita) {
                $temRole = DB::selectOne("SELECT 1 AS x FROM pg_roles WHERE rolname = 'erp_app'") !== null;
                $this->item('Role erp_app existe', $temRole,
                    'role não criada — defina RLS_APP_DB_PASSWORD e reexecute a migration rls_role_app_sem_bypass');
            }
        } catch (\Throwable $e) {
            $this->item('Role de runtime verificável', false, $e->getMessage(), aviso: true);
        }
    }

    /**
     * PF-5/6/8 (auditoria) — a infra assíncrona/tempo-real precisa estar ligada,
     * senão jobs (auto-atribuição, push, geocodificação), agendamentos (expirar
     * PIX, gerar missões) e broadcast (mapa ao vivo) ficam inertes. Em produção,
     * fila/broadcast em modo dev (database/log) é WARN — o compose já sobe worker,
     * scheduler e Reverb, mas o .env precisa apontar para eles.
     */
    private function verificarInfraAssincrona(): void
    {
        $queue = (string) config('queue.default');
        $this->item("Fila (QUEUE_CONNECTION={$queue})", $queue !== 'sync',
            'fila em modo `sync` — jobs rodam inline no request (nunca em background); suba o worker (container queue) e aponte QUEUE_CONNECTION para database/redis',
            aviso: true);

        $broadcast = (string) config('broadcasting.default');
        $this->item("Broadcast (BROADCAST_CONNECTION={$broadcast})", $broadcast === 'reverb',
            'broadcast em modo `'.$broadcast.'` — sem Reverb os apps caem para polling (sem mapa ao vivo)', aviso: true);

        $cache = (string) config('cache.default');
        $this->item("Cache (CACHE_STORE={$cache})",
            in_array($cache, ['redis', 'memcached'], true),
            'cache em `'.$cache.'` — para produção prefira redis (concorrência)', aviso: true);
    }

    private function verificarGatesFiscalCobranca(): void
    {
        $fiscal = (string) config('services.fiscal.driver');
        $this->item("Gate fiscal (FISCAL_DRIVER={$fiscal})", $fiscal === 'nfephp',
            'SEFAZ em modo Fake — não emite NF-e real', aviso: true);

        $cobranca = (string) config('services.cobranca.driver');
        $this->item("Gate cobrança (COBRANCA_DRIVER={$cobranca})", in_array($cobranca, ['caixa', 'itau'], true),
            'Boleto em modo Fake — não gera CNAB real', aviso: true);

        $pix = (string) config('services.pix.driver', 'fake');
        $pixHabilitado = (bool) config('services.pix.enabled', false);

        if ($pix === 'fake') {
            $this->item(
                "Gate PIX (PIX_DRIVER={$pix}, PIX_ENABLED=".($pixHabilitado ? 'true' : 'false').')',
                false,
                $pixHabilitado
                    ? 'PIX habilitado com driver Fake — geraria uma cobrança sintética sem pagamento real'
                    : 'driver Fake permitido apenas em dev/CI; configure um PSP real implementado antes de habilitar em produção',
                aviso: ! $pixHabilitado,
            );

            return;
        }

        // Neste build, somente o fake de dev/CI está implementado. Um nome de PSP
        // desconhecido não pode contar como gate real: o container também recusará
        // resolvê-lo, em vez de degradar silenciosamente para uma cobrança sintética.
        $this->item(
            "Gate PIX (PIX_DRIVER={$pix})",
            false,
            "driver '{$pix}' não implementado neste build",
        );
    }

    private function verificarConfigPorEmpresa(): void
    {
        if (! Schema::hasTable('empresas')) {
            $this->item('Tabela empresas', false, 'schema não migrado');

            return;
        }

        $totalEmpresas = Empresa::query()->count();
        $this->item("Empresas cadastradas ({$totalEmpresas})", $totalEmpresas > 0,
            'nenhuma empresa — nada a operar', aviso: true);

        if ($totalEmpresas === 0) {
            return;
        }

        // Se o gate fiscal é real, toda empresa precisa de certificado A1 (config
        // existente COM cert_path). Conta empresas sem config OU com cert nulo.
        if (config('services.fiscal.driver') === 'nfephp') {
            $comCert = EmpresaConfig::query()->whereNotNull('cert_path')->pluck('empresa_id')->all();
            $semCert = Empresa::query()->whereNotIn('id', $comCert ?: [0])->count();
            $this->item('Certificado A1 por empresa (fiscal real)', $semCert === 0,
                "{$semCert} empresa(s) sem certificado configurado");
        }

        // Se o gate cobrança é real, ao menos uma conta de cobrança por empresa.
        if (in_array(config('services.cobranca.driver'), ['caixa', 'itau'], true)) {
            $semCobranca = EmpresaConfig::query()->get()->filter(
                fn ($c) => empty(($c->dados['cobranca'] ?? []))
            )->count();
            $this->item('Conta de cobrança por empresa (boleto real)', $semCobranca === 0,
                "{$semCobranca} empresa(s) sem conta de cobrança", aviso: true);
        }

        // Multi-tenant: se o PIX está habilitado, cada empresa precisa das PRÓPRIAS
        // credenciais (dados['integracoes']['pix']). Sem isso, a empresa não cobra.
        if (config('services.pix.enabled')) {
            $semPix = EmpresaConfig::query()->get()->filter(
                fn ($c) => empty($c->dados['integracoes']['pix']['client_secret'] ?? null)
            )->count();
            $this->item('Credencial PIX por empresa', $semPix === 0,
                "{$semPix} empresa(s) sem PIX configurado — cada revenda cobra com o seu PSP", aviso: true);
        }

        // Cartão real (gateway) — credencial por empresa.
        if (config('services.pagamento.driver') === 'erede') {
            $semCartao = EmpresaConfig::query()->get()->filter(
                fn ($c) => empty($c->dados['integracoes']['cartao']['token'] ?? null)
            )->count();
            $this->item('Credencial de cartão por empresa', $semCartao === 0,
                "{$semCartao} empresa(s) sem gateway de cartão configurado", aviso: true);
        }
    }

    /** Registra uma verificação. $aviso=true → WARN (não bloqueia) quando falha. */
    private function item(string $label, bool $ok, ?string $detalhe = null, bool $aviso = false): void
    {
        if ($ok) {
            $this->line("  <fg=green>PASS</> {$label}");

            return;
        }
        if ($aviso) {
            $this->warn("  WARN {$label}".($detalhe ? " — {$detalhe}" : ''));
            $this->warn++;

            return;
        }
        $this->error("  FAIL {$label}".($detalhe ? " — {$detalhe}" : ''));
        $this->fail++;
    }
}
