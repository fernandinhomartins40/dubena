<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class VehiclePosition
 * @package App
 * @mixin Model
 * @mixin \Eloquent
 */
class VehiclePosition extends ApiModel
{
    protected $table = 'vehiclespositions';

    protected $fillable = [
        "index", "pedido_id", "latitude", "longitude"
    ];

}


