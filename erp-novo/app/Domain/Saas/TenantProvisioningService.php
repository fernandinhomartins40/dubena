<?php

namespace App\Domain\Saas;

use App\Domain\Financeiro\PlanoContaModeloService;
use App\Domain\Tenant\ConexaoDeOwner;
use App\Domain\Tenant\TenantContext;
use App\Domain\Tenant\TenantEnvelope;
use App\Domain\Tenant\TenantEnvelopeRuntime;
use App\Domain\Tenant\TenantMappingImporter;
use App\Models\Empresa;
use App\Models\Grupo;
use App\Models\Role;
use App\Models\Saas\Assinatura;
use App\Models\Saas\Plano;
use App\Models\Saas\TenantCompany;
use App\Models\Saas\TenantMembership;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * PIE P2-A-01 — cria a revenda nº N pelo produto, não por console.
 *
 * Antes, a única porta era `saas:tenant:importar <json>`, que exige empresa e
 * usuário já existentes e evidência escrita à mão. Aqui, numa transação só:
 * grupo, empresa, dono, titular (TenantAccount) com vínculo e grant, papéis
 * padrão, plano de contas, catálogo inicial e assinatura. Se qualquer etapa
 * falha, nada fica — revenda pela metade é pior do que nenhuma: um tenant sem
 * grant é um login que dá 403 para sempre, e um grupo sem tenant é linha que
 * ninguém enxerga.
 *
 * Pela conexão de owner: a plataforma não tem tenant resolvido e, pelo runtime,
 * não enxergaria nem a empresa que acabou de criar. O catálogo, porém, é
 * gravado DENTRO do envelope do tenant novo, para cada linha nascer com a chave
 * SaaS — o mesmo caminho de um cadastro feito pelo próprio dono.
 *
 * O dono nasce SEM senha conhecida (hash de um aleatório descartado). Ele recebe
 * um convite de uso único — o token de reset do framework — e define a senha
 * dele. Não há senha padrão, e o operador da plataforma nunca a conhece.
 */
final class TenantProvisioningService
{
    public function __construct(
        private TenantMappingImporter $importer,
        private PlanoContaModeloService $planoContas,
        private CatalogoInicialRevenda $catalogo,
        private SuperAdminService $superAdmin,
        private AuditoriaPlataforma $auditoria,
        private TenantEnvelopeRuntime $runtime,
        private TenantContext $contexto,
    ) {}

    /**
     * @param  array{razao_social: string, nome_fantasia?: ?string, cnpj?: ?string, dono_nome: string, dono_email: string, plano_id?: ?int, trial_dias?: ?int}  $dados
     * @return array<string, mixed>
     */
    public function provisionar(array $dados): array
    {
        return ConexaoDeOwner::executar(fn () => DB::transaction(fn () => $this->criar($dados)));
    }

    /** @param  array<string, mixed>  $dados @return array<string, mixed> */
    private function criar(array $dados): array
    {
        $email = mb_strtolower(trim((string) $dados['dono_email']));
        $plano = $this->plano($dados['plano_id'] ?? null);
        $trialDias = (int) ($dados['trial_dias'] ?? config('superadmin.provisionamento.trial_dias', 0));

        // A validação do request já recusa e-mail existente; aqui é a garantia
        // dentro da transação. `users` é compartilhada com os clientes do app:
        // reaproveitar uma linha por e-mail entregaria a conta de outra pessoa
        // (PIE R1-01). E-mail existente é recusa, nunca reuso.
        if (User::query()->whereRaw('lower(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages(['dono_email' => 'Já existe um usuário com este e-mail.']);
        }

        $nome = trim((string) ($dados['nome_fantasia'] ?? '')) ?: trim((string) $dados['razao_social']);
        $grupo = Grupo::create(['descricao' => $nome, 'ativo' => true]);
        $empresa = Empresa::create([
            'grupo_id' => $grupo->id,
            'razao_social' => trim((string) $dados['razao_social']),
            'nome_fantasia' => $dados['nome_fantasia'] ?? null,
            'cnpj' => $dados['cnpj'] ?? null,
            'matriz' => true,
            'ativo' => true,
        ]);
        $dono = User::create([
            'name' => trim((string) $dados['dono_nome']),
            'email' => $email,
            'password' => Hash::make(Str::random(64)),
            'empresa_id' => $empresa->id,
            'grupo_id' => $grupo->id,
            'ativo' => true,
        ]);

        // As proteções do importador (empresa já vinculada, grant operacional
        // sem leitura, grupo de outro tenant) valem aqui também. A evidência é
        // o próprio ato registrado na trilha da plataforma.
        $evidencia = 'superadmin:revenda:'.(auth('platform')->id() ?? 'console').':'.Str::uuid();
        $this->importer->apply(['tenants' => [[
            'legal_name' => $empresa->razao_social,
            'document' => $empresa->cnpj,
            'classification_evidence_ref' => $evidencia,
            'companies' => [['empresa_id' => $empresa->id, 'ownership_evidence_ref' => $evidencia]],
            'legacy_group_scopes' => [['grupo_id' => $grupo->id, 'evidence_ref' => $evidencia]],
            'memberships' => [[
                'user_id' => $dono->id,
                'membership_role' => 'OWNER',
                'approval_evidence_ref' => $evidencia,
                'grants' => [['empresa_id' => $empresa->id, 'can_read' => true, 'can_operate' => true, 'grant_evidence_ref' => $evidencia]],
            ]],
        ]]]);

        $tenantId = (int) TenantCompany::query()->where('empresa_id', $empresa->id)->value('tenant_account_id');
        $membershipId = (int) TenantMembership::query()->where('tenant_account_id', $tenantId)->where('user_id', $dono->id)->value('id');
        DB::table('grupos')->where('id', $grupo->id)->update(['tenant_account_id' => $tenantId]);

        app(RbacSeeder::class)->papeisPadraoDoGrupo($grupo->id);
        $administrador = Role::query()->where('grupo_id', $grupo->id)->where('nome', 'Administrador')->firstOrFail();
        $dono->roles()->attach($administrador->id, ['empresa_id' => $empresa->id, 'herda_filhos' => true]);

        $this->planoContas->copiarParaGrupo($grupo->id, $tenantId);

        $envelope = new TenantEnvelope($tenantId, $membershipId, $empresa->id, [$empresa->id], [$empresa->id], 'provisionamento:'.Str::uuid());
        $catalogo = $this->runtime->run($envelope, function () use ($empresa) {
            $this->contexto->set($empresa->id, (int) $empresa->grupo_id);
            try {
                return $this->catalogo->aplicar($empresa);
            } finally {
                $this->contexto->clear();
            }
        });

        // Pelo serviço que já existe: a assinatura ganha evento, trilha e
        // invalidação de licença do mesmo jeito que pela tela de assinatura.
        $assinatura = $this->superAdmin->definirAssinatura($empresa->id, $plano->id, $trialDias > 0
            ? ['status' => Assinatura::STATUS_TRIAL, 'trial_ate' => now()->addDays($trialDias)->toDateString()]
            : ['status' => Assinatura::STATUS_ATIVA]);

        $convite = Password::broker('convites')->createToken($dono);

        $resumo = [
            'tenant_account_id' => $tenantId,
            'grupo_id' => $grupo->id,
            'empresa_id' => $empresa->id,
            'dono_user_id' => $dono->id,
            'plano' => $plano->slug,
            'status_assinatura' => $assinatura->status,
            'trial_ate' => $assinatura->trial_ate?->toDateString(),
            'catalogo' => $catalogo,
        ];
        // O token NUNCA entra na trilha: ela é lida por outros operadores.
        $this->auditoria->registrar('revenda.provisionada', $empresa->id, 'tenant_accounts', $tenantId, null, $resumo);

        return $resumo + [
            'dono' => ['id' => $dono->id, 'nome' => $dono->name, 'email' => $dono->email],
            'convite' => [
                'token' => $convite,
                'expira_em' => now()->addMinutes((int) config('auth.passwords.convites.expire'))->toIso8601String(),
            ],
        ];
    }

    private function plano(?int $planoId): Plano
    {
        $plano = $planoId !== null
            ? Plano::query()->find($planoId)
            : Plano::query()->where('slug', (string) config('superadmin.provisionamento.plano_padrao'))->first();

        // Fail-closed: sem plano válido não há revenda. Cair num plano qualquer
        // (ou em nenhum) daria uma revenda sem licença ou com a errada.
        if ($plano === null || ! $plano->ativo) {
            throw ValidationException::withMessages(['plano_id' => 'Plano inexistente ou inativo: escolha um plano vendável.']);
        }

        return $plano;
    }
}
