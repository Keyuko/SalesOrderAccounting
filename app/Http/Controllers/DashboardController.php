<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $qQuery = \App\Models\Quotation::query();
        $soQuery = \App\Models\SalesOrder::query();
        $doQuery = \App\Models\DeliveryOrder::query();
        $delQuery = \App\Models\Delivery::query();

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $start = $request->start_date . ' 00:00:00';
            $end = $request->end_date . ' 23:59:59';
            $qQuery->whereBetween('created_at', [$start, $end]);
            $soQuery->whereBetween('created_at', [$start, $end]);
            $doQuery->whereBetween('created_at', [$start, $end]);
            $delQuery->whereBetween('created_at', [$start, $end]);
        }

        $quotationCount = $qQuery->count();
        $soCount = $soQuery->count();
        $doCount = $doQuery->count();
        $deliveryCount = $delQuery->count();

        return view('dashboard.index', compact('quotationCount', 'soCount', 'doCount', 'deliveryCount'));
    }
}
