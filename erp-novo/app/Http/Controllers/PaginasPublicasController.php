<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Política de privacidade e exclusão de conta — URLs que a Play Console e a
 * App Store Connect exigem antes de publicar. Controller (e não closure) pelo
 * mesmo motivo do WelcomeController: closure impede `route:cache`.
 */
class PaginasPublicasController extends Controller
{
    public function privacidade(): View
    {
        return view('publico.privacidade', ['p' => $this->dados()]);
    }

    public function excluirConta(): View
    {
        return view('publico.excluir-conta', ['p' => $this->dados()]);
    }

    /** @return array<string, string|null> */
    private function dados(): array
    {
        $p = config('paginas_publicas');
        abort_if(blank($p['razao_social'] ?? null), 404);

        return $p;
    }
}
