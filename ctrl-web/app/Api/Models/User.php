<?php

namespace App\Api\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * App\User
 *
 * @property-read \Illuminate\Database\Eloquent\Collection|\Laravel\Passport\Client[] $clients
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection|\Illuminate\Notifications\DatabaseNotification[] $notifications
 * @property-read \Illuminate\Database\Eloquent\Collection|\Laravel\Passport\Token[] $tokens
 * @mixin \Eloquent
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string $erpurl
 * @property string $horaabertura
 * @property string $horafechamento
 * @property string|null $remember_token
 * @property int $erpempresa_id
 * @property int $admin
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereAdmin($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereErpempresaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereErpurl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereHoraabertura($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereHorafechamento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereUpdatedAt($value)
 * @property int $ativo
 * @property string|null $erp_authorization
 * @property string|null $serviceuser_id
 * @property int $permiteagendamento
 * @property string|null $fantasia
 * @property string|null $semanahoraabertura
 * @property string|null $semanahorafechamento
 * @property string|null $sabadohoraabertura
 * @property string|null $sabadohorafechamento
 * @property string|null $domingohoraabertura
 * @property string|null $domingohorafechamento
 * @property string|null $feriadohoraabertura
 * @property string|null $feriadohorafechamento
 * @property string|null $uf
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\OauthClient[] $oauth
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereAtivo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereDomingohoraabertura($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereDomingohorafechamento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereErpAuthorization($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereFantasia($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereFeriadohoraabertura($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereFeriadohorafechamento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User wherePermiteagendamento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereSabadohoraabertura($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereSabadohorafechamento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereSemanahoraabertura($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereSemanahorafechamento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereServiceuserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereUf($value)
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $enderecocompleto
 * @property mixed|null $thumbnail
 * @property float $avaliacao
 * @property string $telefone
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereAvaliacao($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereEnderecocompleto($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereLatitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereLongitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereTelefone($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereThumbnail($value)
 */
class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    // FASE 5: usuário do app (banco sgcm_api), distinto do App\User do ERP.
    protected $connection = 'sgcm_api';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'admin',
        'erpempresa_id',
        'erpurl',
        'erp_authorization',
        'serviceuser_id',
        'permiteagendamento',
        'ativo',
        'fantasia',
        'semanahoraabertura',
        'semanahorafechamento',
        'sabadohoraabertura',
        'sabadohorafechamento',
        'domingohoraabertura',
        'domingohorafechamento',
        'feriadohoraabertura',
        'feriadohorafechamento',
        'uf',
        "latitude",
        "longitude",
        "enderecocompleto",
        "thumbnail",
        "avaliacao",
        "telefone",
        "delivery_time_start",
        "delivery_time_end",
        "valorfretegp",
        "produtogp_id",
        "gaspovoativado",
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function oauth()
    {
        return $this->hasMany(OauthClient::class);
    }
}

