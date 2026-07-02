<?php

namespace App\Domain\Missao;

use App\Domain\Mobile\PushService;
use App\Domain\Monitora\MonitoraService;
use App\Domain\Pedido\EfeitoPedido;
use App\Models\Logistica\Jornada;
use App\Models\Logistica\LogisticaConfig;
use App\Models\Missao\Missao;
use App\Models\Missao\MissaoAtribuicao;
use App\Models\Mobile\EntregadorPosicao;
use App\Models\Pedido\Pedido;

/**
 * GeradorMissaoService (L7) — o MOTOR de ociosidade. Para cada entregador em
 * JORNADA sem entregas ativas há mais de `ociosidade_min` (config da empresa),
 * atribui automaticamente a missão ativa mais adequada à posição atual:
 *
 *  1º) missão cuja CERCA poligonal contém o ponto (reusa o geofence do Monitora);
 *  2º) missão com centro+raio cobrindo o ponto;
 *  3º) missão sem área definida (vale em toda a praça).
 *
 * Nunca empilha: 1 missão em execução por entregador. Roda pelo comando agendado
 * `logistica:gerar-missoes` (scheduler) — sem depender de request.
 */
class GeradorMissaoService
{
    public function __construct(
        private MonitoraService $geofence,
        private PushService $push,
    ) {}

    /**
     * Varre os entregadores em jornada da empresa e atribui missões aos ociosos.
     *
     * @return int atribuições criadas
     */
    public function gerarParaEmpresa(int $empresaId): int
    {
        $config = LogisticaConfig::query()->where('empresa_id', $empresaId)->first();
        $ociosidadeMin = $config?->ociosidade_min ?? 30;

        $missoes = Missao::query()
            ->where('empresa_id', $empresaId)->where('ativo', true)->get()
            ->filter(fn (Missao $m) => $m->dentroDaJanela());

        if ($missoes->isEmpty()) {
            return 0;
        }

        $criadas = 0;
        $jornadas = Jornada::query()->where('empresa_id', $empresaId)->where('status', 'ativa')->get();

        foreach ($jornadas as $jornada) {
            $uid = (int) $jornada->entregador_user_id;

            if (! $this->estaOcioso($empresaId, $uid, $ociosidadeMin, $jornada)) {
                continue;
            }
            if ($this->temMissaoEmExecucao($uid)) {
                continue;
            }

            $pos = EntregadorPosicao::query()->where('entregador_user_id', $uid)->first();
            $missao = $this->melhorMissao($missoes, $pos ? (float) $pos->latitude : null, $pos ? (float) $pos->longitude : null);
            if ($missao === null) {
                continue;
            }

            MissaoAtribuicao::create([
                'empresa_id' => $empresaId,
                'missao_id' => $missao->id,
                'entregador_user_id' => $uid,
                'status' => 'atribuida',
                'automatica' => true,
            ]);
            $criadas++;

            $this->push->paraUsuario($uid, 'Nova missão', $missao->titulo, [
                'acao' => 'novaMissao', 'missao_id' => (string) $missao->id,
            ]);
        }

        return $criadas;
    }

    /**
     * Ocioso = sem pedido PENDENTE atribuído E sem atividade recente (última ação
     * de entrega há >= X min; sem nenhuma entrega no dia, conta desde o início da
     * jornada).
     */
    private function estaOcioso(int $empresaId, int $entregadorUserId, int $ociosidadeMin, Jornada $jornada): bool
    {
        $temAtivas = Pedido::query()
            ->where('empresa_id', $empresaId)
            ->where('entregador_user_id', $entregadorUserId)
            ->whereHas('situacao', fn ($q) => $q->where('efeito', EfeitoPedido::PENDENTE->value))
            ->exists();

        if ($temAtivas) {
            return false;
        }

        $ultimaAcao = Pedido::query()
            ->where('empresa_id', $empresaId)
            ->where('entregador_user_id', $entregadorUserId)
            ->max('datahora_acao');

        $referencia = $ultimaAcao ? \Illuminate\Support\Carbon::parse($ultimaAcao) : $jornada->iniciada_em;

        return $referencia !== null && $referencia->diffInMinutes(now()) >= $ociosidadeMin;
    }

    private function temMissaoEmExecucao(int $entregadorUserId): bool
    {
        return MissaoAtribuicao::query()
            ->where('entregador_user_id', $entregadorUserId)
            ->whereIn('status', ['atribuida', 'em_andamento'])
            ->exists();
    }

    /**
     * A missão mais adequada à posição: cerca contendo o ponto > raio cobrindo o
     * ponto > sem área. Sem posição conhecida, só missões sem área.
     *
     * @param  \Illuminate\Support\Collection<int, Missao>  $missoes
     */
    private function melhorMissao($missoes, ?float $lat, ?float $lng): ?Missao
    {
        if ($lat !== null && $lng !== null) {
            // 1º: cerca poligonal contendo o ponto.
            foreach ($missoes as $m) {
                if ($m->cerca_id && $m->cerca && $this->geofence->dentroDaCerca($m->cerca->load('pontos'), $lat, $lng)) {
                    return $m;
                }
            }
            // 2º: centro+raio cobrindo o ponto.
            foreach ($missoes as $m) {
                if ($m->centro_lat !== null && $m->centro_lng !== null && $m->raio_m) {
                    $d = $this->geofence->distanciaMetros($lat, $lng, (float) $m->centro_lat, (float) $m->centro_lng);
                    if ($d <= $m->raio_m) {
                        return $m;
                    }
                }
            }
        }

        // 3º: missão sem área definida (vale em toda a praça).
        return $missoes->first(fn (Missao $m) => ! $m->cerca_id && $m->centro_lat === null);
    }
}
