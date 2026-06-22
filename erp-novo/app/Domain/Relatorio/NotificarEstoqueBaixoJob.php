<?php

namespace App\Domain\Relatorio;

use App\Domain\Mobile\PushService;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job de fila (N12) — notifica os gestores de cada empresa sobre itens de estoque
 * abaixo do mínimo. Disparado pelo cron diário (notify:alertas). Assíncrono.
 */
class NotificarEstoqueBaixoJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $empresaId)
    {
    }

    public function handle(RelatorioService $relatorios, PushService $push): void
    {
        $itens = $relatorios->estoqueBaixo($this->empresaId);
        if ($itens === []) {
            return;
        }

        $empresa = Empresa::find($this->empresaId);
        if (! $empresa) {
            return;
        }

        // Notifica os usuários (gestores) da empresa.
        $userIds = User::query()->where('empresa_id', $this->empresaId)->where('ativo', true)->pluck('id');
        $n = count($itens);
        foreach ($userIds as $userId) {
            $push->paraUsuario($userId, 'Estoque baixo', "{$n} item(ns) abaixo do mínimo.", ['tipo' => 'estoque_baixo']);
        }
    }
}
