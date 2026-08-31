<?php

namespace App\Domain\Satelite;

use App\Domain\Produto\TipoProduto;
use App\Models\Produto\Produto;
use Illuminate\Support\Collection;

/**
 * Liga o VASILHAME emprestado ao PRODUTO que o enche.
 *
 * **Por que precisa existir.** O comodato é de "Vasilha P13 Kg" (#98) e a venda
 * é de "Glp P13" (#50) — produtos distintos no cadastro. Sem ligar os dois, não
 * há como perguntar "este cliente com 13 vasilhames P13 comprou quanto de P13?",
 * que é a pergunta inteira da vigilância.
 *
 * **O campo existia e estava vazio.** `produtos.produto_retornavel_id` está no
 * schema desde a migração do legado, preenchido em 0 dos 30 produtos. Este
 * serviço infere o par e grava ali — o campo passa a ser a verdade, e a
 * inferência só existe para o primeiro preenchimento e para produto novo.
 *
 * **Como infere.** Pela CAPACIDADE, que aparece nos dois lados da relação:
 *
 *     "Vasilha P13 Kg"  → P13  ─┐
 *     "Glp P13"         → P13  ─┴→ par
 *     "Botijão P45 (45kg)" → P45
 *
 * `tipo_glp` (3=P13, 4=P20, 5=P45) confirma o lado do conteúdo quando presente.
 * Casos ambíguos NÃO são adivinhados: ficam sem vínculo e aparecem na tela de
 * conferência. Um par errado aqui vira alerta falso sobre um cliente real.
 */
class VinculoVasilhame
{
    /** tipo_glp → capacidade. Do legado; é o que distingue P13 de P45 na venda. */
    private const TIPO_GLP = [3 => 'P13', 4 => 'P20', 5 => 'P45'];

    /**
     * Capacidade declarada na descrição.
     *
     * Reconhece "P13", "P 13", "13kg" e "(13kg)" — as quatro formas que
     * aparecem no cadastro real.
     */
    public function capacidade(?string $descricao): ?string
    {
        $texto = mb_strtoupper((string) $descricao);

        if (preg_match('/\bP\s?(13|20|45|90)\b/', $texto, $m) === 1) {
            return 'P'.$m[1];
        }

        // "13KG", "45 KG", "(13kg)" — a forma usada nos produtos novos.
        if (preg_match('/\b(13|20|45|90)\s?KG\b/', $texto, $m) === 1) {
            return 'P'.$m[1];
        }

        return null;
    }

    /**
     * O produto é um VASILHAME (casco vazio, o que se empresta)?
     *
     * `vasilhame_retornavel` NÃO serve para isto: no cadastro real ele está
     * `false` em "Vasilha P13 Kg" e `true` em "Glp P13" — a semântica da coluna
     * é "esta venda gera retorno de casco", não "isto é um casco".
     */
    public function ehVasilhame(Produto $p): bool
    {
        // F3-02: o tipo DECLARADO decide. A palavra na descrição virou
        // sugestão (ver `sugerirTipo`), porque um catálogo que diga
        // "Cilindro 13kg" ou esteja em espanhol sumia da vigilância inteira —
        // e a tela não ficava vazia, ficava com menos linhas.
        return $p->tipo === TipoProduto::RECIPIENTE;
    }

    /**
     * Sugestão de tipo a partir da descrição — para a tela de conferência.
     *
     * É a heurística antiga, no lugar certo: um palpite oferecido a quem
     * decide, em vez de uma resposta usada como verdade. Devolve também a
     * evidência, porque um palpite sem o motivo não é conferível.
     *
     * @return array{tipo: TipoProduto, evidencia: string}|null
     */
    public function sugerirTipo(Produto $p): ?array
    {
        if ($p->tipo !== TipoProduto::INDEFINIDO) {
            return null; // já classificado: nada a sugerir
        }

        $texto = mb_strtoupper((string) $p->descricao);

        if ($p->tipo_glp !== null && ! str_contains($texto, 'GRANEL')) {
            return ['tipo' => TipoProduto::CONTEUDO, 'evidencia' => 'tipo_glp preenchido'];
        }

        // "Botijão P13 - RECARGA" é venda de conteúdo, não casco emprestado.
        // Sem esta exclusão ele entraria como recipiente E como compra,
        // errando os dois lados da conta.
        if (! str_contains($texto, 'RECARGA')) {
            foreach (['VASILHA', 'CASCO', 'BOTIJAO', 'BOTIJÃO', 'CILINDRO'] as $termo) {
                if (str_contains($texto, $termo)) {
                    return ['tipo' => TipoProduto::RECIPIENTE, 'evidencia' => 'descrição contém '.$termo];
                }
            }
        }

        if (! str_contains($texto, 'GRANEL')) {
            foreach (['GLP', 'RECARGA'] as $termo) {
                if (str_contains($texto, $termo)) {
                    return ['tipo' => TipoProduto::CONTEUDO, 'evidencia' => 'descrição contém '.$termo];
                }
            }
        }

        return null;
    }

    /**
     * O produto é CONTEÚDO (o gás que se vende para encher o casco)?
     *
     * GLP a GRANEL fica de fora: vai para tanque estacionário, não enche
     * botijão. Contá-lo como reabastecimento de vasilhame inflaria o giro do
     * cliente que tem os dois e esconderia exatamente o desvio procurado.
     */
    public function ehConteudo(Produto $p): bool
    {
        // F3-02: tipo declarado, mesma razão de `ehVasilhame`.
        return $p->tipo === TipoProduto::CONTEUDO;
    }

    /**
     * Produtos de conteúdo compatíveis com um vasilhame.
     *
     * Devolve LISTA e não item único de propósito: "Glp P13" aparece cinco vezes
     * no cadastro (ids 50, 617, 622, 623, 624 — duplicatas do legado), e a
     * compra do cliente pode estar em qualquer uma delas. Considerar só uma
     * subestimaria o consumo e geraria alerta falso.
     *
     * @return list<int>
     */
    public function conteudosDe(Produto $vasilhame, ?Collection $catalogo = null): array
    {
        $capacidade = $this->capacidade($vasilhame->descricao);

        if ($capacidade === null) {
            return [];
        }

        $catalogo ??= Produto::query()->where('ativo', true)->get();

        return $catalogo
            // O par tem que ser da MESMA empresa. Sem isto o "Vasilha P13" da
            // empresa 140 casava com o "Glp P13" da empresa 2 — um produto que
            // jamais aparece nos pedidos dela. O consumo daria zero e todo
            // cliente com comodato dessas empresas viraria alerta crítico falso.
            ->filter(fn (Produto $p) => (int) $p->empresa_id === (int) $vasilhame->empresa_id)
            ->filter(fn (Produto $p) => $this->ehConteudo($p) && ! $this->ehVasilhame($p))
            ->filter(function (Produto $p) use ($capacidade) {
                // `tipo_glp` é mais confiável que o texto quando existe: é campo
                // fiscal, preenchido para valer.
                $porTipo = $p->tipo_glp !== null
                    ? (self::TIPO_GLP[(int) $p->tipo_glp] ?? null)
                    : null;

                return ($porTipo ?? $this->capacidade($p->descricao)) === $capacidade;
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Propõe o vínculo de todos os vasilhames do catálogo.
     *
     * Só PROPÕE: quem grava é `aplicar()`, e a tela de conferência fica entre os
     * dois. Um par errado aqui acusaria de desvio um cliente que compra normal.
     *
     * @return list<array{vasilhame:Produto, capacidade:?string, conteudos:list<int>, principal:?Produto}>
     */
    public function propor(): array
    {
        $catalogo = Produto::query()->where('ativo', true)->get();
        $proposta = [];

        foreach ($catalogo as $p) {
            if (! $this->ehVasilhame($p)) {
                continue;
            }

            $conteudos = $this->conteudosDe($p, $catalogo);

            $proposta[] = [
                'vasilhame' => $p,
                'capacidade' => $this->capacidade($p->descricao),
                'conteudos' => $conteudos,
                // O principal é o de menor id: entre duplicatas do legado, o
                // mais antigo é o que concentra o histórico de venda.
                'principal' => $conteudos === []
                    ? null
                    : $catalogo->firstWhere('id', min($conteudos)),
            ];
        }

        return $proposta;
    }

    /**
     * Grava os vínculos inequívocos em `produtos.produto_retornavel_id`.
     *
     * Não sobrescreve vínculo já existente — o que a pessoa conferiu vale mais
     * que o que a heurística deduz.
     *
     * @return array{vinculados:int, ambiguos:list<string>}
     */
    public function aplicar(): array
    {
        $vinculados = 0;
        $ambiguos = [];

        foreach ($this->propor() as $item) {
            $vasilhame = $item['vasilhame'];

            if ($vasilhame->produto_retornavel_id !== null) {
                continue;
            }

            if ($item['principal'] === null) {
                $ambiguos[] = "#{$vasilhame->id} {$vasilhame->descricao}";

                continue;
            }

            $vasilhame->forceFill(['produto_retornavel_id' => $item['principal']->id])->save();
            $vinculados++;
        }

        return ['vinculados' => $vinculados, 'ambiguos' => $ambiguos];
    }

    /**
     * Todos os ids de produto que contam como "compra" para um vasilhame.
     *
     * Parte do vínculo gravado e expande para as duplicatas de mesma capacidade
     * — o cliente pode ter comprado em qualquer uma delas.
     *
     * @return list<int>
     */
    public function idsDeCompra(Produto $vasilhame, ?Collection $catalogo = null): array
    {
        $inferidos = $this->conteudosDe($vasilhame, $catalogo);

        if ($vasilhame->produto_retornavel_id !== null) {
            $inferidos[] = (int) $vasilhame->produto_retornavel_id;
        }

        return array_values(array_unique($inferidos));
    }
}
