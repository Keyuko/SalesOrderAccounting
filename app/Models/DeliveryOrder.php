<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryOrder extends Model
{
    protected $guarded = [];

    public function salesOrder() {
        return $this->belongsTo(SalesOrder::class);
    }
}
