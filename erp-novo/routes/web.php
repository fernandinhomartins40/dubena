<?php

use App\Http\Controllers\PaginasPublicasController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

// Controller, e não closure: closure de ação faz `route:cache` falhar, e sem
// esse cache toda requisição reparseia as rotas do zero.
Route::get('/', [WelcomeController::class, 'index']);

// Exigidas pela Play Console e pela App Store Connect para publicar os apps.
Route::get('/privacidade', [PaginasPublicasController::class, 'privacidade']);
Route::get('/excluir-conta', [PaginasPublicasController::class, 'excluirConta']);
