<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $quotationCount = \App\Models\Quotation::count();
        $soCount = \App\Models\SalesOrder::count();
        $doCount = \App\Models\DeliveryOrder::count();
        $deliveryCount = \App\Models\Delivery::count();

        return view('dashboard.index', compact('quotationCount', 'soCount', 'doCount', 'deliveryCount'));
    }
}
