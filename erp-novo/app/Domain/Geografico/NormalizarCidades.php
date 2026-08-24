<?php

namespace App\Domain\Geografico;

use App\Domain\Identidade\NormalizadorTexto;
use App\Models\Geografico\Cidade;
use App\Models\Geografico\MunicipioIbge;
use Illuminate\Support\Facades\DB;

/**
 * Normaliza as cidades cadastradas à mão contra o catálogo oficial do IBGE.
 *
 * O que a base real mostrou, e que a conciliação de código sozinha NÃO resolve:
 *
 *  1. NOME divergente do oficial — "MATELANDIA" (sem acento), "Lidianopolis",
 *     "Jaraguá do Siul" (digitação), "Rua Palhoça" (o "Rua" entrou no nome da
 *     cidade). O vínculo está certo; o nome exibido é que está torto.
 *
 *  2. VÍNCULO ERRADO — "CAMPO LARGO" cadastrada com UF=SC e o código de
 *     Fraiburgo. O código existe e a UF bate com ele, então a conciliação
 *     aceitou. Mas o NOME diz outra coisa, e é o nome que denuncia o erro.
 *
 *  3. DISTRITO — "Palmeirinha (Guarapuava)" e "Colônia Vitória" carregam o
 *     código do município-sede. Isso está CORRETO (distrito pertence ao
 *     município) e não pode ser "consertado": são praças de entrega distintas,
 *     com 231 e 17 clientes próprios.
 *
 * Distinguir (2) de (3) é o ponto: nos dois o nome difere do oficial, mas um é
 * erro e o outro é legítimo. O critério é a SEMELHANÇA do nome — distrito tem
 * nome próprio (não parecido com a sede); erro de digitação tem nome parecido
 * com o município que deveria estar ali.
 */
class NormalizarCidades
{
    /** Acima disto, o nome cadastrado é o oficial mal escrito. */
    public const LIMIAR_MESMO_NOME = 0.80;

    /**
     * Analisa as cidades do grupo.
     *
     * @return list<array{cidade:Cidade, situacao:string, oficial:?MunicipioIbge, sugerido:?MunicipioIbge, similaridade:float}>
     */
    public function analisar(): array
    {
        $resultado = [];

        foreach (Cidade::withoutGrupo()->orderBy('descricao')->get() as $cidade) {
            $resultado[] = $this->avaliar($cidade);
        }

        return $resultado;
    }

    /**
     * @return array{cidade:Cidade, situacao:string, oficial:?MunicipioIbge, sugerido:?MunicipioIbge, similaridade:float}
     */
    public function avaliar(Cidade $cidade): array
    {
        $base = [
            'cidade' => $cidade,
            'situacao' => 'sem_vinculo',
            'oficial' => null,
            'sugerido' => null,
            'similaridade' => 0.0,
        ];

        if ($cidade->municipio_ibge === null) {
            // Sem vínculo: procura o município pelo nome, em qualquer UF — a UF
            // do cadastro é justamente o que costuma estar errado
            // ("Gravataí/PR", que é RS).
            $base['sugerido'] = $this->porNomeQualquerUf((string) $cidade->descricao);
            $base['situacao'] = $base['sugerido'] !== null ? 'sugestao_uf' : 'sem_correspondencia';

            return $base;
        }

        $oficial = MunicipioIbge::query()->find($cidade->municipio_ibge);

        if ($oficial === null) {
            return $base;
        }

        $base['oficial'] = $oficial;

        if ((string) $cidade->descricao === $oficial->nome) {
            $base['situacao'] = 'ok';
            $base['similaridade'] = 1.0;

            return $base;
        }

        // Compara a forma NORMALIZADA antes da similaridade: "MATELANDIA" e
        // "Matelândia" são o mesmo texto sem acento e caixa, mas
        // `similaridadeNome` os pontua 0.50 — ela limita nome de UM token de
        // propósito (para pessoa, "Paulo" sozinho não identifica ninguém).
        // Cidade não tem esse problema: o nome inteiro é a identidade.
        $normalizadoCadastro = NormalizadorTexto::basico((string) $cidade->descricao);
        $normalizadoOficial = NormalizadorTexto::basico($oficial->nome);

        if ($normalizadoCadastro === $normalizadoOficial) {
            $base['situacao'] = 'nome_divergente';
            $base['similaridade'] = 1.0;

            return $base;
        }

        $similaridade = NormalizadorTexto::similaridadeNome($cidade->descricao, $oficial->nome);
        $base['similaridade'] = $similaridade;

        // Nome parecido com o do vínculo: é o mesmo município mal escrito.
        if ($similaridade >= self::LIMIAR_MESMO_NOME) {
            $base['situacao'] = 'nome_divergente';

            return $base;
        }

        // Nome NÃO parece com o do vínculo. Ou é distrito (nome próprio), ou o
        // vínculo está errado. Quem decide é se existe um município homônimo:
        // se existe, o cadastro quis dizer aquele e o vínculo está errado.
        $homonimo = $this->porNomeQualquerUf((string) $cidade->descricao);

        if ($homonimo !== null) {
            // O homônimo É o município vinculado: o nome só tem lixo em volta
            // ("Rua Palhoça" → Palhoça). Vínculo certo, grafia errada.
            if ($homonimo->cod_ibge === $oficial->cod_ibge) {
                $base['situacao'] = 'nome_divergente';

                return $base;
            }

            $base['situacao'] = 'vinculo_suspeito';
            $base['sugerido'] = $homonimo;

            return $base;
        }

        // O nome do cadastro CONTÉM o do município ("Rua Palhoça" contém
        // "Palhoça")? Então é o mesmo lugar com prefixo/sufixo espúrio, não um
        // distrito de nome próprio.
        if ($this->contemONome($normalizadoCadastro, $normalizadoOficial)) {
            $base['situacao'] = 'nome_divergente';

            return $base;
        }

        // Sem homônimo: nome próprio dentro do município. É distrito/localidade,
        // e está certo — não mexer.
        $base['situacao'] = 'distrito';

        return $base;
    }

    /**
     * Corrige o NOME da cidade para o oficial.
     *
     * O id não muda: 44 mil clientes apontam para `cidades.id` e recriar o
     * registro apagaria o endereço deles. Só o texto exibido é acertado.
     *
     * @throws ColisaoDeNome quando já existe outra cidade do grupo com o nome
     *                       oficial — ver o porquê em `colideCom()`.
     */
    public function corrigirNome(Cidade $cidade, MunicipioIbge $oficial): void
    {
        $colidente = $this->colideCom($cidade, $oficial);

        if ($colidente !== null) {
            throw new ColisaoDeNome($cidade, $colidente, $oficial);
        }

        DB::transaction(fn () => $cidade->forceFill([
            'descricao' => $oficial->nome,
            'uf' => $oficial->uf,
            'cod_ibge' => $oficial->cod_ibge,
            'municipio_ibge' => $oficial->cod_ibge,
        ])->save());
    }

    /**
     * Já existe OUTRA cidade do grupo com o nome oficial?
     *
     * Descoberto aplicando em produção: corrigir "CORENEL VIVIDA" para "Coronel
     * Vivida" esbarra em `cidades_grupo_id_descricao_uf_unique`, porque o
     * registro #622 já tem exatamente esse nome.
     *
     * A colisão não é obstáculo a contornar — é a DUPLICATA se revelando. Os
     * dois registros são a mesma cidade, e o que falta não é renomear, é
     * decidir qual sobrevive e para onde vão os clientes do outro. Fundir é
     * decisão humana, então aqui apenas recusamos com a informação completa.
     */
    public function colideCom(Cidade $cidade, MunicipioIbge $oficial): ?Cidade
    {
        return Cidade::withoutGrupo()
            ->where('grupo_id', $cidade->grupo_id)
            ->where('descricao', $oficial->nome)
            ->where('uf', $oficial->uf)
            ->where('id', '!=', $cidade->id)
            ->first();
    }

    /**
     * Revincula a cidade ao município correto (caso de código/UF errados).
     *
     * Diferente de `corrigirNome`: aqui o vínculo em si estava errado, então o
     * código E a UF mudam. O nome também passa a ser o oficial — manter o nome
     * antigo com o vínculo novo recriaria a ambiguidade.
     */
    public function revincular(Cidade $cidade, MunicipioIbge $correto): void
    {
        $this->corrigirNome($cidade, $correto);
    }

    /**
     * Cidades do grupo que apontam para o MESMO município oficial.
     *
     * Nem toda repetição é erro: distrito e sede compartilham o código de
     * propósito. Só é duplicata quando os NOMES também são equivalentes
     * ("CORENEL VIVIDA" e "Coronel Vivida").
     *
     * @return list<array{oficial:MunicipioIbge, cidades:list<Cidade>}>
     */
    public function duplicatas(): array
    {
        $porMunicipio = [];

        foreach ($this->analisar() as $item) {
            if ($item['oficial'] === null || $item['situacao'] === 'distrito') {
                continue;
            }

            $cod = $item['oficial']->cod_ibge;
            $porMunicipio[$cod]['oficial'] = $item['oficial'];
            $porMunicipio[$cod]['cidades'][] = $item['cidade'];
        }

        return array_values(array_filter(
            $porMunicipio,
            fn ($g) => count($g['cidades']) > 1,
        ));
    }

    /**
     * O nome cadastrado é o do município com lixo em volta?
     *
     * "rua palhoca" contém "palhoca" e é o mesmo lugar. Já "palmeirinha
     * guarapuava" TAMBÉM contém "guarapuava", mas é distrito — o que separa os
     * dois é o que sobra: em "rua" sobra uma palavra vazia de identidade; em
     * "palmeirinha" sobra um nome de lugar.
     *
     * A lista é fechada de propósito: qualquer palavra que sobre e não esteja
     * aqui é tratada como nome próprio, e o registro fica como distrito. Errar
     * para o lado do distrito é seguro — não altera nada.
     */
    private function contemONome(string $cadastrado, string $oficial): bool
    {
        if ($oficial === '' || ! str_contains($cadastrado, $oficial)) {
            return false;
        }

        $sobra = array_filter(explode(' ', trim(str_replace($oficial, ' ', $cadastrado))));

        if ($sobra === []) {
            return true;
        }

        // Palavras que não identificam lugar nenhum — são resto de digitação.
        $vazias = ['rua', 'av', 'avenida', 'cidade', 'municipio', 'distrito', 'de', 'do', 'da'];

        return array_diff($sobra, $vazias) === [];
    }

    /**
     * Procura o município pelo nome em QUALQUER UF.
     *
     * A UF do cadastro não serve de filtro aqui: ela é parte do que costuma
     * estar errado. Devolve apenas quando há UM município com aquele nome —
     * havendo vários ("Bom Jesus" existe em 6 estados), a escolha é humana.
     */
    private function porNomeQualquerUf(string $nome): ?MunicipioIbge
    {
        $busca = NormalizadorTexto::basico($nome);

        if ($busca === '') {
            return null;
        }

        $achados = MunicipioIbge::query()->where('nome_busca', $busca)->limit(2)->get();

        return $achados->count() === 1 ? $achados->first() : null;
    }
}
