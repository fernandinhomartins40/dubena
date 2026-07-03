<?php

namespace App\Domain\Missao;

use App\Domain\Shared\Geo;
use App\Models\Cliente\Cliente;
use App\Models\Missao\Missao;
use App\Models\Missao\MissaoAtribuicao;
use App\Models\Missao\MissaoEvidencia;
use App\Models\Missao\MissaoTrilha;
use App\Models\Missao\MissaoVisita;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * MissaoService (L7) — ciclo de execução da missão pelo entregador: iniciar,
 * registrar visita (com evidência fotográfica obrigatória conforme a missão),
 * gravar a trilha GPS em lote, sugerir a próxima casa, adiar (com aprovação
 * posterior — ETAPA 11) e concluir. Toda regra aqui, no backend.
 */
class MissaoService
{
    /** Disco privado (mesmo das comprovações P7). */
    private const DISCO = 'local';

    /** A atribuição em execução do entregador (atribuida/em_andamento), se houver. */
    public function atribuicaoAtiva(int $entregadorUserId): ?MissaoAtribuicao
    {
        return MissaoAtribuicao::query()
            ->where('entregador_user_id', $entregadorUserId)
            ->whereIn('status', ['atribuida', 'em_andamento'])
            ->with('missao')
            ->latest('id')
            ->first();
    }

    /** Marca o início da execução. */
    public function iniciar(MissaoAtribuicao $atribuicao): MissaoAtribuicao
    {
        if ($atribuicao->status !== 'atribuida') {
            throw ValidationException::withMessages(['missao' => 'Missão não está aguardando início.']);
        }

        $atribuicao->forceFill(['status' => 'em_andamento', 'iniciada_em' => now()])->save();

        return $atribuicao->refresh();
    }

    /**
     * Registra uma visita (residência) com a evidência exigida.
     *
     * @param  array{latitude?:float|null, longitude?:float|null, status:string, cliente_id?:int|null,
     *   observacao?:string|null, duracao_seg?:int|null}  $dados
     */
    public function registrarVisita(MissaoAtribuicao $atribuicao, array $dados, ?UploadedFile $foto = null, string $tipoFoto = 'visita'): MissaoVisita
    {
        if (! $atribuicao->emExecucao()) {
            throw ValidationException::withMessages(['missao' => 'Missão não está em execução.']);
        }
        if (! in_array($dados['status'], MissaoVisita::STATUS, true)) {
            throw ValidationException::withMessages(['status' => 'Status de visita inválido.']);
        }

        // Evidência obrigatória conforme o molde da missão (exceto "ausente" —
        // não há o que fotografar de porta fechada se a missão não for panfletagem).
        $exigeFoto = (bool) $atribuicao->missao?->exige_foto
            && ! ($dados['status'] === 'ausente' && $atribuicao->missao?->tipo !== 'panfletagem');
        if ($exigeFoto && $foto === null) {
            throw ValidationException::withMessages(['foto' => 'Envie a foto de evidência da visita.']);
        }

        return DB::transaction(function () use ($atribuicao, $dados, $foto, $tipoFoto) {
            $visita = MissaoVisita::create([
                'empresa_id' => $atribuicao->empresa_id,
                'missao_atribuicao_id' => $atribuicao->id,
                'latitude' => $dados['latitude'] ?? null,
                'longitude' => $dados['longitude'] ?? null,
                'status' => $dados['status'],
                'cliente_id' => $dados['cliente_id'] ?? null,
                'iniciada_em' => now(),
                'finalizada_em' => now(),
                'duracao_seg' => $dados['duracao_seg'] ?? null,
                'observacao' => $dados['observacao'] ?? null,
            ]);

            if ($foto) {
                MissaoEvidencia::create([
                    'empresa_id' => $atribuicao->empresa_id,
                    'missao_visita_id' => $visita->id,
                    'tipo' => $tipoFoto,
                    'foto_path' => Storage::disk(self::DISCO)->putFile(
                        "missoes/{$atribuicao->empresa_id}/{$atribuicao->id}", $foto,
                    ),
                ]);
            }

            return $visita;
        });
    }

    /**
     * Grava a trilha GPS em LOTE (o app acumula e envia de tempos em tempos).
     *
     * @param  list<array{latitude:float, longitude:float, registrado_em?:string|null}>  $pontos
     * @return int pontos gravados
     */
    public function registrarTrilha(MissaoAtribuicao $atribuicao, array $pontos): int
    {
        if (! $atribuicao->emExecucao()) {
            return 0; // trilha fora de execução é descartada silenciosamente
        }

        $linhas = array_map(fn (array $p) => [
            'empresa_id' => $atribuicao->empresa_id,
            'missao_atribuicao_id' => $atribuicao->id,
            'latitude' => $p['latitude'],
            'longitude' => $p['longitude'],
            'registrado_em' => $p['registrado_em'] ?? now(),
        ], $pontos);

        MissaoTrilha::insert($linhas);

        return count($linhas);
    }

    /**
     * Próxima casa mais próxima: o CLIENTE geocodificado da empresa mais perto da
     * posição atual que ainda NÃO foi visitado nesta missão. Base concreta para
     * prospecção/visita; panfletagem usa como referência de quarteirão.
     *
     * @return array{cliente_id:int, nome:string, endereco:string, lat:float, lng:float, distancia_m:float}|null
     */
    public function proximaCasa(MissaoAtribuicao $atribuicao, float $lat, float $lng): ?array
    {
        $visitados = MissaoVisita::query()
            ->where('missao_atribuicao_id', $atribuicao->id)
            ->whereNotNull('cliente_id')
            ->pluck('cliente_id');

        // PF-1: BUSCA EM ANÉIS EXPANSÍVEIS. Antes carregava TODOS os clientes da
        // empresa (O(N)). Agora tenta caixas de raio crescente (500m → 2km → 10km),
        // indexadas por lat/lng, e só cai no full scan se nenhum anel achar alguém
        // (praça esparsa). O resultado é o mesmo (o cliente não-visitado mais próximo).
        $candidato = null;
        foreach ([500.0, 2000.0, 10000.0, null] as $raioM) {
            $q = Cliente::query()
                ->where('empresa_id', $atribuicao->empresa_id)
                ->whereNotNull('latitude')->whereNotNull('longitude')
                ->whereNotIn('id', $visitados);

            if ($raioM !== null) {
                $box = Geo::boundingBox($lat, $raioM);
                $q->whereBetween('latitude', [$lat - $box['lat_delta'], $lat + $box['lat_delta']])
                    ->whereBetween('longitude', [$lng - $box['lng_delta'], $lng + $box['lng_delta']]);
            }

            $candidato = $q->get(['id', 'nome', 'endereco', 'numero', 'latitude', 'longitude'])
                ->map(fn (Cliente $c) => [
                    'cliente' => $c,
                    'dist' => $this->distanciaM($lat, $lng, (float) $c->latitude, (float) $c->longitude),
                ])
                ->sortBy('dist')
                ->first();

            if ($candidato) {
                break;
            }
        }

        if (! $candidato) {
            return null;
        }

        $c = $candidato['cliente'];

        return [
            'cliente_id' => $c->id,
            'nome' => $c->nome,
            'endereco' => trim(($c->endereco ?? '').', '.($c->numero ?? '')),
            'lat' => (float) $c->latitude,
            'lng' => (float) $c->longitude,
            'distancia_m' => round($candidato['dist']),
        ];
    }

    /** Solicita ADIAMENTO (ETAPA 11) — para de executar; aprovação fica pendente. */
    public function adiar(MissaoAtribuicao $atribuicao, string $motivo, ?string $detalhe = null): MissaoAtribuicao
    {
        if (! $atribuicao->emExecucao()) {
            throw ValidationException::withMessages(['missao' => 'Missão não está em execução.']);
        }
        if (! in_array($motivo, ['nova_entrega', 'emergencia', 'veiculo', 'clima', 'outro'], true)) {
            throw ValidationException::withMessages(['motivo' => 'Motivo de adiamento inválido.']);
        }

        $atribuicao->forceFill([
            'status' => 'adiada',
            'adiamento_motivo' => $motivo,
            'adiamento_detalhe' => $detalhe,
            'adiada_em' => now(),
            'adiamento_aprovacao' => 'pendente',
        ])->save();

        return $atribuicao->refresh();
    }

    /** Conclui a execução (vai para a fila de auditoria do operador — L9). */
    public function concluir(MissaoAtribuicao $atribuicao): MissaoAtribuicao
    {
        if (! $atribuicao->emExecucao()) {
            throw ValidationException::withMessages(['missao' => 'Missão não está em execução.']);
        }

        $atribuicao->forceFill(['status' => 'concluida', 'concluida_em' => now()])->save();

        return $atribuicao->refresh();
    }

    /** Métricas da execução (auditoria L9): visitas, conversões, distância da trilha. */
    public function metricas(MissaoAtribuicao $atribuicao): array
    {
        $visitas = $atribuicao->visitas()->get();
        $trilha = $atribuicao->trilha()->orderBy('registrado_em')->get(['latitude', 'longitude']);

        $distanciaKm = 0.0;
        for ($i = 1; $i < $trilha->count(); $i++) {
            $distanciaKm += $this->distanciaM(
                (float) $trilha[$i - 1]->latitude, (float) $trilha[$i - 1]->longitude,
                (float) $trilha[$i]->latitude, (float) $trilha[$i]->longitude,
            ) / 1000;
        }

        $duracaoMin = $atribuicao->iniciada_em
            ? $atribuicao->iniciada_em->diffInMinutes($atribuicao->concluida_em ?? now())
            : null;

        return [
            'visitas_total' => $visitas->count(),
            'por_status' => $visitas->countBy('status'),
            'vendas' => $visitas->where('status', 'venda')->count(),
            'interessados' => $visitas->where('status', 'interessado')->count(),
            'distancia_km' => round($distanciaKm, 2),
            'duracao_min' => $duracaoMin !== null ? (int) $duracaoMin : null,
            'pontos_trilha' => $trilha->count(),
        ];
    }

    private function distanciaM(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        return Geo::metros($lat1, $lng1, $lat2, $lng2);
    }
}
