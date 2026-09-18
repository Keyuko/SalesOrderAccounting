<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BqDeliveryOrder extends Model
{
    protected $table = 'bq_delivery_orders';

    protected $guarded = [];

    protected $casts = [
        'estimated_goods_issue_date' => 'date',
        'actual_goods_issue_date' => 'date',
        'fbl3n_posting_date' => 'date',
        'quantity' => 'decimal:4',
        'cost_value_in_local_currency' => 'decimal:4',
        'goods_value_in_local_currency' => 'decimal:4',
    ];

    /**
     * Matched by DO number, since this raw BigQuery table has no foreign key
     * to delivery_orders — it's linked purely by delivery_order_number.
     */
    public function deliveryOrder()
    {
        return $this->belongsTo(DeliveryOrder::class, 'delivery_order_number', 'do_number');
    }
}