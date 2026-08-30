<?php

namespace App\Domain\Acesso;

use App\Domain\Saas\AuditoriaPlataforma;
use App\Models\Saas\BreakGlassGrant;
use App\Models\User;

/**
 * F2-05 — ponto unico que decide se um acesso elevado esta autorizado AGORA.
 *
 * Antes disto, `users.support` respondia "sim" em quatro camadas independentes,
 * para sempre e sem trilha. Aqui a resposta depende de uma concessao vigente
 * para aquele usuario NAQUELA empresa.
 *
 * O flag `support` continua sendo o mecanismo legitimo de suporte — ele diz
 * QUEM pode pedir break-glass. O que ele deixou de ser e autorizacao por si.
 *
 * Enquanto o enforcement SaaS estiver desligado o comportamento legado e
 * preservado, para nao derrubar a operacao atual antes do cutover.
 */
class BreakGlass
{
    /** @var array<string, bool> pares ja registrados na trilha neste ciclo */
    private array $cache = [];

    public function __construct(private AuditoriaPlataforma $auditoria) {}

    /**
     * O usuario tem acesso elevado nesta empresa neste instante?
     *
     * Sem `support` a resposta e sempre nao: break-glass eleva quem ja e do
     * suporte, nao concede poder a qualquer um com uma linha na tabela.
     */
    public function ativo(User $user, ?int $empresaId = null): bool
    {
        if (! $user->support) {
            return false;
        }

        // Modo legado: o flag ainda vale por si. Isto sai quando o enforcement
        // for ligado em definitivo, e e o unico caminho que preserva o bypass.
        if (! config('saas_transformation.enforcement.tenant_envelope')) {
            return true;
        }

        $empresaId ??= $user->empresa_id;
        if ($empresaId === null) {
            return false;
        }

        $agora = now();
        $vigente = BreakGlassGrant::query()
            ->where('user_id', $user->id)
            ->where('empresa_id', $empresaId)
            ->whereNull('revogado_em')
            ->where('inicia_em', '<=', $agora)
            ->where('expira_em', '>', $agora)
            // Espelha `BreakGlassGrant::vigente()`: 2FA conferido no ato, e
            // aprovacao de um segundo administrador quando o escopo e OPERACAO.
            // Divergir daquele metodo faria a decisao depender de por onde se
            // pergunta, que e o tipo de brecha que esta fase existe para fechar.
            ->whereNotNull('twofa_verificado_em')
            ->where(fn ($q) => $q
                ->where('escopo', '!=', BreakGlassGrant::ESCOPO_OPERACAO)
                ->orWhereNotNull('aprovado_em'))
            ->exists();

        // A CONSULTA nao e memorizada de proposito: memorizar a decisao faria
        // uma revogacao so valer no request seguinte, e revogar imediatamente e
        // metade do valor do break-glass. O que se memoriza e apenas o REGISTRO
        // na trilha, para que as quatro camadas de autorizacao nao gerem quatro
        // linhas identicas por requisicao.
        $chave = $user->id.':'.$empresaId;
        if ($vigente && ! array_key_exists($chave, $this->cache)) {
            $this->cache[$chave] = true;
            $this->auditoria->registrar(
                acao: 'break_glass.usado',
                empresaId: $empresaId,
                entidade: 'users',
                entidadeId: $user->id,
            );
        }

        return $vigente;
    }
}
