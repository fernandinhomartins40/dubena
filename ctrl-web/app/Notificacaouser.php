<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Notificacaouser
 *
 * @property string|null $CREATED_AT
 * @property int $EMPRESA_ID
 * @property int $ID
 * @property int $NOTIFICACAO_ID
 * @property string $STATUS
 * @property string $TELA
 * @property string|null $UPDATED_AT
 * @property int $USER_ID
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Notificacaouser whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Notificacaouser whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Notificacaouser whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Notificacaouser whereNOTIFICACAOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Notificacaouser whereSTATUS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Notificacaouser whereTELA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Notificacaouser whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Notificacaouser whereUSERID($value)
 * @mixin \Eloquent
 */
class Notificacaouser extends Model
{
    protected $fillable = ["user_id", "empresa_id", "notificacao_id", "status"];

    public function getUser()
    {
        return $this->belongsToMany('App\User','user_id');
    }

    public function getEmpresa()
    {
        return $this->belongsToMany('App\Empresa','empresa_id');
    }

    public function getNotificacao()
    {
        return $this->belongsToMany('App\Notificacoes','notificacao_id');
    }

    /**
     * Returns query with users that should receive the notifications
     * @param string $appnotification -> notification from the app or not
     * @return string $query
     */
    public function getUsersQuery($appnotification = "0")
    {
        return "select users.id as user_id, noti.id as notificacao_id, noti.empresa_id, noti.tela, cast('N' as varchar(1)) as status ".
            "from notificacoes noti ".
            "inner join empresa_user empu on noti.empresa_id = empu.empresa_id ".
            "inner join users on empu.user_id = users.id ".
            "inner join menuusers mun on mun.user_id = users.id and empu.empresa_id = mun.empresa_id ".
            "inner join menus on mun.menu_id = menus.id ".
            "left join notificacaousers notiu on notiu.user_id = users.id and notiu.notificacao_id = noti.id ".
            "where notiu.notificacao_id is null and ".
            "menus.descricao like cast(('%' || noti.tela || '.index') as varchar(50)) and ".
            "mun.alerta = 1 and noti.appnotification = $appnotification ".
            "order by user_id";
    }
}
