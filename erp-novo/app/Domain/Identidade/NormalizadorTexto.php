<?php

namespace App\Domain\Identidade;

/**
 * Normalização de texto para COMPARAÇÃO de identidade.
 *
 * Toda a identificação de cliente depende de comparar strings que humanos
 * digitaram de formas diferentes. Medido na base real: o mesmo cliente aparece
 * como "SANDRA MARA DE FATIMA CARNEIRO" (canal legado, caixa alta, sem acento)
 * e "Sandra Mara de Fátima Carneiro" (app, como a pessoa digitou). Sem
 * normalizar, são duas pessoas.
 *
 * Tabela de transliteração EXPLÍCITA de propósito: `iconv('ASCII//TRANSLIT')`
 * depende do locale e no Windows devolve "?" para acentuados — o que faria a
 * comparação divergir entre o dev (Windows) e a VPS (Linux).
 */
final class NormalizadorTexto
{
    /** Acentuados → ASCII. Cobre pt-BR e os estrangeirismos comuns em nomes. */
    private const ACENTOS = [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a', 'å' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n', 'ý' => 'y',
    ];

    /**
     * Partículas que não distinguem pessoas — "de", "da", "dos"…
     *
     * Ficam fora da comparação porque a base tem o mesmo nome com e sem elas
     * ("MIGUEL MARCELITO GADENS" × "Miguel MARCELIt gadens"), e mantê-las só
     * adiciona ruído ao casamento.
     *
     * @var list<string>
     */
    private const PARTICULAS = ['de', 'da', 'do', 'das', 'dos', 'e', 'di', 'du', 'del', 'la'];

    /** Minúsculas, sem acento, sem pontuação, espaços colapsados. */
    public static function basico(?string $texto): string
    {
        if ($texto === null) {
            return '';
        }

        $t = mb_strtolower(trim($texto), 'UTF-8');
        $t = strtr($t, self::ACENTOS);
        // Tudo que não é letra/dígito vira espaço: hífen, ponto e o "-" solto
        // que aparece na base ("JEANN RICARDO DE GOES-") não podem separar.
        $t = preg_replace('/[^a-z0-9]+/', ' ', $t) ?? '';

        return trim(preg_replace('/\s+/', ' ', $t) ?? '');
    }

    /** Só os dígitos — para CPF, CNPJ, CEP e telefone. */
    public static function digitos(?string $texto): string
    {
        return preg_replace('/\D+/', '', (string) $texto) ?? '';
    }

    /**
     * Documento com o zero à esquerda restaurado.
     *
     * CPF que passou por planilha ou campo numérico perde o zero inicial e
     * volta com 10 dígitos. Preencher à esquerda até 11 (CPF) ou 14 (CNPJ)
     * faz "5835579969" convergir com "05835579969", em vez de virarem duas
     * pessoas.
     *
     * Não trata o CPF DESLOCADO ("05835579969" × "58355799690", medido uma vez
     * na base): ambos têm 11 dígitos e são indistinguíveis de dois documentos
     * legítimos sem validar o dígito verificador. Esse par cai na fila de
     * revisão pelos outros traços — que é onde deve mesmo ser decidido.
     */
    public static function documento(?string $texto, int $tamanho = 11): string
    {
        $d = self::digitos($texto);

        if ($d === '' || strlen($d) > $tamanho) {
            return $d;
        }

        return str_pad($d, $tamanho, '0', STR_PAD_LEFT);
    }

    /**
     * Telefone reduzido aos 8 dígitos finais.
     *
     * O mesmo número aparece como "42999852622", "(42) 99985-2622" e
     * "+5542999852622" (E.164 do Firebase). Os 8 últimos dígitos são o que
     * sobrevive a DDD, dígito 9 e código de país — é o mesmo critério que o
     * `ClienteAuthService` já usava para achar cliente por telefone.
     *
     * Devolve string vazia se não sobrar número plausível.
     */
    public static function telefone(?string $texto): string
    {
        $d = self::digitos($texto);

        return strlen($d) >= 8 ? substr($d, -8) : '';
    }

    /**
     * Nome reduzido ao que identifica: sem partículas, tokens ordenados.
     *
     * A ordenação faz "Silva Maria" casar com "Maria Silva" — inversão comum
     * entre quem cadastra pelo primeiro nome e quem cadastra pelo sobrenome.
     */
    public static function nome(?string $texto): string
    {
        $tokens = array_filter(
            explode(' ', self::basico($texto)),
            fn (string $t) => $t !== '' && ! in_array($t, self::PARTICULAS, true),
        );

        sort($tokens);

        return implode(' ', $tokens);
    }

    /**
     * Nome em código FONÉTICO — o que pega erro de digitação.
     *
     * Casos reais medidos na base que só casam por fonética:
     *   VICENTE BARONI          × Vicente Barone
     *   GABRIEL NICZAI DE ARAUJO × Gabriel Niczay
     *   MIGUEL MARCELITO GADENS × Miguel MARCELIt gadens
     *
     * Soundex/Metaphone padrão são feitos para o inglês e erram em pt-BR
     * (não sabem que "ç"="s", "ph"="f", "lh"/"nh" são um som só).
     */
    public static function nomeFonetico(?string $texto): string
    {
        $tokens = array_filter(explode(' ', self::nome($texto)), fn ($t) => $t !== '');
        $codigos = array_map([self::class, 'foneticaToken'], $tokens);
        $codigos = array_filter($codigos, fn (string $c) => $c !== '');

        sort($codigos);

        return implode(' ', $codigos);
    }

    /**
     * Código fonético de UMA palavra, pelas regras do português.
     *
     * A ordem das substituições importa: dígrafos primeiro (senão o "h" de
     * "lh" já teria sido removido), depois consoantes de som equivalente,
     * depois as vogais — que são o que mais varia na grafia errada.
     */
    private static function foneticaToken(string $token): string
    {
        $t = $token;

        // 1) Dígrafos e sons compostos.
        $t = strtr($t, [
            'lh' => 'L', 'nh' => 'N', 'ch' => 'X', 'ph' => 'f',
            'rr' => 'r', 'ss' => 's', 'sc' => 's', 'sh' => 'X',
            'qu' => 'k', 'gu' => 'g',
        ]);

        // 2) Consoantes de som equivalente colapsadas num representante.
        //    z→s ("Souza"/"Sousa"), y→i ("Niczay"/"Niczai"), w→v, k→c.
        $t = strtr($t, [
            'z' => 's', 'y' => 'i', 'w' => 'v', 'k' => 'c', 'q' => 'c', 'ç' => 's',
        ]);

        // 3) "c" antes de e/i soa como "s"; senão como "k".
        $t = preg_replace('/c([ei])/', 's$1', $t) ?? $t;
        $t = str_replace('c', 'k', $t);

        // 4) "g" antes de e/i soa como "j".
        $t = preg_replace('/g([ei])/', 'j$1', $t) ?? $t;

        // 5) Vogais átonas viram uma só: é onde mora o erro de digitação
        //    ("Baroni"/"Barone", "Marcelito"/"Marcelit").
        $t = preg_replace('/[aeiou]+/', 'a', $t) ?? $t;

        // 6) Letras repetidas viram uma.
        $t = preg_replace('/(.)\1+/', '$1', $t) ?? $t;

        // 7) "h" mudo (o de dígrafo já virou L/N/X acima).
        $t = str_replace('h', '', $t);

        return $t;
    }

    /**
     * Quanto dois nomes se parecem, de 0 a 1, comparando os códigos fonéticos.
     *
     * Igualdade exata não basta — medido na base real, dois padrões escapam:
     *
     *   "Gabriel Niczay"        ⊂ "GABRIEL NICZAI DE ARAUJO"  (nome PARCIAL)
     *   "Miguel MARCELIt gadens" ~ "MIGUEL MARCELITO GADENS"   (TRUNCADO)
     *
     * Ambos são a mesma pessoa e ambos falham na comparação binária. A medida é
     * a sobreposição sobre o MENOR conjunto de tokens (não sobre a união): quem
     * cadastrou só "Gabriel Niczay" informou menos, não informou outra coisa.
     * Tokens quase iguais (um é prefixo do outro) contam como casados, o que
     * resolve o truncamento.
     */
    public static function similaridadeNome(?string $a, ?string $b): float
    {
        $ta = array_filter(explode(' ', self::nomeFonetico($a)));
        $tb = array_filter(explode(' ', self::nomeFonetico($b)));

        if ($ta === [] || $tb === []) {
            return 0.0;
        }

        $casados = 0;
        $restantes = $tb;

        foreach ($ta as $token) {
            foreach ($restantes as $i => $outro) {
                if ($token === $outro || self::tokensQuaseIguais($token, $outro)) {
                    $casados++;
                    unset($restantes[$i]); // cada token casa no máximo uma vez
                    break;
                }
            }
        }

        $menor = min(count($ta), count($tb));
        $similaridade = $casados / $menor;

        // UM só token casando é evidência fraca, por mais que a fração dê 1.0:
        // "Paulo" bate com "PAULO CESAR DOMINICO" e com todo outro Paulo da
        // base. Sem este teto, um primeiro nome sozinho consolidaria pessoas
        // diferentes — o erro mais caro que este sistema pode cometer.
        if ($menor === 1) {
            return min($similaridade, 0.5);
        }

        return $similaridade;
    }

    /**
     * Um token é prefixo do outro e a diferença é pequena — trata truncamento
     * ("marsalat" ⊂ "marsalata") sem casar palavras genuinamente distintas.
     */
    private static function tokensQuaseIguais(string $a, string $b): bool
    {
        $menor = strlen($a) <= strlen($b) ? $a : $b;
        $maior = strlen($a) <= strlen($b) ? $b : $a;

        // Abaixo de 4 letras a chance de coincidência é alta demais.
        return strlen($menor) >= 4
            && str_starts_with($maior, $menor)
            && strlen($maior) - strlen($menor) <= 2;
    }

    /**
     * Chave canônica do endereço, para casar "R. das Flores" com "Rua das Flores".
     *
     * Tipo de logradouro é removido (é justamente o que mais varia) e o número
     * entra separado — é ele que distingue vizinhos na mesma rua.
     */
    public static function endereco(?string $logradouro, ?string $numero): string
    {
        $l = self::basico($logradouro);

        // Abreviaturas de logradouro fora: "r", "rua", "av", "avenida"… não
        // ajudam a distinguir e atrapalham o casamento.
        $l = preg_replace('/^(r|rua|av|avenida|al|alameda|tv|travessa|pc|praca|rod|rodovia|est|estrada)\s+/', '', $l) ?? $l;

        $n = self::digitos($numero);

        return trim($l.' '.$n);
    }

    /**
     * Tipos de logradouro removidos da chave de busca.
     *
     * São o que MAIS varia na digitação ("R.", "RUA", "Av", "AVENIDA") e não
     * distinguem uma via da outra.
     *
     * @var list<string>
     */
    private const TIPOS_LOGRADOURO = [
        'rua', 'avenida', 'travessa', 'alameda', 'praca', 'rodovia', 'estrada',
        'largo', 'viela', 'via', 'passagem', 'ladeira', 'servidao', 'quadra',
        'conjunto', 'vila', 'jardim', 'parque', 'nucleo', 'chacara', 'colonia',
        'linha', 'ramal', 'trevo', 'anel', 'eixo', 'marginal', 'complexo',
        // Abreviaturas: o operador digita "R. das Flores" e "Av. Brasil" tanto
        // quanto por extenso. A pontuação já virou espaço em basico(), então
        // o que sobra é a letra solta.
        'r', 'av', 'tv', 'al', 'pc', 'rod', 'est', 'pca', 'trav', 'lgo',
    ];

    /**
     * Romanos usuais em nome de via → dígito.
     *
     * "Avenida XV de Novembro" e "Avenida Quinze de Novembro" são a MESMA via —
     * na base real de Guarapuava as duas grafias coexistem.
     *
     * @var array<string,string>
     */
    private const ROMANOS = [
        'ii' => '2', 'iii' => '3', 'iv' => '4', 'v' => '5', 'vi' => '6',
        'vii' => '7', 'viii' => '8', 'ix' => '9', 'x' => '10', 'xi' => '11',
        'xii' => '12', 'xiii' => '13', 'xiv' => '14', 'xv' => '15',
        'xvi' => '16', 'xvii' => '17', 'xviii' => '18', 'xix' => '19',
        'xx' => '20', 'xxi' => '21', 'xxv' => '25', 'xxx' => '30',
    ];

    /**
     * Numerais por extenso → dígito.
     *
     * Ruas de data estão entre as mais comuns do país e aparecem das duas
     * formas: o CNEFE grava "7 DE SETEMBRO", o operador digita "Sete de
     * Setembro". Sem unificar, justamente essas nunca casariam.
     *
     * @var array<string,string>
     */
    private const EXTENSO = [
        'um' => '1', 'primeiro' => '1', 'dois' => '2', 'tres' => '3', 'quatro' => '4',
        'cinco' => '5', 'seis' => '6', 'sete' => '7', 'oito' => '8', 'nove' => '9',
        'dez' => '10', 'onze' => '11', 'doze' => '12', 'treze' => '13', 'quatorze' => '14',
        'catorze' => '14', 'quinze' => '15', 'dezesseis' => '16', 'dezessete' => '17',
        'dezoito' => '18', 'dezenove' => '19', 'vinte' => '20', 'trinta' => '30',
    ];

    /**
     * Semelhança entre NOMES DE VIA. Não use `similaridadeNome` para isto.
     *
     * `similaridadeNome` mede a sobreposição sobre o MENOR conjunto de tokens,
     * porque para PESSOA informar menos é legítimo ("Gabriel Niczay" é o mesmo
     * "Gabriel Niczay Ferreira"). Para logradouro essa regra produz absurdos
     * medidos na base real de Guarapuava:
     *
     *   "Rua Santo André"           → "RUA JOSE DOS SANTOS ANDRADE"   100%
     *   "Rua Sonia Maria Sampaio…"  → "RUA MARIO ZENI"                100%
     *   "Travessa Mato Grosso do Sul" → "RUA MATO GROSSO"             100%
     *
     * São vias DIFERENTES: "Mato Grosso do Sul" não é "Mato Grosso", e um
     * subconjunto de tokens não indica a mesma rua. Aqui a medida é sobre a
     * UNIÃO (índice de Jaccard): quem tem tokens a mais está falando de outra
     * coisa, não informando menos.
     */
    public static function similaridadeLogradouro(?string $a, ?string $b): float
    {
        // Partículas fora: "Carla Selhorst DE Souza" e "Carla Selhorst Souza"
        // são a mesma via, e o "de" sobrando penalizaria o par sem motivo.
        $ta = self::tokensDeVia($a);
        $tb = self::tokensDeVia($b);

        if ($ta === [] || $tb === []) {
            return 0.0;
        }

        // Via de nome curto (um token de cada lado) NÃO tolera letra trocada: aí
        // não há um segundo token para corroborar, e uma letra separa coisas
        // reais. Medido em produção: "Rua Matinhos" × "ESTRADA DE PATINHOS" são
        // duas cidades do Paraná, não um erro de digitação.
        $exigirExato = count($ta) === 1 && count($tb) === 1;

        $casados = 0;
        $restantes = $tb;

        foreach ($ta as $token) {
            foreach ($restantes as $i => $outro) {
                // Tolera a letra trocada do erro de digitação ("Seetembro"),
                // que é justamente o caso que queremos pegar.
                $casa = $exigirExato
                    ? ($token === $outro || self::mesmoTokenNoPlural($token, $outro))
                    : ($token === $outro || self::tokenParecido($token, $outro));

                if ($casa) {
                    $casados++;
                    unset($restantes[$i]);
                    break;
                }
            }
        }

        // União, não o menor: token sobrando PENALIZA.
        $uniao = count($ta) + count($tb) - $casados;

        $similaridade = $uniao > 0 ? $casados / $uniao : 0.0;

        // Um token só casando é evidência fraca — "Brasil" bate com toda via que
        // contenha "Brasil". Só vale quando os DOIS lados têm um token apenas
        // (aí é a mesma via escrita com tipos diferentes: "Rua Brasil"/"Av
        // Brasil"), o caso real das duplicatas da base.
        if ($casados === 1 && (count($ta) > 1 || count($tb) > 1)) {
            return min($similaridade, 0.5);
        }

        return $similaridade;
    }

    /**
     * Tokens iguais a menos de uma letra — cobre o erro de digitação
     * ("seetembro" × "setembro", "rossoni" × "rossini") sem casar palavras
     * genuinamente distintas.
     */
    private static function tokenParecido(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        if (abs(strlen($a) - strlen($b)) > 1) {
            return false;
        }

        // Token de 3 letras só casa por troca do ÚLTIMO caractere — cobre a
        // grafia variante do sobrenome ("Lis"/"Liz", "Luis"/"Luiz") sem casar
        // palavras curtas genuinamente distintas ("sul"/"sol").
        if (strlen($a) === 3 && strlen($b) === 3) {
            return substr($a, 0, 2) === substr($b, 0, 2);
        }

        // Abaixo de 4 letras (e tamanhos diferentes) a coincidência é provável.
        if (strlen($a) < 4 || strlen($b) < 4) {
            return false;
        }

        return levenshtein($a, $b) <= 1;
    }

    /**
     * A mesma palavra no singular e no plural.
     *
     * Exceção deliberada ao "via de um token exige exato": medido em produção,
     * "Das araucária" perdia "RUA DAS ARAUCARIAS" (as partículas saem e sobra
     * um token de cada lado) e casava com "VILA ARAUCARIA", que é outra via.
     *
     * Plural é a MESMA palavra, ao contrário de "matinhos"/"patinhos", onde a
     * letra trocada é interna e separa dois nomes próprios. Por isso a
     * diferença só vale no FIM da palavra e só para "s"/"es".
     */
    private static function mesmoTokenNoPlural(string $a, string $b): bool
    {
        $menor = strlen($a) <= strlen($b) ? $a : $b;
        $maior = strlen($a) <= strlen($b) ? $b : $a;

        if (strlen($menor) < 4 || ! str_starts_with($maior, $menor)) {
            return false;
        }

        return in_array(substr($maior, strlen($menor)), ['s', 'es'], true);
    }

    /**
     * Tokens significativos de um nome de via: sem partículas e sem repetição.
     *
     * @return list<string>
     */
    private static function tokensDeVia(?string $texto): array
    {
        $tokens = array_filter(
            explode(' ', self::logradouro($texto)),
            fn (string $t): bool => $t !== '' && ! in_array($t, self::PARTICULAS, true),
        );

        return array_values(array_unique($tokens));
    }

    /**
     * Chave canônica do NOME de um logradouro, para casar o cadastro manual com
     * o oficial do CNEFE.
     *
     * Precisa produzir EXATAMENTE o mesmo resultado que `normalizar()` do
     * `scripts/cnefe_importar.py` — as duas pontas alimentam a mesma coluna
     * `nome_busca`, e qualquer divergência faria a busca não encontrar nada.
     *
     * Difere de `endereco()`: aqui não entra número (é o nome da VIA, não de um
     * ponto nela) e a lista de tipos é a completa do CNEFE.
     */
    public static function logradouro(?string $texto): string
    {
        $t = self::basico($texto);

        if ($t === '') {
            return '';
        }

        foreach (self::TIPOS_LOGRADOURO as $tipo) {
            if (str_starts_with($t, $tipo.' ')) {
                $t = substr($t, strlen($tipo) + 1);
                break;
            }
        }

        // "i" fica FORA dos romanos de propósito: viraria "1" e destruiria
        // qualquer nome que o contenha isolado.
        $partes = array_map(
            fn (string $p): string => self::EXTENSO[$p] ?? self::ROMANOS[$p] ?? $p,
            explode(' ', trim($t)),
        );

        return trim(implode(' ', $partes));
    }
}
