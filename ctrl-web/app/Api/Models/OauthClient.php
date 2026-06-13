<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\OauthClient
 *
 * @mixin \Eloquent
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string $secret
 * @property string $redirect
 * @property int $personal_access_client
 * @property int $password_client
 * @property int $revoked
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|\App\OauthClient whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\OauthClient whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\OauthClient whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\OauthClient wherePasswordClient($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\OauthClient wherePersonalAccessClient($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\OauthClient whereRedirect($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\OauthClient whereRevoked($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\OauthClient whereSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\OauthClient whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\OauthClient whereUserId($value)
 * @property-read \App\User|null $user
 */
class OauthClient extends ApiModel
{
    protected $table = 'oauth_clients';

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}


