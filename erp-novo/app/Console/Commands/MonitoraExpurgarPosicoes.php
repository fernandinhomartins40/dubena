<?php

namespace App\Console\Commands;

use App\Console\Concerns\RelataAutomacao;
use App\Domain\Tenant\AutomacaoPorEmpresa;
use App\Models\EmpresaConfig;
use App\Models\Monitora\Posicao;
use App\Models\Monitora\Veiculo;
use Illuminate\Console\Command;

/**
 * monitora:expurgar-posicoes — retenção do histórico de rastreamento.
 *
 * `monitora_posicoes` é append-only e nasceu sem nenhuma rotina de limpeza: em
 * produção chegou a 27.891 linhas num dia. A deduplicação no
 * MonitoraSyncService já cortou o pior (fix repetido pelo provedor), mas o que
 * sobra é legítimo e cresce para sempre — e disco cheio na VPS vira CPU gasta
 * pelo agente de monitoramento varrendo `du` sem parar.
 *
 * A janela de retenção é configuração DA EMPRESA (`dados.monitora_retencao_dias`
 * em empresa_configs), nunca constante no código: quanto tempo se guarda
 * rastreamento é decisão de negócio — tem revenda que precisa de 90 dias para
 * contestar multa e tem quem não queira guardar nada além do mês corrente.
 */
class MonitoraExpurgarPosicoes extends Command
{
    use RelataAutomacao;

    protected $signature = 'monitora:expurgar-posicoes
                            {--dias= : Sobrepõe a retenção configurada (todas as empresas)}
                            {--empresa= : Limita a uma empresa}
                            {--dry-run : Só conta, não apaga}';

    protected $description = 'Apaga posições de GPS mais antigas que a retenção configurada por empresa.';

    /** Retenção assumida quando a empresa não configurou nada. */
    private const RETENCAO_PADRAO_DIAS = 90;

    /**
     * Lote pequeno de propósito: o expurgo roda de madrugada, mas divide o
     * Postgres com o resto da operação. DELETE único de centenas de milhares de
     * linhas segura lock e incha o WAL; em lotes, cada transação é curta.
     */
    private const LOTE = 1000;

    public function handle(AutomacaoPorEmpresa $automacao): int
    {
        // Com a identidade de automação de cada tenant — o DELETE roda sob a
        // RLS, então só alcança posição de veículo da própria empresa.
        //
        // `soAtivas: false`: empresa desativada continua guardando histórico
        // que a retenção manda apagar; deixá-la de fora faria o dado de quem
        // saiu ficar para sempre.
        $resultado = $automacao->paraCada(
            fn (int $empresaId) => $this->expurgar($empresaId),
            $this->option('empresa') !== null ? (int) $this->option('empresa') : null,
            soAtivas: false,
        );

        $this->info("Total: {$resultado->total()} posição(ões).");

        return $this->relatar($resultado);
    }

    private function expurgar(int $empresaId): int
    {
        $dias = $this->retencaoDias($empresaId);

        // Retenção zero/negativa = "guardar para sempre". Precisa ser uma
        // escolha explícita possível: apagar histórico é irreversível, e o
        // default silencioso nunca deve ser a opção destrutiva.
        if ($dias <= 0) {
            $this->line("Empresa {$empresaId}: retenção desligada, nada a fazer.");

            return 0;
        }

        $corte = now()->subDays($dias);

        // Filtra pelos veículos DA EMPRESA: monitora_posicoes não tem
        // empresa_id próprio, a empresa vem do veículo.
        $veiculoIds = Veiculo::query()->where('empresa_id', $empresaId)->pluck('id');

        if ($veiculoIds->isEmpty()) {
            return 0;
        }

        $alvo = Posicao::query()
            ->whereIn('veiculo_id', $veiculoIds)
            ->where('registrado_em', '<', $corte);

        if ($this->option('dry-run')) {
            $qtd = (clone $alvo)->count();
            $this->line("Empresa {$empresaId}: {$qtd} posição(ões) anterior(es) a {$corte->toDateString()} (dry-run).");

            return $qtd;
        }

        $apagadas = 0;
        do {
            // `limit` no delete e laço: ver LOTE acima.
            $n = (clone $alvo)->limit(self::LOTE)->delete();
            $apagadas += $n;
        } while ($n > 0);

        $this->line("Empresa {$empresaId}: {$apagadas} posição(ões) apagada(s) (retenção {$dias}d).");

        return $apagadas;
    }

    /** Janela de retenção desta empresa, em dias. */
    private function retencaoDias(int $empresaId): int
    {
        if ($this->option('dias') !== null) {
            return (int) $this->option('dias');
        }

        $config = EmpresaConfig::query()->where('empresa_id', $empresaId)->first();
        $dados = $config?->dados ?? [];

        return (int) ($dados['monitora_retencao_dias'] ?? self::RETENCAO_PADRAO_DIAS);
    }
}
