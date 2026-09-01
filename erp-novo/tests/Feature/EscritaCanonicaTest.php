<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * F3-11 — guardião contra o retorno das inferências por texto.
 *
 * A F3 gastou trabalho tirando decisões de negócio de dentro de palavras em
 * português: a situação "saiu para entrega" (F3-04A), o que um produto é e qual
 * a capacidade dele (F3-02).
 *
 * Nada disso se sustenta sozinho. A próxima pessoa que precisar distinguir dois
 * conceitos que o modelo não separa vai fazer exatamente o que foi feito antes —
 * `str_contains($p->descricao, 'ALGUMA_COISA')` — porque funciona na hora, passa
 * no teste da revenda que está na frente, e o custo só aparece na segunda
 * revenda, meses depois, como uma tela com menos linhas do que devia.
 *
 * Este teste varre o código de decisão e falha quando uma inferência dessas
 * reaparece. É deliberadamente chato: a saída não é contornar a lista, é
 * declarar o conceito no cadastro — que é o que a F3 inteira propõe.
 */
class EscritaCanonicaTest extends TestCase
{
    /**
     * Termos de domínio em português que não podem governar decisão.
     *
     * @var list<string>
     */
    private const TERMOS_PROIBIDOS = [
        'VASILHA', 'CASCO', 'BOTIJAO', 'BOTIJÃO', 'GRANEL', 'RECARGA',
        'SAIU', 'ROTA DE', 'CAMINHO',
    ];

    /**
     * Onde a regra vale: o código que DECIDE.
     *
     * @var list<string>
     */
    private const PASTAS = ['app/Domain', 'app/Http/Controllers', 'app/Jobs'];

    /**
     * Arquivos com licença explícita, e o motivo de cada um.
     *
     * A lista é curta de propósito. Cada entrada é uma dívida declarada, não
     * uma isenção genérica — e entrar nela deve custar uma conversa.
     *
     * @var array<string, string>
     */
    private const PERMITIDOS = [
        // A regex vive aqui como SUGESTÃO para a tela de conferência, com a
        // evidência junto. É o lugar certo de um palpite (F3-02).
        'VinculoVasilhame.php' => 'sugestão de tipo/capacidade, conferida por humano',
    ];

    /**
     * Nenhuma decisão nova pode nascer de uma palavra em português.
     */
    public function test_nenhuma_decisao_nova_infere_conceito_pela_descricao(): void
    {
        $achados = [];

        foreach ($this->arquivosPhp() as $arquivo) {
            if (isset(self::PERMITIDOS[basename($arquivo)])) {
                continue;
            }

            foreach ($this->linhasDeCodigo($arquivo) as $numero => $linha) {
                foreach (self::TERMOS_PROIBIDOS as $termo) {
                    if ($this->inferePorTexto($linha, $termo)) {
                        $relativo = str_replace(base_path().DIRECTORY_SEPARATOR, '', $arquivo);
                        $achados[] = "{$relativo}:{$numero} decide por \"{$termo}\" no texto";
                    }
                }
            }
        }

        $this->assertSame([], $achados, implode("\n", array_merge(
            ['Inferência por palavra em português no código de decisão:'],
            $achados,
            [
                '',
                'A saída não é contornar esta lista: é declarar o conceito no cadastro,',
                'como `PedidoSituacao::papel` (F3-04A) e `Produto::tipo` (F3-02).',
                'Se for mesmo sugestão para conferência humana, o lugar é um método',
                '`sugerir*` que devolva a evidência junto.',
            ],
        )));
    }

    /**
     * F3-10 — nome de revenda ou cidade dela não vira literal em código.
     *
     * "Dubena" e "Guarapuava" são a primeira cliente e a cidade dela. Num
     * produto para N revendas, qualquer um dos dois dentro de uma string
     * executável é uma regra da Dubena aplicada a todo mundo — e o caso que
     * motivou isto era exatamente assim: o `User-Agent` enviado ao Overpass
     * dizia `ERP-Dubena`, então toda revenda do SaaS se identificaria como a
     * primeira perante um serviço externo.
     *
     * Comentário citando a origem de um número ("medido na base de Guarapuava")
     * é documentação valiosa, e continua permitido — o que se proíbe é o
     * literal governar comportamento.
     */
    public function test_nome_de_revenda_nao_vira_literal_em_codigo(): void
    {
        $achados = [];
        $varridos = 0;

        foreach ($this->arquivosPhp() as $arquivo) {
            $varridos++;

            foreach ($this->linhasDeCodigo($arquivo) as $numero => $linha) {
                // `GASEMCASA` entrou junto (F8): é o nome da primeira revenda
                // sem espaço, a forma que os campos de largura fixa usam — foi
                // assim que ele apareceu no payload PIX. Um guardião que busca
                // só a grafia bonita não pega a que de fato vaza.
                foreach (['Dubena', 'Guarapuava', 'GASEMCASA', 'Gas em Casa'] as $termo) {
                    // Só dentro de string: um nome de classe ou variável que
                    // contenha o termo é outro assunto (e não existe hoje).
                    if (preg_match("/['\"][^'\"]*{$termo}[^'\"]*['\"]/i", $linha) === 1) {
                        $relativo = str_replace(base_path().DIRECTORY_SEPARATOR, '', $arquivo);
                        $achados[] = "{$relativo}:{$numero} tem \"{$termo}\" numa string";
                    }
                }
            }
        }

        // A varredura tem de ter varrido algo. Sem isto, uma pasta renomeada
        // faria a lista vir vazia e o teste passaria protegendo ZERO arquivo —
        // que é como um guardião deste repositório passou meses aprovando nada.
        $this->assertGreaterThan(300, $varridos, 'a varredura precisa ter alcançado o código');

        $this->assertSame([], $achados, implode("\n", array_merge(
            ['Nome da primeira revenda (ou da cidade dela) dentro de código:'],
            $achados,
            ['', 'Use configuração de plataforma (app.name) ou do tenant.'],
        )));
    }

    /**
     * A licença é nominal: arquivo permitido tem de existir, senão a lista
     * envelhece protegendo um arquivo que já foi renomeado.
     */
    public function test_a_lista_de_permitidos_nao_envelhece(): void
    {
        $nomes = array_map('basename', $this->arquivosPhp());

        foreach (array_keys(self::PERMITIDOS) as $permitido) {
            $this->assertContains(
                $permitido,
                $nomes,
                "{$permitido} está na lista de permitidos mas não existe mais — remova a entrada.",
            );
        }
    }

    /**
     * A linha decide algo com base no texto?
     *
     * Comparar strings não é o problema; usar o resultado como condição é.
     * Por isso a checagem exige uma função de busca em texto na mesma linha, e
     * não a simples presença da palavra — que aparece legitimamente em rótulo,
     * mensagem de erro e nome de variável.
     */
    private function inferePorTexto(string $linha, string $termo): bool
    {
        if (stripos($linha, $termo) === false) {
            return false;
        }

        foreach (['str_contains', 'stripos', 'strpos', 'preg_match', 'LIKE', 'ilike'] as $funcao) {
            if (stripos($linha, $funcao) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Linhas de código, sem comentários.
     *
     * Comentário citando o padrão antigo é documentação — inclusive a que
     * explica por que ele foi removido. Contá-lo faria este teste proibir a
     * própria explicação.
     *
     * @return array<int, string>
     */
    private function linhasDeCodigo(string $arquivo): array
    {
        $linhas = [];

        foreach (file($arquivo) ?: [] as $i => $linha) {
            $limpa = trim($linha);

            if ($limpa === '' || str_starts_with($limpa, '*')
                || str_starts_with($limpa, '//') || str_starts_with($limpa, '/*')) {
                continue;
            }

            $linhas[$i + 1] = $linha;
        }

        return $linhas;
    }

    /** @return list<string> */
    private function arquivosPhp(): array
    {
        $arquivos = [];

        foreach (self::PASTAS as $pasta) {
            $caminho = base_path($pasta);
            if (! is_dir($caminho)) {
                continue;
            }

            $iterador = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($caminho));
            foreach ($iterador as $arquivo) {
                if ($arquivo->isFile() && $arquivo->getExtension() === 'php') {
                    $arquivos[] = $arquivo->getPathname();
                }
            }
        }

        return $arquivos;
    }
}
