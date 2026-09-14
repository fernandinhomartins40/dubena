<?php

use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

// Controller, e não closure: closure de ação faz `route:cache` falhar, e sem
// esse cache toda requisição reparseia as rotas do zero.
Route::get('/', [WelcomeController::class, 'index']);
