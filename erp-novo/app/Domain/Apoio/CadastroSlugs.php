<?php

namespace App\Domain\Apoio;

/**
 * Fonte ÚNICA da verdade para os slugs dos cadastros de apoio / lookups (F00.2).
 *
 * A auditoria apontou divergência de slug entre `LookupController` (AsyncSelect da
 * SPA, rota /lookups/{tipo}) e `CadastroApoioRegistry` (CRUD, rota /cadastros/{tipo})
 * para a MESMA entidade — ex.: `tipo-pessoa` vs `tipos-pessoa`. Isso quebra o
 * contrato: a tela edita num slug e busca noutro.
 *
 * Aqui definimos o slug CANÔNICO de cada entidade e os ALIASES históricos aceitos.
 * Ambos os controllers normalizam o slug recebido por `canonico()` antes de usar,
 * de modo que `/lookups/tipo-pessoa` e `/cadastros/tipos-pessoa` passam a apontar
 * para a MESMA entidade — sem quebrar chamadas já existentes da SPA.
 */
final class CadastroSlugs
{
    /**
     * slug canônico => lista de aliases aceitos (compatibilidade retroativa).
     *
     * Regra: o canônico é o slug "de cadastro" (plural), por ser o que cria/edita.
     *
     * @var array<string, list<string>>
     */
    private const ALIASES = [
        'segmentos' => [],
        'tipos-pessoa' => ['tipo-pessoa'],
        'telefone-tipos' => ['telefonetipos'],
        'contato-tipos' => [],
        'contato-situacoes' => [],
        'bancos' => [],
        'tipos-movimento' => ['tipos-movimento', 'contamovimentotipos'],
        'agencias' => [],
        'transportadoras' => [],
        'feriados' => [],
        'profissoes' => [],
        'estados-civis' => ['estadocivil'],
        'cargos' => [],
        'parentescos' => [],
        'tipos-exame' => ['tipoexame'],
        'condicoes-pagamento' => ['condicoes-pagamento'],
        'pedido-operacoes' => [],
        'pedido-situacoes' => [],
        'produto-classes' => [],
        'unidades' => ['unidades-medida'],
        'combustiveis' => ['tipo-combustiveis'],
        'veiculo-tipos' => [],
        'regioes' => [],
        'cidades' => [],
        'bairros' => [],
        'ruas' => [],
        'estados' => [],
        'setores' => [],
        'contas' => [],
        'planos-conta' => [],
        'centros-custo' => [],
        'clientes' => [],
        'clientes-fornecedores' => [],
        'colaboradores' => [],
        'produtos' => [],
        'produtos-vasilhame' => [],
    ];

    /**
     * Resolve qualquer slug (canônico ou alias) para o slug canônico.
     * Slug desconhecido é devolvido como veio (deixa o chamador decidir 404/vazio).
     */
    public static function canonico(string $slug): string
    {
        if (isset(self::ALIASES[$slug])) {
            return $slug;
        }

        foreach (self::ALIASES as $canonico => $aliases) {
            if (in_array($slug, $aliases, true)) {
                return $canonico;
            }
        }

        return $slug;
    }

    /** @return list<string> Todos os slugs canônicos conhecidos. */
    public static function todos(): array
    {
        return array_keys(self::ALIASES);
    }
}
