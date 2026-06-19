<?php

namespace App\ApiAdmin\Services;

use App\Produto;
use App\Estoquesetor;
use App\Produtoorigem;
use Illuminate\Support\Facades\DB;

/**
 * F1 (SPA React) — regras de negócio do Produto, extraídas do
 * ProdutoController@dadosExtras legado (auditado linha-a-linha — ver
 * docs/01-vigente/IMPL_PRODUTO.md §3). PRESERVA as regras críticas:
 *   - GLP: pgni+pgnn+pglp soma 100 ou 0;
 *   - Origens do combustível: soma dos percentuais = 100%;
 *   - não inativar produto com saldo em estoque.
 *
 * Diferença do legado: a API recebe números limpos (o React não manda
 * máscara BR "R$ ...,.."), então NÃO reaplicamos insertNumeroDecimalOracle —
 * apenas normalizamos para float. As regras de negócio são idênticas.
 */
class ProdutoService
{
    /** Colunas numéricas NOT NULL que o form pode omitir → default 0 (paridade c/ emptyToNull+casts legado). */
    private const NUM_DEFAULT_ZERO = [
        'customedio', 'custofrete', 'precovenda', 'precovendaminimo', 'precogasdopovo',
        'pesoliquido', 'pesobruto', 'nfealiqipi', 'nfebcipi', 'nfeqbcprod', 'nfevaliqprod', 'nfevcide',
    ];

    /**
     * Percentuais de GLP. ATENÇÃO: no Postgres as colunas foram criadas com aspas
     * em camelCase (pGNi/pGNn/pGLP) — referência sem aspas (pgni) NÃO casa. A API
     * aceita as chaves minúsculas do front e grava nas colunas reais.
     */
    private const MAPA_GLP = ['pgni' => 'pGNi', 'pgnn' => 'pGNn', 'pglp' => 'pGLP'];

    /**
     * Monta o payload pronto para Produto::create/update aplicando as regras.
     *
     * @throws \InvalidArgumentException quando uma regra de negócio é violada.
     */
    public function prepararDados(array $data, int $grupoId, int $empresaId): array
    {
        // Normaliza numéricos (default 0 onde NOT NULL).
        foreach (self::NUM_DEFAULT_ZERO as $campo) {
            $data[$campo] = $this->numero($data[$campo] ?? 0);
        }

        // GLP: lê as chaves minúsculas do front, grava nas colunas reais (camelCase).
        $glp = [];
        foreach (self::MAPA_GLP as $entrada => $coluna) {
            $glp[$coluna] = $this->numero($data[$entrada] ?? 0);
            unset($data[$entrada]);          // remove a chave minúscula (coluna inexistente)
            $data[$coluna] = $glp[$coluna];  // usa a coluna real
        }

        // NF-e: se não permite, zera o flag (igual legado).
        $data['nfepermite'] = ! empty($data['nfepermite']) ? 1 : 0;

        // REGRA GLP — soma dos percentuais precisa ser 100 ou 0.
        $somaGlp = $glp['pGNi'] + $glp['pGNn'] + $glp['pGLP'];
        if ($somaGlp > 0 && $somaGlp < 100) {
            throw new \InvalidArgumentException('A soma dos percentuais de GLP precisa ser 100 ou 0.');
        }

        $data['grupo_id'] = $grupoId;
        $data['empresa_id'] = $empresaId;
        $data['produtoretornavel_id'] = $data['produtoretornavel_id'] ?? null;
        $data['ativo'] = isset($data['ativo']) ? (int) (! empty($data['ativo'])) : 0;
        $data['enviaappnf'] = isset($data['enviaappnf']) ? (int) (! empty($data['enviaappnf'])) : 0;
        $data['vasilhameretornavel'] = ! empty($data['vasilhameretornavel']) ? 1 : 0;

        return $data;
    }

    /**
     * Valida e normaliza a lista de origens do combustível.
     * Aceita o formato do app novo: [['indimport'=>..,'cuforig'=>..,'porig'=>..], ...].
     *
     * @return array<int,array{indimport:int,cuforig:int,porig:float}>
     * @throws \InvalidArgumentException se a soma dos percentuais ≠ 100.
     */
    public function normalizarOrigens(array $origens): array
    {
        if (empty($origens)) {
            return [];
        }

        $total = 0.0;
        $norm = [];
        foreach ($origens as $o) {
            $porig = $this->numero($o['porig'] ?? 0);
            $total += $porig;
            $norm[] = [
                'indimport' => (int) ($o['indimport'] ?? 0),
                'cuforig'   => (int) ($o['cuforig'] ?? 0),
                'porig'     => $porig,
            ];
        }

        // Compara com tolerância (decimais).
        if (abs($total - 100.0) > 0.001) {
            throw new \InvalidArgumentException('A soma dos percentuais de Origem do Combustível precisa dar 100 %.');
        }

        return $norm;
    }

    /** Persiste as origens (substitui as existentes). */
    public function salvarOrigens(Produto $produto, array $origensNorm): void
    {
        $produto->origens()->delete();
        if (empty($origensNorm)) {
            return;
        }
        $rows = array_map(fn ($o) => array_merge($o, [
            'produto_id' => $produto->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]), $origensNorm);
        Produtoorigem::insert($rows);
    }

    /**
     * REGRA: não inativar produto com saldo em estoque (ProdutoController:146-152).
     *
     * @throws \InvalidArgumentException se houver saldo ≠ 0 em algum setor.
     */
    public function garantirPodeInativar(int $produtoId): void
    {
        $temSaldo = Estoquesetor::where('produto_id', $produtoId)
            ->where('quantidade', '<>', 0)
            ->exists();
        if ($temSaldo) {
            throw new \InvalidArgumentException('Este produto não pode ser inativado pois está no estoque de algum setor!');
        }
    }

    /** Aceita número (12.5) ou string BR ("12,50" / "1.234,56") → float. */
    private function numero($valor): float
    {
        if (is_numeric($valor)) {
            return (float) $valor;
        }
        if (! is_string($valor) || $valor === '') {
            return 0.0;
        }
        $v = str_replace(['R$', ' ', '%', 'Kg'], '', $valor);
        // Formato BR: remove separador de milhar (.) e troca vírgula decimal por ponto.
        if (str_contains($v, ',')) {
            $v = str_replace('.', '', $v);
            $v = str_replace(',', '.', $v);
        }
        return (float) $v;
    }
}
