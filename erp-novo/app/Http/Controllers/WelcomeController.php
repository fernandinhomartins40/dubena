<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Raiz do domínio. Existe como controller (e não como closure na rota) porque
 * closure de ação impede `route:cache` — e o cache de rotas é o que evita que
 * cada requisição, e cada processo do scheduler, reparseie routes/api.php.
 */
class WelcomeController extends Controller
{
    public function index(): View
    {
        return view('welcome');
    }
}
