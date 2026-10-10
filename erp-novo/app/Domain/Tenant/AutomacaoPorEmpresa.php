<?php

namespace App\Domain\Tenant;

use App\Models\Saas\TenantCompany;
use App\Models\Saas\TenantMembership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Executa um trabalho agendado uma vez por empresa, com a identidade de
 * automação do tenant dela.
 *
 * Duas conexões, de propósito:
 *
 *  - a LISTA de empresas vem pela conexão de owner. Não há como descobrir por
 *    onde passar estando sob RLS e sem tenant — é a pergunta que o runtime não
 *    consegue responder;
 *  - o TRABALHO roda pelo runtime, dentro do envelope da identidade de
 *    automação. O que o comando ler ou gravar passa pelas mesmas policies de
 *    uma requisição: um `where` errado devolve zero, não o dado de outra revenda.
 *
 * Empresa sem identidade provisionada é PULADA e devolvida em `semIdentidade` —
 * nunca processada por outro caminho. Fail-closed: a alternativa seria operar
 * sem fronteira exatamente onde alguém esqueceu de configurá-la.
 */
final class AutomacaoPorEmpresa
{
    public function __construct(
        private TenantEnvelopeRuntime $runtime,
        private TenantContext $contexto,
    ) {}

    /**
     * @param  callable(int $empresaId): mixed  $trabalho
     * @param  int|null  $soEmpresa  restringe a uma empresa (opção `--empresa` dos comandos)
     * @param  bool  $soAtivas  empresa desativada não recebe rotina; o expurgo de dados é a exceção
     */
    public function paraCada(callable $trabalho, ?int $soEmpresa = null, bool $soAtivas = true): ResultadoDaAutomacao
    {
        $resultado = new ResultadoDaAutomacao;

        foreach ($this->alvos($soEmpresa, $soAtivas) as $alvo) {
            $empresaId = (int) $alvo->empresa_id;

            if ($alvo->membership_id === null || $alvo->grant_id === null) {
                $resultado->semIdentidade[] = $empresaId;

                continue;
            }

            $envelope = new TenantEnvelope(
                (int) $alvo->tenant_account_id,
                (int) $alvo->membership_id,
                $empresaId,
                [$empresaId],
                [$empresaId],
                'automacao:'.Str::uuid(),
            );

            try {
                // Antes do envelope: o runtime lê o grupo da empresa ativa em
                // `empresas`, cuja policy ainda é a de grupo — sem `app.grupo_id`
                // essa leitura devolve nada e o envelope recusa a empresa. Numa
                // requisição quem define isto é o middleware `tenant`, que roda
                // antes; aqui não há middleware.
                $this->contextoLegado($empresaId, (int) $alvo->grupo_id);

                $resultado->retornos[$empresaId] = $this->runtime->run($envelope, function () use ($trabalho, $empresaId, $alvo) {
                    // 1ª barreira (escopo global dos models) junto com a 2ª
                    // (RLS, aplicada pelo runtime acima).
                    $this->contexto->set($empresaId, (int) $alvo->grupo_id);

                    return $trabalho($empresaId);
                });
            } catch (\Throwable $e) {
                // A falha de uma revenda não pode parar a rotina das outras:
                // num SaaS, um cadastro quebrado de um cliente deixaria todos
                // sem GPS. Registra, segue, e o comando sai com FAILURE.
                report($e);
                $resultado->falhas[$empresaId] = $e->getMessage();
            } finally {
                // Sempre, e não só no caminho feliz: se o envelope falhar ao
                // ser aplicado, o `run` não chega ao próprio `finally`, e a
                // empresa SEGUINTE herdaria o contexto desta.
                $this->contexto->clear();
                $this->runtime->clear();
            }
        }

        return $resultado;
    }

    private function contextoLegado(int $empresaId, int $grupoId): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('SELECT set_config(?, ?, false), set_config(?, ?, false)', [
            'app.empresa_id', (string) $empresaId,
            'app.grupo_id', (string) $grupoId,
        ]);
    }

    /** @return iterable<object{empresa_id: int, tenant_account_id: int, grupo_id: int, membership_id: int|null, grant_id: int|null}> */
    private function alvos(?int $soEmpresa, bool $soAtivas): iterable
    {
        return ConexaoDeOwner::executar(fn () => DB::table('tenant_companies as tc')
            ->join('empresas as e', 'e.id', '=', 'tc.empresa_id')
            ->leftJoin('tenant_memberships as m', fn ($j) => $j
                ->on('m.tenant_account_id', '=', 'tc.tenant_account_id')
                ->where('m.membership_role', IdentidadeDeAutomacao::PAPEL)
                ->where('m.status', TenantMembership::STATUS_ACTIVE))
            ->leftJoin('tenant_company_grants as g', fn ($j) => $j
                ->on('g.tenant_membership_id', '=', 'm.id')
                ->on('g.empresa_id', '=', 'tc.empresa_id')
                ->where('g.can_read', true)->where('g.can_operate', true)
                ->whereNotNull('g.approved_at'))
            // Só o que está DENTRO da fronteira aprovada: empresa sem vínculo
            // de tenant o resolver nega numa requisição, e a automação não
            // pode ser a porta que a alcança.
            ->where('tc.status', TenantCompany::STATUS_APPROVED)
            ->when($soAtivas, fn ($q) => $q->where('e.ativo', true))
            ->when($soEmpresa !== null, fn ($q) => $q->where('tc.empresa_id', $soEmpresa))
            ->orderBy('tc.empresa_id')
            ->get(['tc.empresa_id', 'tc.tenant_account_id', 'e.grupo_id', 'm.id as membership_id', 'g.id as grant_id'])
            ->all());
    }
}
