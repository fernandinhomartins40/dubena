<?php

namespace App\Console\Commands;

use App\Domain\Saas\AuditoriaPlataforma;
use App\Models\Saas\BreakGlassGrant;
use App\Models\Saas\PlatformAdmin;
use Illuminate\Console\Command;

/**
 * F2-05 — segunda assinatura para acesso elevado de OPERACAO.
 *
 * Olhar um cadastro e mexer em dinheiro nao podem custar o mesmo. Concessao de
 * escopo OPERACAO nasce inerte: so passa a valer quando um PlatformAdmin
 * DIFERENTE de quem pediu aprova. Quatro olhos para o acesso que altera dado.
 */
class SaasBreakGlassAprovar extends Command
{
    protected $signature = 'saas:break-glass:aprovar
        {grant : ID da concessao}
        {--admin= : ID do PlatformAdmin aprovador (obrigatorio)}';

    protected $description = 'Aprova uma concessao break-glass de escopo OPERACAO.';

    public function handle(AuditoriaPlataforma $auditoria): int
    {
        $grant = BreakGlassGrant::query()->find((int) $this->argument('grant'));
        if ($grant === null) {
            $this->error('Concessao nao encontrada.');

            return self::FAILURE;
        }

        if ($grant->escopo !== BreakGlassGrant::ESCOPO_OPERACAO) {
            $this->error('Somente concessao de escopo OPERACAO precisa (e aceita) aprovacao.');

            return self::FAILURE;
        }

        if ($grant->aprovado_em !== null) {
            $this->error('Concessao ja aprovada.');

            return self::FAILURE;
        }

        if ($grant->revogado_em !== null || $grant->expira_em <= now()) {
            $this->error('Concessao revogada ou expirada: peca outra em vez de aprovar esta.');

            return self::FAILURE;
        }

        $admin = PlatformAdmin::query()->find((int) $this->option('admin'));
        if ($admin === null || ! $admin->ativo) {
            $this->error('Aprovador precisa ser um PlatformAdmin ativo.');

            return self::FAILURE;
        }

        // Quem pede nao aprova. Sem isto, "segunda assinatura" seria decoracao.
        if ($grant->concedido_por_platform_admin_id !== null
            && (int) $grant->concedido_por_platform_admin_id === $admin->id) {
            $this->error('O aprovador precisa ser diferente de quem concedeu.');

            return self::FAILURE;
        }

        $grant->update([
            'aprovado_em' => now(),
            'aprovado_por_platform_admin_id' => $admin->id,
        ]);

        $auditoria->registrar(
            acao: 'break_glass.aprovado',
            empresaId: (int) $grant->empresa_id,
            entidade: 'break_glass_grants',
            entidadeId: $grant->id,
            depois: ['aprovado_por' => $admin->id, 'escopo' => $grant->escopo],
        );

        $this->info("Concessao {$grant->id} aprovada por {$admin->id}; vigente ate {$grant->expira_em}.");

        return self::SUCCESS;
    }
}
