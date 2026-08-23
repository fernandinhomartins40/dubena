<?php

namespace App\Domain\Geografico\Drivers;

use App\Domain\Geografico\Contracts\FonteLogradouros;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Base de CEP dos Correios via ViaCEP — a fonte CERTA para enumerar logradouros.
 *
 * Por que não o Google Maps: a Google Maps Platform não tem endpoint que liste
 * as ruas de um município (Geocoding resolve endereço→coordenada; Places busca
 * POI por proximidade, paginado e enviesado para comércio), e os Termos de
 * Serviço proíbem armazenar o conteúdo fora de um mapa Google. A base de CEP,
 * ao contrário, É um cadastro de logradouros com bairro e CEP — os três campos
 * que `ruas`/`bairros` já têm. O legado sabia disso: `legado.ruas` tem a coluna
 * `importacaocep_id`.
 *
 * Limites medidos na API real (2026-08-23):
 *  - termo com menos de 3 caracteres → HTTP 400;
 *  - no máximo 50 resultados por consulta, truncados SEM AVISO.
 */
class ViaCepFonte implements FonteLogradouros
{
    private const BASE = 'https://viacep.com.br/ws';

    /** Medido: consultas amplas voltam exatamente 50 em cidades diferentes. */
    private const TETO = 50;

    public function teto(): int
    {
        return self::TETO;
    }

    public function buscar(string $uf, string $cidade, string $termo): array
    {
        // Abaixo de 3 caracteres a API responde 400: nem gasta a requisição.
        if (mb_strlen($termo) < 3) {
            return [];
        }

        $url = sprintf('%s/%s/%s/%s/json/', self::BASE, rawurlencode($uf), rawurlencode($cidade), rawurlencode($termo));

        try {
            $resposta = Http::timeout(30)->retry(2, 1500)->get($url);
        } catch (\Throwable $e) {
            Log::warning('viacep: falha na consulta', ['cidade' => $cidade, 'termo' => $termo, 'erro' => $e->getMessage()]);

            return [];
        }

        if (! $resposta->successful()) {
            return [];
        }

        $dados = $resposta->json();

        // Cidade inexistente devolve `{"erro": true}` em vez de lista.
        if (! is_array($dados) || array_is_list($dados) === false) {
            return [];
        }

        $itens = [];

        foreach ($dados as $d) {
            if (! is_array($d) || empty($d['logradouro'])) {
                continue;
            }

            $itens[] = [
                'logradouro' => trim((string) $d['logradouro']),
                'bairro' => trim((string) ($d['bairro'] ?? '')),
                'cep' => trim((string) ($d['cep'] ?? '')),
            ];
        }

        return $itens;
    }
}
