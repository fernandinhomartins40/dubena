<?php

namespace App\Domain\Geografico;

use App\Domain\Geografico\Contracts\FonteLogradouros;
use App\Domain\Identidade\NormalizadorTexto;
use App\Models\Geografico\Bairro;
use App\Models\Geografico\Cidade;
use App\Models\Geografico\Rua;
use Illuminate\Support\Facades\DB;

/**
 * Importa os logradouros de uma cidade a partir da base de CEP.
 *
 * O ALGORITMO e o porquê dele:
 *
 * A fonte busca por trecho do nome e devolve no máximo 50 resultados, truncados
 * SEM AVISO. Uma varredura ingênua ("busque 'rua', 'avenida'…") perderia ruas e
 * não teria como saber. Então: quando uma consulta bate o teto, a resposta está
 * incompleta por definição e o termo é REFINADO (termo + cada letra), descendo
 * até o resultado caber abaixo do teto.
 *
 * As sementes são trigramas frequentes em nomes de logradouro pt-BR, não o
 * alfabeto inteiro: o mínimo da API é 3 caracteres e varrer as 17.576
 * combinações seria absurdo para um retorno que estas ~90 sementes já cobrem.
 *
 * O que NÃO é feito: apagar. A importação só cria e completa. Rua já cadastrada
 * mantém o id — há 44.338 clientes apontando para `rua_id`, e trocar esses ids
 * quebraria o endereço de toda a base.
 */
class ImportarLogradouros
{
    /**
     * Sementes de busca: trigramas iniciais e radicais frequentes em nomes de
     * logradouro no Brasil. Cobrem tipo (rua/avenida/travessa), patronímicos e
     * os temas recorrentes (santos, estados, datas).
     *
     * @var list<string>
     */
    private const SEMENTES = [
        'rua', 'ave', 'tra', 'est', 'rod', 'ala', 'pra', 'lar', 'vil', 'jar',
        'san', 'sao', 'jos', 'joa', 'mar', 'ant', 'fra', 'man', 'ped', 'pau',
        'car', 'lui', 'alb', 'alf', 'ana', 'and', 'ang', 'ari', 'arn', 'aug',
        'bar', 'bel', 'ben', 'bra', 'cam', 'can', 'cap', 'cas', 'cat', 'cel',
        'cid', 'cla', 'col', 'con', 'cor', 'cos', 'cru', 'cun', 'dom', 'dou',
        'duq', 'edu', 'esp', 'fer', 'fil', 'flo', 'fre', 'gen', 'ger', 'gon',
        'gua', 'gui', 'hen', 'her', 'ind', 'ipi', 'ita', 'jac', 'jer', 'jul',
        'lag', 'leo', 'lim', 'lop', 'mac', 'mad', 'mag', 'mai', 'mat', 'mig',
        'min', 'mon', 'nos', 'nov', 'oli', 'pal', 'par', 'pas', 'per', 'pin',
        'pir', 'por', 'pre', 'pri', 'rib', 'rio', 'roc', 'rod', 'sal', 'sar',
        'sen', 'ser', 'sil', 'sou', 'tei', 'tir', 'tor', 'tre', 'uru', 'val',
        'vas', 'ver', 'vic', 'vit', 'xav', 'set', 'qui', 'dez', 'nat', 'ind',
    ];

    /**
     * Sufixos do refino.
     *
     * Inclui ESPAÇO e DÍGITOS, não só letras: nomes de logradouro terminam em
     * numeral com frequência ("Rua Sete de Setembro 2", "Travessa B 12"), e um
     * refino só alfabético nunca alcança essas variações — a busca continuaria
     * batendo o teto e perdendo ruas.
     */
    private const SUFIXOS = 'abcdefghijklmnopqrstuvwxyz0123456789 ';

    /**
     * Profundidade máxima do refino (semente de 3 + 5 = termos de até 8 caracteres).
     *
     * Não é folga arbitrária: o que distingue logradouros de nome parecido
     * costuma vir NO FIM ("Rua Sete de Setembro 2"), então parar cedo deixa o
     * ramo no teto e perde ruas. A recursão só desce quando o teto foi batido,
     * então a profundidade extra não custa nada nos ramos que já couberam.
     */
    private const PROFUNDIDADE_MAX = 5;

    /**
     * Teto de consultas por cidade.
     *
     * Trava de segurança, não meta: o refino só desce onde o teto foi batido,
     * e na prática uma cidade sai em algumas centenas de consultas. Mas a
     * recursão é exponencial no pior caso, e sem este limite uma capital com
     * muitos ramos cheios manteria o job rodando por horas contra um serviço
     * público de terceiros.
     */
    private const CONSULTAS_MAX = 3000;

    public function __construct(private FonteLogradouros $fonte) {}

    /**
     * Varre a fonte e devolve os logradouros únicos da cidade.
     *
     * Separado da gravação para o comando poder mostrar o que viria ANTES de
     * escrever, e para o teste exercitar o algoritmo sem tocar no banco.
     *
     * @return array{logradouros: array<string, array{logradouro:string, bairro:string, cep:string}>, consultas:int, truncados:int}
     */
    public function varrer(string $uf, string $nomeCidade, ?callable $progresso = null): array
    {
        $encontrados = [];
        $consultas = 0;
        $truncados = 0;

        $visitar = function (string $termo, int $profundidade) use (
            &$visitar, &$encontrados, &$consultas, &$truncados, $uf, $nomeCidade, $progresso
        ): void {
            if ($consultas >= self::CONSULTAS_MAX) {
                // Bateu a trava: conta como truncado para a importação ser
                // reportada como possivelmente INCOMPLETA, em vez de "pronta".
                $truncados++;

                return;
            }

            $itens = $this->fonte->buscar($uf, $nomeCidade, $termo);
            $consultas++;

            foreach ($itens as $item) {
                // A chave é logradouro+bairro, não o CEP: uma rua longa tem
                // dezenas de CEPs (um por trecho) e todos são a MESMA rua.
                $chave = NormalizadorTexto::basico($item['logradouro']).'|'.NormalizadorTexto::basico($item['bairro']);
                if ($chave !== '|' && ! isset($encontrados[$chave])) {
                    $encontrados[$chave] = $item;
                }
            }

            if ($progresso !== null) {
                $progresso($termo, count($itens), count($encontrados));
            }

            // Teto batido = resposta truncada em silêncio. Refina ou perde ruas.
            if (count($itens) >= $this->fonte->teto()) {
                if ($profundidade >= self::PROFUNDIDADE_MAX) {
                    // Fim do refino com o teto ainda batendo: registra que ESTA
                    // importação pode estar incompleta, em vez de fingir sucesso.
                    $truncados++;

                    return;
                }

                foreach (str_split(self::SUFIXOS) as $sufixo) {
                    $visitar($termo.$sufixo, $profundidade + 1);
                }
            }
        };

        foreach (array_unique(self::SEMENTES) as $semente) {
            $visitar($semente, 0);
        }

        return ['logradouros' => $encontrados, 'consultas' => $consultas, 'truncados' => $truncados];
    }

    /**
     * Grava os logradouros varridos na cidade.
     *
     * Idempotente: reexecutar não duplica. Rua existente é COMPLETADA (ganha
     * bairro e CEP se estiverem vazios), nunca recriada — 44.338 clientes
     * apontam para `ruas.id` e trocar o id apagaria o endereço deles.
     *
     * @param  array<string, array{logradouro:string, bairro:string, cep:string}>  $logradouros
     * @return array{ruas_criadas:int, bairros_criados:int, ruas_atualizadas:int}
     */
    public function gravar(Cidade $cidade, array $logradouros): array
    {
        $criadas = 0;
        $atualizadas = 0;
        $bairrosCriados = 0;

        DB::transaction(function () use ($cidade, $logradouros, &$criadas, &$atualizadas, &$bairrosCriados) {
            $bairros = $this->indexarBairros($cidade);
            $ruas = $this->indexarRuas($cidade);

            foreach ($logradouros as $item) {
                $bairroId = null;

                if ($item['bairro'] !== '') {
                    $chaveBairro = NormalizadorTexto::basico($item['bairro']);

                    if (! isset($bairros[$chaveBairro])) {
                        $novo = Bairro::create([
                            'grupo_id' => $cidade->grupo_id,
                            'cidade_id' => $cidade->id,
                            'descricao' => $item['bairro'],
                            'ativo' => true,
                        ]);
                        $bairros[$chaveBairro] = $novo->id;
                        $bairrosCriados++;
                    }

                    $bairroId = $bairros[$chaveBairro];
                }

                $chaveRua = NormalizadorTexto::basico($item['logradouro']);
                $cep = $item['cep'] !== '' ? $item['cep'] : null;

                if (isset($ruas[$chaveRua])) {
                    $rua = $ruas[$chaveRua];
                    $mudou = [];

                    // Só COMPLETA o que está vazio: o dado que o operador
                    // cadastrou à mão vale mais que o da importação em massa.
                    if ($rua->bairro_id === null && $bairroId !== null) {
                        $mudou['bairro_id'] = $bairroId;
                    }
                    if (empty($rua->cep) && $cep !== null) {
                        $mudou['cep'] = $cep;
                    }

                    if ($mudou !== []) {
                        $rua->forceFill($mudou)->save();
                        $atualizadas++;
                    }

                    continue;
                }

                $nova = Rua::create([
                    'grupo_id' => $cidade->grupo_id,
                    'cidade_id' => $cidade->id,
                    'bairro_id' => $bairroId,
                    'descricao' => $item['logradouro'],
                    'cep' => $cep,
                    'ativo' => true,
                ]);
                $ruas[$chaveRua] = $nova;
                $criadas++;
            }
        });

        return ['ruas_criadas' => $criadas, 'bairros_criados' => $bairrosCriados, 'ruas_atualizadas' => $atualizadas];
    }

    /** @return array<string,int> */
    private function indexarBairros(Cidade $cidade): array
    {
        $mapa = [];

        foreach (Bairro::withoutGrupo()->where('cidade_id', $cidade->id)->get(['id', 'descricao']) as $b) {
            $mapa[NormalizadorTexto::basico((string) $b->descricao)] = (int) $b->id;
        }

        return $mapa;
    }

    /** @return array<string,Rua> */
    private function indexarRuas(Cidade $cidade): array
    {
        $mapa = [];

        foreach (Rua::withoutGrupo()->where('cidade_id', $cidade->id)->get() as $r) {
            $mapa[NormalizadorTexto::basico((string) $r->descricao)] = $r;
        }

        return $mapa;
    }
}
