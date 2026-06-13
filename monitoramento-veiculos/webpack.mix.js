/*
 |--------------------------------------------------------------------------
 | Mix Asset Management (FASE 4)
 |--------------------------------------------------------------------------
 |
 | Substitui o pipeline Gulp/Laravel Elixir (descontinuado) por Laravel Mix,
 | compilando o mesmo SASS para public/css.
 |
 */

const mix = require('laravel-mix');

mix.sass('resources/assets/sass/app.scss', 'public/css');
