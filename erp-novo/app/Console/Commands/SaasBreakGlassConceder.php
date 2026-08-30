<?php

namespace App\Console\Commands;

use App\Domain\Saas\AuditoriaPlataforma;
use App\Domain\Seguranca\VerificadorDoisFatores;
use App\Models\Empresa;
use App\Models\Saas\BreakGlassGrant;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * F2-05 — concede acesso elevado temporario, com motivo e prazo.
 *
 * Substitui a pratica de ligar `users.support` e esquecer: aqui o acesso tem
 * alvo, justificativa, validade e trilha. Fica no console de proposito — e ato
 * de plataforma, nao funcionalidade da aplicacao.
 */
class SaasBreakGlassConceder extends Command
{
    protected $signature = 'saas:break-glass:conceder
        {user : ID ou e-mail do usuario de suporte}
        {empresa : ID da empresa alvo}
        {--motivo= : Justificativa (obrigatoria)}
        {--ticket= : Referencia do chamado}
        {--minutos=60 : Validade em minutos}
        {--otp= : Codigo TOTP do usuario elevado (obrigatorio para conceder)}
        {--escopo=LEITURA : LEITURA ou OPERACAO; OPERACAO exige aprovacao posterior}
        {--revogar : Revoga as concessoes vigentes desse usuario nessa empresa}';

    protected $description = 'Concede (ou revoga) acesso break-glass temporario a uma empresa.';

    public function handle(AuditoriaPlataforma $auditoria, VerificadorDoisFatores $verificador): int
    {
        $user = User::query()
            ->when(is_numeric($this->argument('user')),
                fn ($q) => $q->whereKey((int) $this->argument('user')),
                fn ($q) => $q->where('email', $this->argument('user')))
            ->first();
        if ($user === null) {
            $this->error('Usuario nao encontrado.');

            return self::FAILURE;
        }

        $empresaId = (int) $this->argument('empresa');
        if (! Empresa::withoutGlobalScopes()->whereKey($empresaId)->exists()) {
            $this->error("Empresa {$empresaId} nao encontrada.");

            return self::FAILURE;
        }

        if ($this->option('revogar')) {
            return $this->revogar($auditoria, $user, $empresaId);
        }

        // Sem `support` nao ha o que elevar: break-glass amplia quem ja pertence
        // ao suporte, nao transforma um usuario comum em administrador.
        if (! $user->support) {
            $this->error('Usuario nao tem o flag de suporte; break-glass so eleva quem ja o possui.');

            return self::FAILURE;
        }

        $motivo = trim((string) $this->option('motivo'));
        if ($motivo === '') {
            $this->error('O motivo e obrigatorio: acesso sem justificativa e o que esta sendo eliminado.');

            return self::FAILURE;
        }

        $escopo = strtoupper((string) $this->option('escopo'));
        if (! in_array($escopo, [BreakGlassGrant::ESCOPO_LEITURA, BreakGlassGrant::ESCOPO_OPERACAO], true)) {
            $this->error('Escopo invalido: use LEITURA ou OPERACAO.');

            return self::FAILURE;
        }

        // 2FA no ato: quem pede prova que e ele. Sem isto, bastava acesso ao
        // console para elevar qualquer usuario de suporte.
        $otp = trim((string) $this->option('otp'));
        if ($otp === '') {
            $this->error('O codigo 2FA (--otp) e obrigatorio para conceder acesso elevado.');

            return self::FAILURE;
        }
        if (! $verificador->verificar($user->twoFactor, $otp)) {
            $this->error('Codigo 2FA invalido, ja utilizado ou 2FA nao configurado para este usuario.');

            return self::FAILURE;
        }

        $minutos = max(1, (int) $this->option('minutos'));
        $grant = BreakGlassGrant::create([
            'user_id' => $user->id,
            'empresa_id' => $empresaId,
            'escopo' => $escopo,
            'motivo' => $motivo,
            'ticket_ref' => $this->option('ticket') ?: null,
            'twofa_verificado_em' => now(),
            'inicia_em' => now(),
            'expira_em' => now()->addMinutes($minutos),
        ]);

        $auditoria->registrar(
            acao: 'break_glass.concedido',
            empresaId: $empresaId,
            entidade: 'break_glass_grants',
            entidadeId: $grant->id,
            depois: [
                'user_id' => $user->id, 'motivo' => $motivo, 'escopo' => $escopo,
                'expira_em' => $grant->expira_em->toIso8601String(),
            ],
        );

        $this->info("Acesso {$escopo} concedido a {$user->email} na empresa {$empresaId} ate {$grant->expira_em}.");

        if ($escopo === BreakGlassGrant::ESCOPO_OPERACAO) {
            $this->warn(
                "PENDENTE DE APROVACAO: escopo OPERACAO so passa a valer apos um segundo administrador rodar\n"
                ."  php artisan saas:break-glass:aprovar {$grant->id} --admin=<id>"
            );
        }

        return self::SUCCESS;
    }

    private function revogar(AuditoriaPlataforma $auditoria, User $user, int $empresaId): int
    {
        $afetadas = BreakGlassGrant::query()
            ->where('user_id', $user->id)
            ->where('empresa_id', $empresaId)
            ->whereNull('revogado_em')
            ->update([
                'revogado_em' => now(),
                'revogado_motivo' => trim((string) $this->option('motivo')) ?: 'revogacao manual',
            ]);

        $auditoria->registrar(
            acao: 'break_glass.revogado',
            empresaId: $empresaId,
            entidade: 'users',
            entidadeId: $user->id,
            depois: ['concessoes_revogadas' => $afetadas],
        );

        $this->info("{$afetadas} concessao(oes) revogada(s).");

        return self::SUCCESS;
    }
}
