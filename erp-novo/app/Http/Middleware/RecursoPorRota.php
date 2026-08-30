<?php

namespace App\Http\Middleware;

use App\Domain\Saas\LicencaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * F2-03 — enforcement de licença por PREFIXO de rota.
 *
 * O middleware `recurso:chave` já existia e estava correto, mas exigia ser
 * escrito rota a rota — e nenhuma das 604 rotas o usava. A licença existia e não
 * decidia nada.
 *
 * Aqui o recurso é resolvido pelo caminho. Isso vale mais que declarar em cada
 * rota por dois motivos: não exige reescrever `routes/api.php` inteiro, e uma
 * rota NOVA do domínio passa a ser coberta sozinha — o esquecimento, que é o
 * modo de falha real, deixa de existir.
 *
 * Rota fora do mapa não é barrada: o núcleo do ERP (cliente, produto, pedido,
 * estoque, financeiro) não é add-on, é o que a revenda contrata por definição.
 * O mapa cobre o que diferencia plano, não o que todo mundo precisa.
 *
 * Governado por `SAAS_ENFORCE_LICENCA`. Desligado, é passagem livre — a operação
 * atual não pode cair porque a grade de planos mudou.
 */
class RecursoPorRota
{
    /**
     * Prefixo do caminho (depois de `api/admin/`) => chave do recurso.
     *
     * A ordem importa: o primeiro prefixo que casar decide.
     *
     * @var array<string, string>
     */
    private const MAPA = [
        'monitora/' => 'monitora',
        'pos-vendas' => 'crm',
        'promocoes' => 'crm',
        'sorteios' => 'crm',
        'checklists' => 'crm',
        'metas' => 'crm',
        'mala-direta' => 'crm',
        'veiculos' => 'frota',
        'abastecimentos' => 'frota',
        'manutencoes' => 'frota',
        'boletos' => 'cobranca',
        'remessas' => 'cobranca',
        'retornos' => 'cobranca',
        'pix' => 'cobranca',
        'notas' => 'nfce',
        'fiscal/' => 'nfce',
        'relatorios/' => 'relatorios_avancados',
    ];

    public function __construct(private LicencaService $licenca) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('saas_transformation.enforcement.licenca')) {
            return $next($request);
        }

        $chave = self::recursoDaRota($request->path());
        if ($chave === null) {
            return $next($request);
        }

        abort_unless(
            $this->licenca->recursoHabilitado($chave),
            402,
            'Recurso não disponível no plano contratado.',
        );

        return $next($request);
    }

    /** Recurso exigido por um caminho, ou null se a rota não é de módulo opcional. */
    public static function recursoDaRota(string $caminho): ?string
    {
        $caminho = ltrim($caminho, '/');
        if (! str_starts_with($caminho, 'api/admin/')) {
            return null;
        }

        $resto = substr($caminho, strlen('api/admin/'));
        foreach (self::MAPA as $prefixo => $recurso) {
            if (str_starts_with($resto, $prefixo)) {
                return $recurso;
            }
        }

        return null;
    }
}
