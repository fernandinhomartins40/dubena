<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Ponto único de autorização dos controllers (Fase A1).
 *
 * Substitui os 36 `private function autorizar()` idênticos espalhados pelos
 * controllers: todos passavam direto por `temPermissao()`. Agora a checagem é
 * delegada ao Gate (definido em AuthServiceProvider), que por sua vez delega a
 * `temPermissao()` — então a regra de negócio NÃO muda, mas o enforcement vira
 * um ponto único, testável e auditável (camada 2 do defense-in-depth).
 *
 * Contrato preservado byte-a-byte: 403 com mensagem 'Sem permissão.' — usamos
 * `Gate::denies` + `abort_unless` (não `Gate::authorize`) justamente para manter
 * a mensagem e o status idênticos aos de antes da centralização.
 */
trait AutorizaPorPermissao
{
    /**
     * Aborta com 403 se o usuário autenticado não tiver a permissão "modulo.acao".
     */
    protected function autorizar(Request $request, string $chave): void
    {
        abort_if(Gate::forUser($request->user())->denies($chave), 403, 'Sem permissão.');
    }
}
