<?php

namespace App\Domain\Geografico;

use App\Domain\Identidade\NormalizadorTexto;
use App\Models\Geografico\Cidade;
use App\Models\Geografico\MunicipioIbge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Sincroniza o catálogo nacional de municípios e casa as cidades do grupo com ele.
 *
 * A fonte é a API pública do IBGE — sem chave e sem cota, ao contrário do Google.
 * É a fonte OFICIAL do mesmo código que a SEFAZ valida, então não há intermediário
 * entre o dado e o uso dele.
 */
class CatalogoIbge
{
    private const URL = 'https://servicodados.ibge.gov.br/api/v1/localidades/municipios';

    /**
     * Baixa e grava o catálogo. Idempotente: reexecutar não duplica nem apaga.
     *
     * @return array{total:int, novos:int}
     */
    public function sincronizar(): array
    {
        $resposta = Http::timeout(120)->retry(3, 2000)->get(self::URL, ['orderBy' => 'nome']);

        if (! $resposta->successful()) {
            throw new \RuntimeException('IBGE respondeu '.$resposta->status().'.');
        }

        $municipios = $resposta->json();

        if (! is_array($municipios) || count($municipios) < 5000) {
            // O Brasil tem 5.570 municípios. Um payload muito menor significa
            // resposta truncada ou mudança de contrato — gravar isso apagaria
            // metade do catálogo em silêncio.
            throw new \RuntimeException('Payload do IBGE inesperado: '.(is_array($municipios) ? count($municipios) : 0).' municípios.');
        }

        $antes = MunicipioIbge::query()->count();
        $linhas = [];
        $agora = now();

        foreach ($municipios as $m) {
            $uf = $m['microrregiao']['mesorregiao']['UF']
                ?? $m['regiao-imediata']['regiao-intermediaria']['UF']
                ?? null;

            if (! is_array($uf) || empty($m['id']) || empty($m['nome'])) {
                continue;
            }

            $linhas[] = [
                'cod_ibge' => (int) $m['id'],
                'nome' => (string) $m['nome'],
                'uf' => (string) $uf['sigla'],
                'nome_busca' => NormalizadorTexto::basico((string) $m['nome']),
                'cod_uf' => (int) $uf['id'],
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
        }

        // Em lotes: 5.570 linhas num único INSERT estoura o limite de parâmetros.
        foreach (array_chunk($linhas, 500) as $lote) {
            MunicipioIbge::query()->upsert($lote, ['cod_ibge'], ['nome', 'uf', 'nome_busca', 'cod_uf', 'updated_at']);
        }

        return ['total' => count($linhas), 'novos' => max(0, MunicipioIbge::query()->count() - $antes)];
    }

    /**
     * Casa as cidades já cadastradas com o município oficial correspondente.
     *
     * Casa primeiro por CÓDIGO e só depois por nome+UF, porque o código é a
     * chave natural. Um código que existe no catálogo é confirmação; um que não
     * existe é justamente o dado corrompido que este trabalho vem consertar.
     *
     * @return list<array{cidade:Cidade, municipio:?MunicipioIbge, criterio:string}>
     */
    public function conciliar(): array
    {
        $resultado = [];

        foreach (Cidade::withoutGrupo()->orderBy('descricao')->get() as $cidade) {
            [$municipio, $criterio] = $this->resolver($cidade);
            $resultado[] = ['cidade' => $cidade, 'municipio' => $municipio, 'criterio' => $criterio];
        }

        return $resultado;
    }

    /**
     * Encontra o município oficial de uma cidade do grupo.
     *
     * @return array{0: ?MunicipioIbge, 1: string}
     */
    public function resolver(Cidade $cidade): array
    {
        if ($cidade->cod_ibge !== null) {
            $porCodigo = MunicipioIbge::query()->find($cidade->cod_ibge);
            if ($porCodigo !== null) {
                // O código bate, mas confere a UF: a base tem "CAMPO LARGO" com
                // o código de Fraiburgo, e aceitar isso perpetuaria o erro.
                if (strcasecmp($porCodigo->uf, (string) $cidade->uf) === 0) {
                    return [$porCodigo, 'codigo'];
                }

                return [$this->porNome($cidade), 'codigo_uf_divergente'];
            }
        }

        $porNome = $this->porNome($cidade);

        return [$porNome, $porNome !== null ? 'nome' : 'sem_correspondencia'];
    }

    /** Busca por nome normalizado dentro da UF — sem acento e sem caixa. */
    private function porNome(Cidade $cidade): ?MunicipioIbge
    {
        $busca = NormalizadorTexto::basico((string) $cidade->descricao);

        if ($busca === '') {
            return null;
        }

        $exato = MunicipioIbge::query()
            ->where('uf', $cidade->uf)
            ->where('nome_busca', $busca)
            ->first();

        if ($exato !== null) {
            return $exato;
        }

        // "Palmeirinha (Guarapuava)" é distrito, não município: o nome real vem
        // entre parênteses. Sem este passo o distrito ficaria órfão do catálogo.
        if (preg_match('/\(([^)]+)\)/', (string) $cidade->descricao, $m) === 1) {
            $interno = NormalizadorTexto::basico($m[1]);
            if ($interno !== '') {
                return MunicipioIbge::query()
                    ->where('uf', $cidade->uf)
                    ->where('nome_busca', $interno)
                    ->first();
            }
        }

        return null;
    }

    /**
     * Aplica a conciliação: grava o vínculo e CORRIGE o cod_ibge divergente.
     *
     * @param  list<array{cidade:Cidade, municipio:?MunicipioIbge, criterio:string}>  $conciliacao
     * @return array{vinculadas:int, corrigidas:int, orfas:int}
     */
    public function aplicar(array $conciliacao): array
    {
        $vinculadas = 0;
        $corrigidas = 0;
        $orfas = 0;

        DB::transaction(function () use ($conciliacao, &$vinculadas, &$corrigidas, &$orfas) {
            foreach ($conciliacao as $item) {
                $municipio = $item['municipio'];

                if ($municipio === null) {
                    $orfas++;

                    continue;
                }

                $cidade = $item['cidade'];
                $precisaCorrigir = (int) $cidade->cod_ibge !== (int) $municipio->cod_ibge;

                $cidade->forceFill([
                    'municipio_ibge' => $municipio->cod_ibge,
                    'cod_ibge' => $municipio->cod_ibge,
                ])->save();

                $vinculadas++;
                if ($precisaCorrigir) {
                    $corrigidas++;
                }
            }
        });

        return ['vinculadas' => $vinculadas, 'corrigidas' => $corrigidas, 'orfas' => $orfas];
    }
}
