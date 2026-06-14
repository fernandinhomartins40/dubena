<?php

use Illuminate\Http\Request;
use App\User;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/user', function (Request $request) {
	return $request->user();
})->middleware('auth:api');
//Bina
Route::post('/testarToken', ['middleware' => 'auth:api', 'uses' => 'ApiController@testarToken']);
//Android
Route::post('/getUsuarios', ['middleware' => 'auth:api', 'uses' => 'ApiController@getUsuarios']);
Route::post('/getEmpresas', ['middleware' => 'auth:api', 'uses' => 'ApiController@getEmpresas']);
//Integração TRACCAR
Route::get('/savePosition', ['uses' => 'ApiController@savePosition']);