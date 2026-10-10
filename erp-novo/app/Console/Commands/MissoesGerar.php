<?php

namespace App\Console\Commands;

use App\Console\Concerns\RelataAutomacao;
use App\Domain\Missao\GeradorMissaoService;
use App\Domain\Tenant\AutomacaoPorEmpresa;
use Illuminate\Console\Command;

/**
 * logistica:gerar-missoes (L7) — cron. Varre as empresas ativas e atribui missões
 * de campo aos entregadores OCIOSOS (em jornada, sem entregas há mais de
 * `ociosidade_min`). A inteligência (área/janela/1 por vez) vive no
 * GeradorMissaoService.
 *
 * Opera com a identidade de automação de cada tenant (ver `AutomacaoPorEmpresa`).
 */
class MissoesGerar extends Command
{
    use RelataAutomacao;

    protected $signature = 'logistica:gerar-missoes';

    protected $description = 'Atribui missões de campo aos entregadores ociosos (por empresa).';

    public function handle(GeradorMissaoService $gerador, AutomacaoPorEmpresa $automacao): int
    {
        $resultado = $automacao->paraCada(fn (int $empresaId) => $gerador->gerarParaEmpresa($empresaId));

        $this->info("Missões atribuídas: {$resultado->total()} em {$resultado->processadas()} empresa(s).");

        return $this->relatar($resultado);
    }
}
