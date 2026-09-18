<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryOrder extends Model
{
    protected $guarded = [];

    public function salesOrder() {
        return $this->belongsTo(SalesOrder::class);
    }

    /**
     * The raw BigQuery line items belonging to this DO (matched by DO
     * number — bq_delivery_orders has no foreign key, just the same
     * do_number text value, since it's a flat sync of the SAP view and
     * one DO can have several material/batch lines).
     */
    public function bqLines() {
        return $this->hasMany(BqDeliveryOrder::class, 'delivery_order_number', 'do_number');
    }

    /**
     * Convenience accessors for the summary row in the list view, so the
     * Blade template doesn't need to loop bqLines itself for these.
     */
    public function getBqCustomerAttribute() {
        return $this->bqLines->first()->sold_to_party ?? null;
    }

    public function getBqShipToAttribute() {
        return $this->bqLines->first()->ship_to_party ?? null;
    }

    public function getBqTotalQuantityAttribute() {
        return $this->bqLines->sum('quantity');
    }

    public function getBqMaterialCountAttribute() {
        return $this->bqLines->pluck('material_number')->unique()->count();
    }
}