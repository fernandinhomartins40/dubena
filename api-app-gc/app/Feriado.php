<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Feriado
 *
 * @property int $id
 * @property int $user_id
 * @property string $descricao
 * @property int $ativo
 * @property string $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Feriado whereAtivo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Feriado whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Feriado whereData($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Feriado whereDescricao($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Feriado whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Feriado whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Feriado whereUserId($value)
 * @mixin \Eloquent
 */
class Feriado extends Model
{
    protected $table = "feriados";

    protected $fillable = [
        'user_id', 'data', 'descricao', 'ativo'
    ];

}
