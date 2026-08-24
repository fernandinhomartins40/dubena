<?php

namespace App\Domain\Satelite;

use App\Domain\Alerta\AlertaService;
use App\Models\Alerta;
use App\Models\Satelite\Comodato;
use App\Models\Satelite\ComodatoAvaliacao;
use App\Models\Satelite\ComodatoConfig;
use Illuminate\Support\Carbon;

/**
 * Transforma as avaliações da vigilância em alertas triáveis.
 *
 * Separado do `VigilanciaComodatoService` de propósito: um MEDE, o outro DECIDE
 * o que merece a atenção de uma pessoa. Manter os dois juntos faria a régua de
 * alerta contaminar a série histórica — e a série precisa continuar comparável
 * mesmo quando a régua mudar.
 *
 * Duas origens, porque são perguntas diferentes:
 *
 *   `comodato_giro`       o vasilhame está rodando aqui?
 *   `comodato_vencimento` o contrato que protege o vasilhame ainda vale?
 */
class GerarAlertasComodato
{
    public const ORIGEM_GIRO = 'comodato_giro';

    public const ORIGEM_VENCIMENTO = 'comodato_vencimento';

    public function __construct(
        private VigilanciaComodatoService $vigilancia,
        private AlertaService $alertas,
    ) {
    }

    /**
     * Roda a vigilância e sincroniza os alertas de uma empresa.
     *
     * @return array{avaliados:int, giro:int, vencimento:int, encerrados:int}
     */
    public function executar(int $empresaId, ?Carbon $referencia = null): array
    {
        $referencia ??= now();
        $config = ComodatoConfig::daEmpresa($empresaId);

        $avaliacoes = $this->vigilancia->avaliarEmpresa($empresaId, $referencia);

        $chavesGiro = [];
        foreach ($avaliacoes as $avaliacao) {
            if (! $avaliacao->preocupante()) {
                continue;
            }

            $chavesGiro[] = $this->alertarGiro($avaliacao)->chave;
        }

        $chavesVenc = [];
        foreach ($this->vencendo($empresaId, $config, $referencia) as $comodato) {
            $chavesVenc[] = $this->alertarVencimento($comodato, $referencia)->chave;
        }

        // O cliente voltou a comprar, ou o comodato foi renovado: o alerta
        // fecha sozinho. Sem isto a fila só cresceria.
        $encerrados = $this->alertas->encerrarAusentes($empresaId, self::ORIGEM_GIRO, $chavesGiro)
            + $this->alertas->encerrarAusentes($empresaId, self::ORIGEM_VENCIMENTO, $chavesVenc);

        return [
            'avaliados' => count($avaliacoes),
            'giro' => count($chavesGiro),
            'vencimento' => count($chavesVenc),
            'encerrados' => $encerrados,
        ];
    }

    /**
     * Alerta de giro.
     *
     * A severidade não vem só da classificação: um cliente CRÍTICO com 4
     * vasilhames e outro com 249 representam riscos patrimoniais incomparáveis,
     * e a fila precisa ordenar por isso — senão a equipe gasta a manhã no caso
     * pequeno.
     */
    private function alertarGiro(ComodatoAvaliacao $a): Alerta
    {
        $emPosse = (float) $a->em_posse;

        $severidade = match (true) {
            $a->classificacao === 'CRITICO' && $emPosse >= 20 => 'ALTA',
            $a->classificacao === 'CRITICO' => 'MEDIA',
            $emPosse >= 20 => 'MEDIA',
            default => 'BAIXA',
        };

        $titulo = sprintf(
            '%s com %s vasilhame(s) e giro de %sx',
            $a->cliente?->nome ?? "Cliente #{$a->cliente_id}",
            $this->num($emPosse),
            $this->num((float) $a->giro),
        );

        return $this->alertas->registrar(
            (int) $a->empresa_id,
            (int) $a->grupo_id,
            self::ORIGEM_GIRO,
            // A chave é do CLIENTE, não da avaliação: o problema é o mesmo
            // semana após semana, e é isso que o dedup precisa reconhecer.
            self::ORIGEM_GIRO.":cliente:{$a->cliente_id}",
            [
                'severidade' => $severidade,
                'titulo' => $titulo,
                'descricao' => $a->motivo,
                'cliente_id' => $a->cliente_id,
                'dados' => [
                    'em_posse' => $emPosse,
                    'giro' => (float) $a->giro,
                    'baseline_giro' => $a->baseline_giro !== null ? (float) $a->baseline_giro : null,
                    'variacao' => $a->variacao !== null ? (float) $a->variacao : null,
                    'dias_sem_compra' => $a->dias_sem_compra,
                    'comprado_janela' => (float) $a->comprado_janela,
                    'pedidos_janela' => $a->pedidos_janela,
                    'dias_janela' => $a->dias_janela,
                    'classificacao' => $a->classificacao,
                ],
            ],
        );
    }

    /**
     * Comodatos cujo contrato vence dentro da antecedência configurada — ou já
     * venceu.
     *
     * @return \Illuminate\Support\Collection<int,Comodato>
     */
    private function vencendo(int $empresaId, ComodatoConfig $config, Carbon $referencia)
    {
        return Comodato::query()
            ->with('cliente:id,nome')
            ->where('empresa_id', $empresaId)
            ->whereIn('situacao', ['ATIVO', 'PARCIAL'])
            ->whereNotNull('data_vencimento')
            ->whereDate('data_vencimento', '<=', $referencia->copy()->addDays($config->dias_aviso_vencimento))
            ->whereRaw('quantidade - quantidade_devolvida > 0')
            ->get();
    }

    private function alertarVencimento(Comodato $c, Carbon $referencia): Alerta
    {
        // `diffInDays` sem sinal não distingue "vence em 10" de "venceu há 10",
        // que pedem urgências opostas.
        $dias = (int) $referencia->copy()->startOfDay()
            ->diffInDays($c->data_vencimento->copy()->startOfDay(), false);

        $vencido = $dias < 0;
        $emPosse = (float) $c->quantidade - (float) $c->quantidade_devolvida;

        return $this->alertas->registrar(
            (int) $c->empresa_id,
            (int) $c->grupo_id,
            self::ORIGEM_VENCIMENTO,
            self::ORIGEM_VENCIMENTO.":comodato:{$c->id}",
            [
                // Vencido é ALTA quando há patrimônio relevante em jogo: sem
                // contrato vigente a revenda não tem instrumento para reaver.
                'severidade' => match (true) {
                    $vencido && $emPosse >= 10 => 'ALTA',
                    $vencido => 'MEDIA',
                    default => 'BAIXA',
                },
                'titulo' => sprintf(
                    'Comodato de %s %s',
                    $c->cliente?->nome ?? "cliente #{$c->cliente_id}",
                    $vencido ? 'venceu há '.abs($dias).' dia(s)' : "vence em {$dias} dia(s)",
                ),
                'descricao' => sprintf(
                    '%s vasilhame(s) em poder do cliente, contrato com vencimento em %s. %s',
                    $this->num($emPosse),
                    $c->data_vencimento->format('d/m/Y'),
                    $vencido
                        ? 'Sem contrato vigente a revenda não tem instrumento para reaver o vasilhame.'
                        : 'Renove o contrato ou agende a devolução.',
                ),
                'cliente_id' => $c->cliente_id,
                'comodato_id' => $c->id,
                'dados' => [
                    'dias' => $dias,
                    'vencido' => $vencido,
                    'em_posse' => $emPosse,
                    'data_vencimento' => $c->data_vencimento->toDateString(),
                ],
            ],
        );
    }

    private function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
    }
}
