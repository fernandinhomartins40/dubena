<?php

namespace App\Domain\Geografico;

use App\Domain\Identidade\NormalizadorTexto;
use App\Models\Geografico\Bairro;
use App\Models\Geografico\Cidade;
use App\Models\Geografico\LogradouroOficial;
use App\Models\Geografico\Rua;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Casa o cadastro MANUAL de ruas com o cadastro OFICIAL do CNEFE.
 *
 * O problema medido na base: a mesma via aparece várias vezes, escrita de
 * formas diferentes —
 *   "Rua 10 de Setembro"  ×  "Rua Dez de Setembro"   (a MESMA rua)
 *   "Rua Sete de Seetembro"                          (erro de digitação)
 * Todas passam pela busca do sistema, e o entregador escolhe qualquer uma.
 *
 * O casamento tem três níveis, e a diferença entre eles é o que decide se uma
 * pessoa precisa olhar:
 *
 *   EXATO      nome_busca idêntico ao oficial → é a mesma via, sem dúvida.
 *   PROVAVEL   similaridade alta (uma letra trocada, token a mais/menos).
 *   AUSENTE    não há oficial parecido — rua nova, distrito rural, ou o
 *              município ainda não foi importado.
 *
 * NADA é renomeado ou fundido automaticamente. 44.338 clientes apontam para
 * `ruas.id`, e vias legitimamente parecidas existem ("Rua Paraná" × "Rua
 * Paranaguá"). O serviço PROPÕE; quem decide é a tela de revisão.
 */
class NormalizarLogradouros
{
    /** Acima disto o par é proposta de correção; abaixo, é ruído. */
    public const LIMIAR_PROVAVEL = 0.80;

    /**
     * Analisa as ruas de uma cidade contra o cadastro oficial.
     *
     * @return list<array{rua:Rua, situacao:string, oficial:?LogradouroOficial, similaridade:float}>
     */
    public function analisar(Cidade $cidade): array
    {
        $codIbge = $cidade->municipio_ibge ?? $cidade->cod_ibge;

        if ($codIbge === null) {
            return [];
        }

        $oficiais = $this->oficiaisDe((int) $codIbge);

        if ($oficiais->isEmpty()) {
            return [];
        }

        // Índice por chave normalizada: o casamento exato é o caso comum e não
        // pode custar uma varredura da lista inteira por rua.
        $porChave = [];
        foreach ($oficiais as $o) {
            $porChave[$o->nome_busca] ??= $o;
        }

        $resultado = [];

        foreach (Rua::withoutGrupo()->where('cidade_id', $cidade->id)->get() as $rua) {
            $chave = NormalizadorTexto::logradouro((string) $rua->descricao);

            if ($chave === '') {
                continue;
            }

            if (isset($porChave[$chave])) {
                $resultado[] = [
                    'rua' => $rua,
                    'situacao' => 'exato',
                    'oficial' => $porChave[$chave],
                    'similaridade' => 1.0,
                ];

                continue;
            }

            [$melhor, $escore] = $this->maisParecido($chave, $oficiais);

            $resultado[] = [
                'rua' => $rua,
                'situacao' => $escore >= self::LIMIAR_PROVAVEL ? 'provavel' : 'ausente',
                'oficial' => $escore >= self::LIMIAR_PROVAVEL ? $melhor : null,
                'similaridade' => $escore,
            ];
        }

        return $resultado;
    }

    /**
     * Sugere o logradouro oficial para um texto digitado — usado NA DIGITAÇÃO,
     * antes de a rua errada entrar na base.
     *
     * @return list<array{oficial:LogradouroOficial, similaridade:float}>
     */
    public function sugerir(int $codIbge, string $texto, int $limite = 5): array
    {
        $chave = NormalizadorTexto::logradouro($texto);

        if ($chave === '' || mb_strlen($chave) < 3) {
            return [];
        }

        $sugestoes = [];

        foreach ($this->oficiaisDe($codIbge) as $o) {
            $escore = $o->nome_busca === $chave
                ? 1.0
                : NormalizadorTexto::similaridadeLogradouro($chave, $o->nome_busca);

            if ($escore >= self::LIMIAR_PROVAVEL) {
                $sugestoes[] = ['oficial' => $o, 'similaridade' => $escore];
            }
        }

        usort($sugestoes, fn ($a, $b) => $b['similaridade'] <=> $a['similaridade']);

        return array_slice($sugestoes, 0, $limite);
    }

    /**
     * Aplica uma correção proposta: renomeia a rua para o nome oficial e
     * completa bairro e CEP.
     *
     * O ID NÃO MUDA — é o ponto. Renomear preserva os clientes que apontam
     * para esta rua; recriar apagaria o endereço deles.
     */
    public function aplicar(Rua $rua, LogradouroOficial $oficial): void
    {
        DB::transaction(function () use ($rua, $oficial) {
            $mudancas = ['descricao' => $oficial->nome_completo];

            if (empty($rua->cep) && ! empty($oficial->cep)) {
                $mudancas['cep'] = $oficial->cep;
            }

            if ($rua->bairro_id === null && ! empty($oficial->bairro)) {
                $mudancas['bairro_id'] = $this->bairroDe($rua, $oficial->bairro);
            }

            $rua->forceFill($mudancas)->save();
        });
    }

    /**
     * Ruas do cadastro que apontam para o MESMO logradouro oficial — as
     * duplicatas reais ("10 de Setembro" e "Dez de Setembro").
     *
     * Fundir é decisão humana e mais arriscada que renomear: envolve remapear
     * os clientes de uma rua para outra. Aqui só listamos.
     *
     * @return list<array{oficial:LogradouroOficial, ruas:list<Rua>}>
     */
    public function duplicatas(Cidade $cidade): array
    {
        $porOficial = [];

        foreach ($this->analisar($cidade) as $item) {
            if ($item['oficial'] === null) {
                continue;
            }

            $porOficial[$item['oficial']->id]['oficial'] = $item['oficial'];
            $porOficial[$item['oficial']->id]['ruas'][] = $item['rua'];
        }

        return array_values(array_filter(
            $porOficial,
            fn ($g) => count($g['ruas']) > 1,
        ));
    }

    /** @return Collection<int,LogradouroOficial> */
    private function oficiaisDe(int $codIbge): Collection
    {
        return LogradouroOficial::query()
            ->where('cod_ibge', $codIbge)
            ->get(['id', 'cod_ibge', 'tipo', 'nome', 'bairro', 'cep', 'nome_busca', 'numero_min', 'numero_max']);
    }

    /**
     * @param  Collection<int,LogradouroOficial>  $oficiais
     * @return array{0: ?LogradouroOficial, 1: float}
     */
    private function maisParecido(string $chave, Collection $oficiais): array
    {
        $melhor = null;
        $escore = 0.0;

        foreach ($oficiais as $o) {
            $s = NormalizadorTexto::similaridadeLogradouro($chave, $o->nome_busca);

            if ($s > $escore) {
                $escore = $s;
                $melhor = $o;
            }
        }

        return [$melhor, $escore];
    }

    /** Bairro do grupo correspondente ao nome oficial; cria se não existir. */
    private function bairroDe(Rua $rua, string $nomeOficial): ?int
    {
        $alvo = NormalizadorTexto::basico($nomeOficial);

        foreach (Bairro::withoutGrupo()->where('cidade_id', $rua->cidade_id)->get(['id', 'descricao']) as $b) {
            if (NormalizadorTexto::basico((string) $b->descricao) === $alvo) {
                return (int) $b->id;
            }
        }

        return (int) Bairro::create([
            'grupo_id' => $rua->grupo_id,
            'cidade_id' => $rua->cidade_id,
            'descricao' => $nomeOficial,
            'ativo' => true,
        ])->id;
    }
}
