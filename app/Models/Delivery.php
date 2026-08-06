<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $guarded = [];

    public function deliveryOrder() {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function vehicleChecklist() {
        return $this->hasOne(VehicleChecklist::class);
    }
}
