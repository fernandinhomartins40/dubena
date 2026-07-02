<?php

namespace App\Console\Commands;

use App\Domain\Missao\GeradorMissaoService;
use App\Models\Empresa;
use Illuminate\Console\Command;

/**
 * logistica:gerar-missoes (L7) — cron. Varre as empresas ativas e atribui missões
 * de campo aos entregadores OCIOSOS (em jornada, sem entregas há mais de
 * `ociosidade_min`). A inteligência (área/janela/1 por vez) vive no
 * GeradorMissaoService.
 */
class MissoesGerar extends Command
{
    protected $signature = 'logistica:gerar-missoes';

    protected $description = 'Atribui missões de campo aos entregadores ociosos (por empresa).';

    public function handle(GeradorMissaoService $gerador): int
    {
        $total = 0;
        foreach (Empresa::query()->where('ativo', true)->pluck('id') as $empresaId) {
            $total += $gerador->gerarParaEmpresa((int) $empresaId);
        }

        $this->info("Missões atribuídas: {$total}.");

        return self::SUCCESS;
    }
}
