/*
 |--------------------------------------------------------------------------
 | Mix Asset Management (FASE 4)
 |--------------------------------------------------------------------------
 |
 | Substitui o pipeline Gulp/Laravel Elixir (descontinuado em ~2017) por
 | Laravel Mix. Compila o mesmo SASS (resources/assets/sass/app.scss) que o
 | Elixir compilava, preservando o caminho de saída (public/css/app.css).
 |
 */

const mix = require('laravel-mix');

mix.sass('resources/assets/sass/app.scss', 'public/css');
