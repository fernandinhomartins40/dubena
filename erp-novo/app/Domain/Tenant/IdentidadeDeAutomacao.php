<?php

namespace App\Domain\Tenant;

use App\Models\Saas\TenantAccount;
use App\Models\Saas\TenantCompany;
use App\Models\Saas\TenantCompanyGrant;
use App\Models\Saas\TenantMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A identidade com que as rotinas agendadas operam em cada tenant.
 *
 * ## O problema
 *
 * O agendador não tem usuário. As policies canônicas só liberam uma linha para
 * um MEMBERSHIP com grant na empresa (`app_tenant_can_read/operate`), então um
 * comando de console não lê nem grava dado de revenda nenhuma. Medido na
 * homologação em 10/10/2026: sincronização de GPS, geração de missões,
 * vigilância de comodato e de certificado, alertas — todos terminavam com
 * `DONE` sem ter feito nada.
 *
 * ## Por que um usuário de serviço, e não "o cron lê tudo"
 *
 * Rodar o cron por uma conexão que ignora RLS resolveria em uma linha, e
 * deixaria o banco sem defesa justamente contra o código que roda sozinho, sem
 * ninguém olhando: um `where` esquecido num comando agendado cruzaria dados
 * entre revendas, e nada barraria. Com identidade própria o cron é só mais um
 * ator — o que ele não tem grant para ver, o banco não entrega.
 *
 * ## Por que não o membership de uma pessoa
 *
 * O cron não pode agir "como o dono". A trilha passaria a dizer que a pessoa
 * fez o que não fez, e desligar a pessoa pararia a automação da rede inteira.
 *
 * ## O que a identidade é
 *
 * Um `User` por tenant, que NÃO entra: `ativo = false` (o login exige ativo) e
 * senha aleatória que ninguém conhece. Por ser inativo, também não ocupa vaga
 * no limite de usuários do plano. Tem um membership de papel `AUTOMATION` e
 * grant de leitura e operação em cada empresa APROVADA do tenant — e só nelas.
 *
 * Nada disto é criado sozinho: nasce de `saas:automacao:provisionar`, que
 * registra na trilha de plataforma. Empresa nova no tenant só é alcançada
 * depois de provisionar de novo, e o `golive:check` acusa a que ficou de fora.
 */
final class IdentidadeDeAutomacao
{
    public const PAPEL = 'AUTOMATION';

    public const EVIDENCIA = 'console:saas:automacao:provisionar';

    /**
     * E-mail do usuário de serviço. O domínio `.invalid` é reservado (RFC 2606):
     * nunca resolve, então nenhum fluxo de e-mail entrega nada a ele.
     */
    public static function email(int $tenantAccountId): string
    {
        return "automacao.tenant{$tenantAccountId}@sistema.invalid";
    }

    /**
     * Garante usuário, membership e grants do tenant. Idempotente.
     *
     * Também RETIRA o grant da empresa que deixou de estar aprovada: a
     * identidade alcança exatamente a fronteira vigente, nem menos nem mais.
     *
     * @return array{usuario_criado: bool, membership_criada: bool, grants_criados: int, grants_removidos: int, empresas: list<int>}
     */
    public function provisionar(TenantAccount $tenant): array
    {
        return DB::transaction(function () use ($tenant) {
            $user = User::query()->where('email', self::email($tenant->id))->first();
            $usuarioCriado = $user === null;

            if ($usuarioCriado) {
                $user = new User([
                    'name' => 'Automação — '.$tenant->legal_name,
                    'email' => self::email($tenant->id),
                    // Aleatória e descartada: ninguém entra com este usuário.
                    'password' => Hash::make(Str::random(64)),
                    // Sem empresa padrão: a identidade não "pertence" a uma
                    // unidade, e assim não aparece na lista de usuários de
                    // nenhuma delas.
                    'empresa_id' => null,
                    'grupo_id' => null,
                ]);
            }
            // Sempre reafirmado: reativar este usuário à mão abriria uma porta
            // de login numa conta com grant em toda a rede.
            $user->ativo = false;
            $user->save();

            $membership = TenantMembership::query()
                ->where('tenant_account_id', $tenant->id)
                ->where('user_id', $user->id)
                ->first();
            $membershipCriada = $membership === null;

            if ($membershipCriada) {
                $membership = TenantMembership::create([
                    'tenant_account_id' => $tenant->id,
                    'user_id' => $user->id,
                    'status' => TenantMembership::STATUS_ACTIVE,
                    'membership_role' => self::PAPEL,
                    'approved_at' => now(),
                    'approval_evidence_ref' => self::EVIDENCIA,
                ]);
            }

            $aprovadas = TenantCompany::query()
                ->where('tenant_account_id', $tenant->id)
                ->where('status', TenantCompany::STATUS_APPROVED)
                ->get(['id', 'empresa_id']);

            $criados = 0;
            foreach ($aprovadas as $company) {
                $grant = TenantCompanyGrant::firstOrCreate(
                    ['tenant_membership_id' => $membership->id, 'empresa_id' => $company->empresa_id],
                    [
                        'tenant_account_id' => $tenant->id,
                        'tenant_company_id' => $company->id,
                        'can_read' => true,
                        'can_operate' => true,
                        'approved_at' => now(),
                        'grant_evidence_ref' => self::EVIDENCIA,
                    ],
                );
                $criados += $grant->wasRecentlyCreated ? 1 : 0;
            }

            $removidos = TenantCompanyGrant::query()
                ->where('tenant_membership_id', $membership->id)
                ->whereNotIn('empresa_id', $aprovadas->pluck('empresa_id'))
                ->delete();

            return [
                'usuario_criado' => $usuarioCriado,
                'membership_criada' => $membershipCriada,
                'grants_criados' => $criados,
                'grants_removidos' => (int) $removidos,
                'empresas' => $aprovadas->pluck('empresa_id')->map(fn ($id) => (int) $id)->sort()->values()->all(),
            ];
        });
    }

    /**
     * Empresas aprovadas e ativas que a automação NÃO alcança — sem membership
     * de automação no tenant, ou sem grant nela.
     *
     * @return array<int, string> empresa_id => razão social
     */
    public function empresasDescobertas(): array
    {
        return ConexaoDeOwner::executar(fn () => DB::table('tenant_companies as tc')
            ->join('empresas as e', 'e.id', '=', 'tc.empresa_id')
            ->where('tc.status', TenantCompany::STATUS_APPROVED)
            ->where('e.ativo', true)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from('tenant_company_grants as g')
                ->join('tenant_memberships as m', 'm.id', '=', 'g.tenant_membership_id')
                ->whereColumn('g.empresa_id', 'tc.empresa_id')
                ->whereColumn('m.tenant_account_id', 'tc.tenant_account_id')
                ->where('m.membership_role', self::PAPEL)
                ->where('m.status', TenantMembership::STATUS_ACTIVE)
                ->where('g.can_read', true)->where('g.can_operate', true)
                ->whereNotNull('g.approved_at'))
            ->orderBy('tc.empresa_id')
            ->pluck('e.razao_social', 'tc.empresa_id')
            ->map(fn ($nome) => (string) $nome)
            ->all());
    }
}
