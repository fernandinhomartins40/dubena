<?php

namespace App\Domain\Cobranca\Cnab;

use App\Models\EmpresaConfig;
use Illuminate\Validation\ValidationException;

/**
 * Dados da conta de cobrança do BENEFICIÁRIO (empresa), por banco (F08).
 *
 * Persistidos no JSON `empresa_configs.dados['cobranca'][<banco>]` — sem migration,
 * por empresa (tenant). Credenciais sensíveis (não há senha aqui, mas convênio/
 * cedente são por-empresa). Carregado sob demanda pelo driver real.
 */
final class ContaCobranca
{
    public function __construct(
        public readonly int $banco,
        public readonly string $agencia,
        public readonly string $conta,
        public readonly string $carteira,
        public readonly string $convenio,      // código do cedente/convênio (Caixa: 6; Itaú: usa conta)
        public readonly string $cedenteNome,
        public readonly string $cedenteDocumento,
        public readonly string $operacao = '', // Caixa: posto/operação (opcional)
    ) {}

    /**
     * Carrega a conta de cobrança da empresa para um banco. Lança se não configurada.
     */
    public static function daEmpresa(int $empresaId, int $banco): self
    {
        $config = EmpresaConfig::query()->where('empresa_id', $empresaId)->first();
        $dados = $config?->dados ?? [];
        $c = $dados['cobranca'][(string) $banco] ?? null;

        if (! is_array($c)) {
            throw ValidationException::withMessages([
                'cobranca' => "Conta de cobrança do banco {$banco} não configurada para esta empresa.",
            ]);
        }

        foreach (['agencia', 'conta', 'carteira', 'convenio', 'cedente_nome', 'cedente_documento'] as $req) {
            if (empty($c[$req])) {
                throw ValidationException::withMessages([
                    'cobranca' => "Campo '{$req}' da conta de cobrança ausente (banco {$banco}).",
                ]);
            }
        }

        return new self(
            banco: $banco,
            agencia: (string) $c['agencia'],
            conta: (string) $c['conta'],
            carteira: (string) $c['carteira'],
            convenio: (string) $c['convenio'],
            cedenteNome: (string) $c['cedente_nome'],
            cedenteDocumento: preg_replace('/\D/', '', (string) $c['cedente_documento']),
            operacao: (string) ($c['operacao'] ?? ''),
        );
    }
}
