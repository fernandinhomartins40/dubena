<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Oauthclient
 *
 * @property string|null $CREATED_AT
 * @property int $ID
 * @property string $NAME
 * @property string $PASSWORD_CLIENT
 * @property string $PERSONAL_ACCESS_CLIENT
 * @property string $REDIRECT
 * @property string $REVOKED
 * @property string $SECRET
 * @property string|null $UPDATED_AT
 * @property int|null $USER_ID
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Oauthclient whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Oauthclient whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Oauthclient whereNAME($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Oauthclient wherePASSWORDCLIENT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Oauthclient wherePERSONALACCESSCLIENT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Oauthclient whereREDIRECT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Oauthclient whereREVOKED($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Oauthclient whereSECRET($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Oauthclient whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Oauthclient whereUSERID($value)
 * @mixin \Eloquent
 */
class Oauthclient extends Model
{
	protected $table = 'oauth_clients';

}
